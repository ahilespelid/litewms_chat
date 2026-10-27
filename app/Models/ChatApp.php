<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Подключённое приложение (ЛК litewms, другой сайт, мобильный клиент).
 * Всё остальное в чате принадлежит конкретной app и между app не пересекается.
 */
class ChatApp extends Model
{
    protected $table = 'chat_apps';

    protected $fillable = ['name', 'key', 'secret', 'server_token_hash', 'allowed_origins', 'settings', 'is_active'];

    protected $hidden = ['secret', 'server_token_hash'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'allowed_origins' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Настройки по умолчанию: безопасно закрыто, всё открывает host-бэкенд через серверный API. */
    public const DEFAULT_SETTINGS = [
        'support_enabled' => true,        // участник может сам открыть диалог с поддержкой
        'guest_enabled' => false,         // анонимные гости по ссылке (как в messenger)
        'user_conversations' => false,    // участники сами создают direct/group
        'directory' => false,             // поиск по участникам app
        'accent' => '#0f766e',            // фирменный цвет интерфейса
        'title' => 'Чат',
        'login_url' => null,              // адрес сайта-хозяина для кнопки «Войти через …» на странице /app
    ];

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? (self::DEFAULT_SETTINGS[$key] ?? $default);
    }

    /**
     * Приложение, которое открывает страница /app без ?app=.
     *
     * Самостоятельная страница чата не принадлежит сайту, поэтому приложение
     * выбирается само, если оно единственное активное со входом через сайт
     * (`login_url`). Несколько таких приложений — выбор остаётся за ссылкой
     * с ?app=, иначе чужое приложение предлагало бы войти не туда.
     */
    public static function defaultForLogin(): ?self
    {
        $apps = static::where('is_active', true)->get()->filter(fn (self $app) => $app->setting('login_url'));

        return $apps->count() === 1 ? $apps->first() : null;
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'app_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'app_id');
    }

    /** Origin вида https://host[:port] входит в список разрешённых. Пустой список — браузерам нельзя. */
    public function originAllowed(?string $origin): bool
    {
        if ($origin === null || $origin === '') {
            return false;
        }

        return in_array(rtrim($origin, '/'), array_map(fn ($o) => rtrim($o, '/'), $this->allowed_origins ?? []), true);
    }

    public static function hashServerToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Создаёт app и возвращает секреты ОДИН раз: потом в БД остаётся только хеш серверного токена.
     *
     * @return array{app: self, secret: string, server_token: string}
     */
    public static function register(string $name, array $origins = [], array $settings = []): array
    {
        $secret = 'lws_'.Str::random(48);
        $serverToken = 'lcs_'.Str::random(48);

        $app = self::create([
            'name' => $name,
            'key' => 'lw_pub_'.Str::lower(Str::random(24)),
            'secret' => $secret,
            'server_token_hash' => self::hashServerToken($serverToken),
            'allowed_origins' => array_values(array_unique(array_map(fn ($o) => rtrim($o, '/'), $origins))),
            'settings' => $settings ?: null,
        ]);

        return ['app' => $app, 'secret' => $secret, 'server_token' => $serverToken];
    }
}
