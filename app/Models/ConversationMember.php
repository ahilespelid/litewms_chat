<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMember extends Model
{
    protected $fillable = ['conversation_id', 'participant_id', 'role', 'last_read_message_id', 'muted_until', 'left_at', 'hidden_at', 'hidden_from_message_id', 'pinned_at'];

    protected function casts(): array
    {
        return ['muted_until' => 'datetime', 'left_at' => 'datetime', 'hidden_at' => 'datetime', 'pinned_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }
}
