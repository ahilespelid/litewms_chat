<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Человек в чате. Для пользователей ЛК — зеркало записи из системы-хозяина (external_id),
 * для гостей и операторов «по ссылке» — отдельная запись с личной ссылкой.
 */
class Participant extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'app_id', 'external_id', 'kind', 'name', 'email', 'avatar_url', 'role',
        'is_operator', 'meta', 'link_secret_hash', 'last_seen_at', 'blocked_at',
    ];

    protected $hidden = ['link_secret_hash'];

    protected function casts(): array
    {
        return [
            'is_operator' => 'boolean',
            'meta' => 'array',
            'last_seen_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(ChatApp::class, 'app_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function isGuest(): bool
    {
        return $this->kind === 'guest';
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** Приватный канал участника: по нему приходят все его события реального времени. */
    public function channelName(): string
    {
        return 'participant.'.$this->id;
    }

    /** Создаёт новую личную ссылку-секрет (показывается один раз, в БД — только хеш). */
    public function issueLinkSecret(): string
    {
        $secret = Str::random(48);
        $this->forceFill(['link_secret_hash' => hash('sha256', $secret)])->save();

        return $secret;
    }
}
