<?php

namespace App\Services;

use App\Models\ChatApp;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Builder;
use App\Events\ChatEvent;
use App\Http\Resources\ConversationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * Диалоги участника с числом непрочитанных, самые свежие сверху.
     *
     * @param  array{type?: ?string, context_type?: ?string, context_id?: ?string, unread?: ?bool, q?: ?string}  $filters
     */
    public function listFor(Participant $viewer, array $filters = []): Builder
    {
        $unread = '(select count(*) from messages m where m.conversation_id = conversations.id'
            .' and m.id > coalesce(cm.last_read_message_id, 0)'
            .' and (m.participant_id is null or m.participant_id <> cm.participant_id)'
            .' and m.deleted_at is null)';

        $query = Conversation::query()
            ->join('conversation_members as cm', function ($join) use ($viewer) {
                $join->on('cm.conversation_id', '=', 'conversations.id')
                    ->where('cm.participant_id', '=', $viewer->id)
                    ->whereNull('cm.left_at')
                    ->whereNull('cm.hidden_at');
            })
            ->where('conversations.app_id', $viewer->app_id)
            ->whereNull('conversations.archived_at')
            ->select('conversations.*', 'cm.muted_until as muted_until', 'cm.hidden_from_message_id as hidden_from_message_id', 'cm.pinned_at as pinned_at', 'cm.pin_order as pin_order')
            ->selectRaw("$unread as unread_count")
            ->with(['activeMembers.participant', 'lastMessage.sender', 'creator'])
            ->orderByRaw('(cm.pinned_at is null) asc')
            ->orderByRaw('(cm.pin_order is null) asc')
            ->orderBy('cm.pin_order')
            ->orderByDesc('cm.pinned_at')
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id');

        if (! empty($filters['type'])) {
            $query->where('conversations.type', $filters['type']);
        }
        if (! empty($filters['context_type'])) {
            $query->where('conversations.context_type', $filters['context_type']);
        }
        if (isset($filters['context_id']) && $filters['context_id'] !== '') {
            $query->where('conversations.context_id', $filters['context_id']);
        }
        if (! empty($filters['unread'])) {
            $query->whereRaw("$unread > 0");
        }
        if (! empty($filters['q'])) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('conversations.title', 'like', $like)
                    ->orWhere('conversations.context_title', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))
                            ->from('conversation_members as sm')
                            ->join('participants as sp', 'sp.id', '=', 'sm.participant_id')
                            ->whereColumn('sm.conversation_id', 'conversations.id')
                            ->where('sp.name', 'like', $like);
                    });
            });
        }

        return $query;
    }

    /** Один диалог в том же виде (с unread_count), либо null, если участник в нём не состоит. */
    public function findFor(Participant $viewer, string $id): ?Conversation
    {
        return $this->listFor($viewer)->where('conversations.id', $id)->first();
    }

    /**
     * Создаёт диалог или возвращает уже существующий с тем же ключом.
     *
     * @param  iterable<Participant>  $members
     * @param  array{title?: ?string, context?: ?array, unique_key?: ?string, meta?: ?array}  $attrs
     * @return array{0: Conversation, 1: bool} [диалог, создан ли только что]
     */
    public function create(ChatApp $app, string $type, iterable $members, ?Participant $creator = null, array $attrs = []): array
    {
        $uniqueKey = ! empty($attrs['unique_key']) ? hash('sha256', $app->id.'|'.$attrs['unique_key']) : null;

        if ($uniqueKey && ($existing = Conversation::where('unique_key', $uniqueKey)->first())) {
            $this->addMembers($existing, $members);

            return [$existing, false];
        }

        $context = $attrs['context'] ?? null;

        $conversation = DB::transaction(function () use ($app, $type, $members, $creator, $attrs, $context, $uniqueKey) {
            $conversation = Conversation::create([
                'app_id' => $app->id,
                'type' => $type,
                'title' => $attrs['title'] ?? null,
                'context_type' => $context['type'] ?? null,
                'context_id' => isset($context['id']) ? (string) $context['id'] : null,
                'context_title' => $context['title'] ?? null,
                'context_url' => $context['url'] ?? null,
                'unique_key' => $uniqueKey,
                'created_by' => $creator?->id,
                'last_message_at' => now(),
                'meta' => $attrs['meta'] ?? null,
            ]);

            $this->addMembers($conversation, $members, $creator);

            if ($type === Conversation::TYPE_SUPPORT) {
                $this->addMembers($conversation, Participant::where('app_id', $app->id)
                    ->where('is_operator', true)->whereNull('blocked_at')->get());
            }

            return $conversation;
        });

        return [$conversation, true];
    }

    /** @param  iterable<Participant>  $participants */
    public function addMembers(Conversation $conversation, iterable $participants, ?Participant $owner = null): void
    {
        foreach ($participants as $participant) {
            if ($participant->app_id !== $conversation->app_id) {
                throw new \InvalidArgumentException('Участник из другого приложения.');
            }

            $member = ConversationMember::firstOrNew([
                'conversation_id' => $conversation->id,
                'participant_id' => $participant->id,
            ]);

            if (! $member->exists) {
                $member->role = $owner && $owner->id === $participant->id ? 'owner' : 'member';
                // Пришедший в существующий диалог не получает «всю историю как непрочитанную».
                $member->last_read_message_id = $conversation->last_message_id;
            }

            // Явное добавление (повторное обращение «Написать складу») возвращает скрытый диалог.
            $member->left_at = null;
            $member->hidden_at = null;
            $member->save();
        }
    }

    public function removeMember(Conversation $conversation, Participant $participant): void
    {
        ConversationMember::where('conversation_id', $conversation->id)
            ->where('participant_id', $participant->id)
            ->update(['left_at' => now()]);
    }

    /**
     * «Удалить для себя»: диалог пропадает из списка участника, у остальных остаётся.
     * Вернётся с новым сообщением, но прежняя история ему уже не показывается.
     */
    public function hideFor(Conversation $conversation, Participant $participant): void
    {
        $last = $conversation->last_message_id;

        ConversationMember::where('conversation_id', $conversation->id)
            ->where('participant_id', $participant->id)
            ->update(['pinned_at' => null, 'pin_order' => null] + array_filter([
                'hidden_at' => now(),
                'hidden_from_message_id' => $last,
                'last_read_message_id' => $last,
            ], fn ($v) => $v !== null));

        broadcast(new ChatEvent('participant.'.$participant->id, 'conversation.removed', ['conversation_id' => $conversation->id]));
    }

    /** Кто может удалить диалог у всех: оператор в поддержке, создатель — в личном диалоге и группе. */
    public function canDeleteForAll(Conversation $conversation, Participant $viewer): bool
    {
        if ($conversation->type === Conversation::TYPE_SUPPORT) {
            return (bool) $viewer->is_operator;
        }

        return $conversation->created_by !== null && $conversation->created_by === $viewer->id;
    }

    /**
     * «Удалить навсегда»: диалог пропадает у всех (мягкое удаление). Сообщения, вложения и контекст остаются
     * в базе и на диске как архив — не для показа; участники получают conversation.removed.
     */
    public function destroyEverywhere(Conversation $conversation, Participant $by): void
    {
        $members = ConversationMember::where('conversation_id', $conversation->id)->pluck('participant_id');

        DB::transaction(function () use ($conversation, $by) {
            // Ключ освобождается: «Написать складу» по той же заявке откроет новый пустой диалог.
            $fresh = Conversation::whereKey($conversation->id)->firstOrFail();
            $fresh->forceFill(['unique_key' => null, 'deleted_by' => $by->id])->save();
            $fresh->delete();
        });

        foreach ($members as $id) {
            broadcast(new ChatEvent('participant.'.$id, 'conversation.removed', ['conversation_id' => $conversation->id]));
        }
    }

    /** Ключ идемпотентности для диалогов, которые участник открывает сам (поддержка / личный). */
    public function selfKey(string $kind, Participant $a, ?Participant $b = null, ?array $context = null): string
    {
        $ids = collect([$a->id, $b?->id])->filter()->sort()->implode(':');

        return implode('|', [$kind, $ids, $context['type'] ?? '', $context['id'] ?? '']);
    }

    /**
     * Сколько всего непрочитанного у участника (для бейджа в шапке сайта).
     *
     * @return array{total: int, conversations: int}
     */
    public function unreadTotals(Participant $viewer): array
    {
        $row = DB::table('messages as m')
            ->join('conversation_members as cm', function ($join) use ($viewer) {
                $join->on('cm.conversation_id', '=', 'm.conversation_id')
                    ->where('cm.participant_id', '=', $viewer->id)
                    ->whereNull('cm.left_at')
                    ->whereNull('cm.hidden_at');
            })
            ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
            ->whereNull('c.archived_at')
            ->whereNull('c.deleted_at')
            ->whereRaw('m.id > coalesce(cm.last_read_message_id, 0)')
            ->where(fn ($q) => $q->whereNull('m.participant_id')->orWhereColumn('m.participant_id', '<>', 'cm.participant_id'))
            ->whereNull('m.deleted_at')
            ->selectRaw('count(*) as total, count(distinct m.conversation_id) as conversations')
            ->first();

        return ['total' => (int) $row->total, 'conversations' => (int) $row->conversations];
    }

    /** Сообщает участникам о новом диалоге (или о появлении в нём) на всех их устройствах. */
    public function announce(Conversation $conversation, ?array $only = null): void
    {
        $members = $conversation->activeMembers()->with('participant')->get();

        foreach ($members as $member) {
            if ($only !== null && ! in_array($member->participant_id, $only, true)) {
                continue;
            }

            $view = $this->findFor($member->participant, $conversation->id);

            if ($view) {
                broadcast(new ChatEvent('participant.'.$member->participant_id, 'conversation.updated', [
                    'conversation' => ConversationResource::make($view)->forViewer($member->participant)->resolve(Request::create('/')),
                ]));
            }
        }
    }
}
