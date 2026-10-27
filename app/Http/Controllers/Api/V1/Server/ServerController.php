<?php

namespace App\Http\Controllers\Api\V1\Server;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ParticipantResource;
use App\Models\ChatApp;
use App\Models\Conversation;
use App\Models\Participant;
use App\Services\ConversationService;
use App\Services\MessageService;
use App\Services\ParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Серверный API для бэкенда сайта-хозяина. Авторизация: `Authorization: Bearer lcs_…` (серверный токен приложения).
 * Именно здесь сайт решает, кто с кем может общаться: создаёт диалоги, добавляет и убирает людей.
 *
 * @tags Серверный API (для бэкенда сайта)
 */
class ServerController extends Controller
{
    public function __construct(
        private ParticipantService $participants,
        private ConversationService $conversations,
        private MessageService $messages,
    ) {
    }

    private function app(Request $request): ChatApp
    {
        return $request->attributes->get('chat_app');
    }

    /**
     * Создать или обновить участника
     *
     * Имя, роль и признак оператора перезаписываются. Оператор автоматически попадает во все диалоги поддержки.
     */
    public function upsertParticipant(Request $request, string $externalId): JsonResponse
    {
        $data = $request->validate($this->participantRules());

        $participant = $this->participants->upsert($this->app($request), $externalId, $data);

        return ParticipantResource::make($participant)->response();
    }

    /** Заблокировать участника: токены отзываются, вход закрыт (диалоги и история остаются) */
    public function blockParticipant(Request $request, string $externalId): JsonResponse
    {
        $participant = Participant::where('app_id', $this->app($request)->id)->where('external_id', $externalId)->firstOrFail();
        $this->participants->block($participant);

        return response()->json(['message' => 'ok']);
    }

    /** Снять блокировку с участника */
    public function unblockParticipant(Request $request, string $externalId): JsonResponse
    {
        $participant = Participant::where('app_id', $this->app($request)->id)->where('external_id', $externalId)->firstOrFail();
        $participant->forceFill(['blocked_at' => null])->save();

        return response()->json(['message' => 'ok']);
    }

    /** Непрочитанные участника — чтобы показать число в меню при серверном рендере страницы */
    public function unread(Request $request, string $externalId): JsonResponse
    {
        $participant = Participant::where('app_id', $this->app($request)->id)->where('external_id', $externalId)->first();

        return response()->json(['data' => $participant ? $this->conversations->unreadTotals($participant) : ['total' => 0, 'conversations' => 0]]);
    }

    /**
     * Создать диалог (идемпотентно)
     *
     * Участники передаются как `members` с `external_id`; неизвестные создаются на лету из присланных полей.
     * `unique_key` делает вызов идемпотентным: тот же ключ — тот же диалог (например `request:123:seller:45`).
     * `context` привязывает диалог к заявке, товару или документу. `message` — необязательное первое
     * сообщение (`from` — external_id автора; без `from` оно системное).
     */
    public function createConversation(Request $request): JsonResponse
    {
        $app = $this->app($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(['direct', 'group', 'support'])],
            'title' => ['nullable', 'string', 'max:190'],
            'unique_key' => ['nullable', 'string', 'max:190'],
            'members' => ['required', 'array', 'min:1', 'max:500'],
            'members.*.external_id' => ['required', 'string', 'max:191'],
            ...collect($this->participantRules())->mapWithKeys(fn ($r, $k) => ["members.*.$k" => $r])->all(),
            'context' => ['nullable', 'array'],
            'context.type' => ['required_with:context', 'string', 'max:64'],
            'context.id' => ['required_with:context', 'string', 'max:191'],
            'context.title' => ['nullable', 'string', 'max:255'],
            'context.url' => ['nullable', 'url', 'max:500'],
            'message' => ['nullable', 'array'],
            'message.body' => ['required_with:message', 'string', 'max:'.config('chat.message_max_length')],
            'message.from' => ['nullable', 'string', 'max:191'],
        ]);

        abort_if($data['type'] === 'direct' && count($data['members']) !== 2, 422, 'В личном диалоге ровно два участника.');

        [$conversation, $created, $members] = DB::transaction(function () use ($app, $data) {
            $members = collect($data['members'])->map(
                fn ($m) => $this->participants->upsert($app, $m['external_id'], $this->participants->publicFields($m))
            );
            [$conversation, $created] = $this->conversations->create($app, $data['type'], $members, $members->first(), [
                'title' => $data['title'] ?? null,
                'context' => $data['context'] ?? null,
                'unique_key' => $data['unique_key'] ?? null,
            ]);

            return [$conversation, $created, $members];
        });

        if ($created && ! empty($data['message'])) {
            $from = ! empty($data['message']['from'])
                ? Participant::where('app_id', $app->id)->where('external_id', $data['message']['from'])->first()
                : null;
            $this->messages->send($conversation, $from, [
                'type' => $from ? 'text' : 'system',
                'body' => $data['message']['body'],
            ]);
        }

        if ($created) {
            $this->conversations->announce($conversation);
        }

        $viewer = $members->first();

        return ConversationResource::make($this->conversations->findFor($viewer, $conversation->id))
            ->forViewer($viewer)->response()->setStatusCode($created ? 201 : 200);
    }

    /** Добавить участников в диалог (неизвестные создаются) */
    public function addMembers(Request $request, string $conversation): JsonResponse
    {
        $app = $this->app($request);
        $c = Conversation::where('app_id', $app->id)->findOrFail($conversation);

        $data = $request->validate([
            'members' => ['required', 'array', 'min:1', 'max:500'],
            'members.*.external_id' => ['required', 'string', 'max:191'],
            ...collect($this->participantRules())->mapWithKeys(fn ($r, $k) => ["members.*.$k" => $r])->all(),
        ]);

        $members = collect($data['members'])->map(fn ($m) => $this->participants->upsert($app, $m['external_id'], $this->participants->publicFields($m)));
        $this->conversations->addMembers($c, $members);
        $this->conversations->announce($c, $members->pluck('id')->all());

        return response()->json(['message' => 'ok', 'added' => $members->count()]);
    }

    /** Убрать участника из диалога (его история у остальных остаётся) */
    public function removeMember(Request $request, string $conversation, string $externalId): JsonResponse
    {
        $app = $this->app($request);
        $c = Conversation::where('app_id', $app->id)->findOrFail($conversation);
        $participant = Participant::where('app_id', $app->id)->where('external_id', $externalId)->firstOrFail();

        $this->conversations->removeMember($c, $participant);
        broadcast(new \App\Events\ChatEvent('participant.'.$participant->id, 'conversation.removed', ['conversation_id' => $c->id]));

        return response()->json(['message' => 'ok']);
    }

    /** Архивировать диалог: исчезает из списков участников, данные сохраняются */
    public function archive(Request $request, string $conversation): JsonResponse
    {
        $c = Conversation::where('app_id', $this->app($request)->id)->findOrFail($conversation);
        $c->forceFill(['archived_at' => now()])->save();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Написать в диалог от имени участника или системы
     *
     * `from` — external_id автора; без него сообщение системное («Заявка принята»).
     */
    public function sendMessage(Request $request, string $conversation): JsonResponse
    {
        $app = $this->app($request);
        $c = Conversation::where('app_id', $app->id)->findOrFail($conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('chat.message_max_length')],
            'from' => ['nullable', 'string', 'max:191'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $from = null;
        if (! empty($data['from'])) {
            $from = Participant::where('app_id', $app->id)->where('external_id', $data['from'])->firstOrFail();
            abort_unless($c->memberFor($from), 422, 'Автор не состоит в диалоге.');
        }

        $message = $this->messages->send($c, $from, [
            'type' => $from ? 'text' : 'system',
            'body' => $data['body'],
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        return MessageResource::make($message->load('sender'))->response()->setStatusCode(201);
    }

    /** @return array<string, mixed> */
    private function participantRules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:190'],
            'email' => ['sometimes', 'nullable', 'string', 'max:190'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'role' => ['sometimes', 'nullable', 'string', 'max:64'],
            'is_operator' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
