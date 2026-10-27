<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ChatEvent;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Participant;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * @tags Диалоги
 */
class ConversationController extends ApiController
{
    public function __construct(private ConversationService $conversations)
    {
    }

    /**
     * Список моих диалогов
     *
     * Самые свежие сверху, с числом непрочитанных. Фильтр по контексту (`context_type` + `context_id`) отдаёт
     * диалоги, привязанные к сущности сайта — например к заявке. Постраничность: `page`, `per_page` (до 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['direct', 'group', 'support'])],
            'context_type' => ['nullable', 'string', 'max:64'],
            'context_id' => ['nullable', 'string', 'max:191'],
            'unread' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->conversations->listFor($this->viewer($request), $filters)->paginate($filters['per_page'] ?? 30);

        return ConversationResource::collection($page)->additional([]);
    }

    /**
     * Открыть диалог
     *
     * - `support` — обращение в поддержку (одно на контекст; повторный вызов вернёт уже открытое, код 200);
     * - `direct` — личный диалог с `participant_id`;
     * - `group` — группа: `title` и `participant_ids`.
     *
     * `direct` и `group` участникам доступны, только если сайт-хозяин включил `user_conversations`;
     * иначе их создаёт его бэкенд через серверный API.
     */
    public function store(Request $request): JsonResponse
    {
        $me = $this->viewer($request);
        $app = $me->app;

        $data = $request->validate([
            'type' => ['required', Rule::in(['direct', 'group', 'support'])],
            'participant_id' => ['required_if:type,direct', 'nullable', 'integer'],
            'participant_ids' => ['required_if:type,group', 'nullable', 'array', 'min:1', 'max:200'],
            'participant_ids.*' => ['integer'],
            'title' => ['nullable', 'string', 'max:190', 'required_if:type,group'],
            'context' => ['nullable', 'array'],
            'context.type' => ['required_with:context', 'string', 'max:64'],
            'context.id' => ['required_with:context', 'string', 'max:191'],
            'context.title' => ['nullable', 'string', 'max:255'],
            'context.url' => ['nullable', 'url', 'max:500'],
        ]);

        $type = $data['type'];
        $context = $this->safeContext($data['context'] ?? null, $app);

        if ($type === Conversation::TYPE_SUPPORT) {
            abort_unless($app->setting('support_enabled'), 403, 'Обращения в поддержку выключены.');
            [$conversation, $created] = $this->conversations->create($app, $type, [$me], $me, [
                'context' => $context,
                'unique_key' => $this->conversations->selfKey('support', $me, null, $context),
            ]);
        } else {
            abort_unless($app->setting('user_conversations'), 403, 'Создавать такие диалоги может только сайт-хозяин.');

            $others = Participant::where('app_id', $app->id)->whereNull('blocked_at')->whereKeyNot($me->id)
                ->whereIn('id', $type === 'direct' ? [$data['participant_id']] : $data['participant_ids'])->get();
            abort_if($others->isEmpty() || ($type === 'direct' && $others->count() !== 1), 422, 'Собеседник не найден.');

            [$conversation, $created] = $this->conversations->create($app, $type, $others->push($me), $me, [
                'title' => $type === 'group' ? $data['title'] : null,
                'context' => $context,
                'unique_key' => $type === 'direct' ? $this->conversations->selfKey('direct', $me, $others->first(), $context) : null,
            ]);
        }

        if ($created) {
            $this->conversations->announce($conversation);
        }

        $fresh = $this->conversations->findFor($me, $conversation->id);

        return ConversationResource::make($fresh)->response()->setStatusCode($created ? 201 : 200);
    }

    /** Один диалог */
    public function show(Request $request, string $conversation): ConversationResource
    {
        return ConversationResource::make($this->conversation($request, $conversation));
    }

    /**
     * Изменить диалог: название группы, выключить уведомления
     *
     * `muted_until` — до какого момента не присылать push (null — включить обратно).
     */
    public function update(Request $request, string $conversation): ConversationResource
    {
        $c = $this->conversation($request, $conversation);
        $me = $this->viewer($request);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:190'],
            'muted_until' => ['sometimes', 'nullable', 'date'],
        ]);

        if (array_key_exists('title', $data)) {
            abort_unless($c->type === Conversation::TYPE_GROUP && $c->memberFor($me)?->role === 'owner', 403, 'Название может менять владелец группы.');
            $c->forceFill(['title' => $data['title']])->save();
        }

        if (array_key_exists('muted_until', $data)) {
            $c->memberFor($me)->forceFill(['muted_until' => $data['muted_until']])->save();
        }

        return ConversationResource::make($this->conversations->findFor($me, $c->id));
    }

    /**
     * Добавить участников в группу
     *
     * Только владелец группы и только если сайт-хозяин разрешил `user_conversations`.
     */
    public function addMembers(Request $request, string $conversation): ConversationResource
    {
        $c = $this->conversation($request, $conversation);
        $me = $this->viewer($request);
        abort_unless($c->type === Conversation::TYPE_GROUP && $c->memberFor($me)?->role === 'owner' && $me->app->setting('user_conversations'), 403, 'Недостаточно прав.');

        $data = $request->validate(['participant_ids' => ['required', 'array', 'min:1', 'max:200'], 'participant_ids.*' => ['integer']]);

        $new = Participant::where('app_id', $me->app_id)->whereNull('blocked_at')->whereIn('id', $data['participant_ids'])->get();
        $this->conversations->addMembers($c, $new);
        $this->conversations->announce($c, $new->pluck('id')->all());

        return ConversationResource::make($this->conversations->findFor($me, $c->id));
    }

    /**
     * Убрать участника из группы или выйти самому
     *
     * Свой id — выход из группы; чужой — только владелец.
     */
    public function removeMember(Request $request, string $conversation, int $participant): JsonResponse
    {
        $c = $this->conversation($request, $conversation);
        $me = $this->viewer($request);
        abort_unless($c->type === Conversation::TYPE_GROUP, 422, 'Выйти можно только из группы.');
        abort_unless($participant === $me->id || $c->memberFor($me)?->role === 'owner', 403, 'Недостаточно прав.');

        $target = Participant::where('app_id', $me->app_id)->findOrFail($participant);
        $this->conversations->removeMember($c, $target);
        broadcast(new ChatEvent('participant.'.$target->id, 'conversation.removed', ['conversation_id' => $c->id]));

        return response()->json(['message' => 'ok']);
    }

    /** Контекст от клиента — недоверенный: ссылка на сайт допускается только с разрешённого origin. */
    private function safeContext(?array $context, $app): ?array
    {
        if (! $context) {
            return null;
        }

        if (! empty($context['url'])) {
            $parts = parse_url($context['url']);
            $origin = isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '';
            if (! $app->originAllowed($origin)) {
                $context['url'] = null;
            }
        }

        return $context;
    }
}
