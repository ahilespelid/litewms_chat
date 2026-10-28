<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Диалог глазами конкретного участника (название, число непрочитанных зависят от того, кто смотрит).
 *
 * @property-read Conversation $resource
 */
class ConversationResource extends JsonResource
{
    private ?Participant $viewer = null;

    public function forViewer(Participant $viewer): static
    {
        $this->viewer = $viewer;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $c = $this->resource;
        $viewer = $this->viewer ?? $request->user();

        return [
            'id' => $c->id,
            /** direct | group | support */
            'type' => $c->type,
            'title' => $this->titleFor($c, $viewer),
            /** К чему привязан диалог на сайте-хозяине (заявка, товар…) — null, если ни к чему. */
            'context' => $c->hasContext() ? [
                'type' => $c->context_type,
                'id' => $c->context_id,
                'title' => $c->context_title,
                'url' => $c->context_url,
            ] : null,
            'members' => $c->relationLoaded('activeMembers')
                ? $c->activeMembers->take(30)->map(fn ($m) => [
                    'id' => $m->participant_id,
                    'name' => $m->participant->name,
                    'avatar_url' => $m->participant->avatar_url,
                    'role' => $m->participant->role,
                    'kind' => $m->participant->kind,
                    'is_operator' => (bool) $m->participant->is_operator,
                    /** До какого сообщения этот участник прочитал диалог — по нему считаются галочки «прочитано». */
                    'last_read_message_id' => $m->last_read_message_id,
                ])->values()->all()
                : [],
            'member_count' => (int) ($c->member_count ?? ($c->relationLoaded('activeMembers') ? $c->activeMembers->count() : 0)),
            'last_message' => $c->relationLoaded('lastMessage') && $c->lastMessage
                ? MessageResource::make($c->lastMessage)->resolve($request)
                : null,
            /** Непрочитанные именно для того, кто смотрит. */
            'unread_count' => (int) ($c->unread_count ?? 0),
            /** Можно ли удалить диалог у всех (DELETE …?scope=all): оператор в поддержке, создатель в личном и группе. */
            'can_delete_for_all' => $viewer ? app(\App\Services\ConversationService::class)->canDeleteForAll($c, $viewer) : false,
            /** Закреплён ли диалог у меня: закреплённые идут в списке первыми. */
            'pinned' => ! empty($c->pinned_at),
            'pinned_at' => ! empty($c->pinned_at) ? \Illuminate\Support\Carbon::parse($c->pinned_at)->toIso8601String() : null,
            'muted' => isset($c->muted_until) && $c->muted_until && now()->lt($c->muted_until),
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }

    private function titleFor(Conversation $c, ?Participant $viewer): string
    {
        $context = $c->context_title ? ' · '.$c->context_title : '';

        if ($c->type === Conversation::TYPE_DIRECT && $viewer) {
            $other = $c->relationLoaded('activeMembers')
                ? $c->activeMembers->first(fn ($m) => $m->participant_id !== $viewer->id)?->participant
                : null;

            return ($c->title ?: ($other?->name ?? 'Диалог')).$context;
        }

        if ($c->type === Conversation::TYPE_SUPPORT) {
            // Обратившемуся виден «Поддержка», оператору — имя того, кто написал.
            $asker = $c->relationLoaded('creator') ? $c->creator : null;
            $isAsker = $viewer && $asker && $asker->id === $viewer->id;
            $base = $c->title ?: ($isAsker || ! $asker ? 'Поддержка' : $asker->name);

            return $base.$context;
        }

        return ($c->title ?: 'Группа').$context;
    }
}
