<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Публичная ссылка на сообщение. Доступ: members — участники диалога, app — любой вошедший в чат приложения,
 * anyone — все, у кого есть ссылка. Дополнительно: пароль и срок действия.
 */
class MessageShare extends Model
{
    public const ACCESS = ['members', 'app', 'anyone'];

    /** Допустимые сроки в секундах (null — без срока). */
    public const TTL = [3600, 86400, 604800, 2592000];

    protected $fillable = ['message_id', 'token', 'access', 'password_hash', 'expires_at', 'created_by', 'views'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function url(): string
    {
        return url('/m/'.$this->token);
    }
}
