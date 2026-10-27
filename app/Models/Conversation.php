<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUlids;

    public const TYPE_DIRECT = 'direct';
    public const TYPE_GROUP = 'group';
    public const TYPE_SUPPORT = 'support';

    protected $fillable = [
        'app_id', 'type', 'title', 'context_type', 'context_id', 'context_title', 'context_url',
        'unique_key', 'created_by', 'last_message_id', 'last_message_at', 'meta', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(ChatApp::class, 'app_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'created_by');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_at');
    }

    public function memberFor(Participant $participant): ?ConversationMember
    {
        return $this->members()->where('participant_id', $participant->id)->whereNull('left_at')->first();
    }

    public function hasContext(): bool
    {
        return $this->context_type !== null && $this->context_id !== null;
    }
}
