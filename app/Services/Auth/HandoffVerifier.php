<?php

namespace App\Services\Auth;

use App\Exceptions\HandoffException;
use App\Models\ChatApp;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Проверка короткоживущего подписанного токена, который бэкенд сайта-хозяина (например ЛК litewms)
 * выдаёт уже вошедшему пользователю. Чат паролей хозяина не знает: доверие — только подпись общего секрета.
 *
 * Требования к токену: HS256; iss = публичный ключ app; sub = id пользователя у хозяина;
 * exp обязателен и не дальше max_age от iat; jti одноразовый.
 */
class HandoffVerifier
{
    private const LEEWAY = 30;

    /**
     * @return array{app: ChatApp, claims: object}
     */
    public function verify(string $jwt): array
    {
        $iss = $this->issuerOf($jwt);
        $app = ChatApp::where('key', $iss)->where('is_active', true)->first();

        if (! $app) {
            throw new HandoffException('Неизвестное приложение.');
        }

        JWT::$leeway = self::LEEWAY;

        try {
            $claims = JWT::decode($jwt, new Key($app->secret, 'HS256'));
        } catch (Throwable $e) {
            throw new HandoffException('Токен недействителен: '.$e->getMessage());
        }

        foreach (['sub', 'exp', 'jti'] as $required) {
            if (! isset($claims->{$required}) || $claims->{$required} === '') {
                throw new HandoffException("В токене нет поля {$required}.");
            }
        }

        $maxAge = (int) config('chat.handoff_max_age');
        $issuedAt = (int) ($claims->iat ?? 0);

        if ($issuedAt > 0 && ((int) $claims->exp - $issuedAt) > $maxAge) {
            throw new HandoffException('Срок жизни токена слишком большой.');
        }

        if (((int) $claims->exp - time()) > $maxAge + self::LEEWAY) {
            throw new HandoffException('Срок жизни токена слишком большой.');
        }

        // Один токен — один вход: перехваченный в логах/истории токен повторно не сработает.
        $ttl = max(60, (int) $claims->exp - time() + self::LEEWAY * 2);
        if (! Cache::add('handoff:'.$app->id.':'.hash('sha256', (string) $claims->jti), 1, $ttl)) {
            throw new HandoffException('Токен уже использован.');
        }

        return ['app' => $app, 'claims' => $claims];
    }

    /** Достаёт iss из ещё непроверенного токена — только чтобы найти секрет нужной app. */
    private function issuerOf(string $jwt): string
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new HandoffException('Это не JWT.');
        }

        $payload = json_decode(JWT::urlsafeB64Decode($parts[1]) ?: '', true);

        if (! is_array($payload) || empty($payload['iss']) || ! is_string($payload['iss'])) {
            throw new HandoffException('В токене нет iss.');
        }

        return $payload['iss'];
    }
}
