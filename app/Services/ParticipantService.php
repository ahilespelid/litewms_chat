<?php

namespace App\Services;

use App\Models\ChatApp;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Participant;
use Illuminate\Support\Arr;

class ParticipantService
{
    /**
     * Создаёт или обновляет зеркало пользователя хозяина. Данные приходят из доверенного источника
     * (подпись handoff-токена или серверный токен), поэтому имя/роль/оператор перезаписываются при каждом входе.
     *
     * @param  array{name?: string, email?: ?string, avatar_url?: ?string, role?: ?string, is_operator?: bool, meta?: ?array}  $data
     */
    public function upsert(ChatApp $app, string $externalId, array $data): Participant
    {
        $participant = Participant::firstOrNew(['app_id' => $app->id, 'external_id' => $externalId]);
        $wasOperator = $participant->exists && $participant->is_operator;

        $participant->fill([
            'kind' => 'member',
            'name' => trim((string) ($data['name'] ?? '')) ?: ($participant->name ?: 'Пользователь '.$externalId),
            'email' => array_key_exists('email', $data) ? $data['email'] : $participant->email,
            'avatar_url' => array_key_exists('avatar_url', $data) ? $data['avatar_url'] : $participant->avatar_url,
            'role' => array_key_exists('role', $data) ? $data['role'] : $participant->role,
            'is_operator' => (bool) ($data['is_operator'] ?? $participant->is_operator ?? false),
            'meta' => array_key_exists('meta', $data) ? $data['meta'] : $participant->meta,
        ]);
        $participant->save();

        if ($participant->is_operator && ! $wasOperator) {
            $this->joinOpenSupportConversations($participant);
        }

        return $participant;
    }

    /** Новый оператор сразу видит уже идущие диалоги поддержки своего app. */
    public function joinOpenSupportConversations(Participant $operator): void
    {
        Conversation::where('app_id', $operator->app_id)
            ->where('type', Conversation::TYPE_SUPPORT)
            ->whereNull('archived_at')
            ->select('id')
            ->chunkById(200, function ($chunk) use ($operator) {
                foreach ($chunk as $conversation) {
                    // Всё прошлое считаем прочитанным: оператор не должен получить «1000 новых» в день назначения.
                    ConversationMember::firstOrCreate(
                        ['conversation_id' => $conversation->id, 'participant_id' => $operator->id],
                        ['role' => 'member', 'last_read_message_id' => Conversation::whereKey($conversation->id)->value('last_message_id')]
                    );
                }
            }, 'id');
    }

    public function block(Participant $participant): void
    {
        $participant->forceFill(['blocked_at' => now()])->save();
        $participant->tokens()->delete();
    }

    public function publicFields(array $data): array
    {
        return Arr::only($data, ['name', 'email', 'avatar_url', 'role', 'is_operator', 'meta']);
    }
}
