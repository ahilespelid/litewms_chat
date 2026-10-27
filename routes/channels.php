<?php

use App\Models\ConversationMember;
use Illuminate\Support\Facades\Broadcast;

// Личный канал участника: по нему приходят все его события. Слушать может только он сам.
Broadcast::channel('participant.{id}', function ($participant, $id) {
    return (int) $participant->id === (int) $id;
}, ['guards' => ['sanctum']]);

// Presence-канал открытого диалога: «кто онлайн» и «печатает…». Только для участников диалога.
Broadcast::channel('conversation.{id}', function ($participant, string $id) {
    $isMember = ConversationMember::where('conversation_id', $id)
        ->where('participant_id', $participant->id)->whereNull('left_at')->exists();

    return $isMember ? ['id' => $participant->id, 'name' => $participant->name] : false;
}, ['guards' => ['sanctum']]);
