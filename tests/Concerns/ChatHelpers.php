<?php

namespace Tests\Concerns;

use App\Models\ChatApp;
use App\Models\Participant;
use App\Services\ParticipantService;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

trait ChatHelpers
{
    /** @return array{app: ChatApp, secret: string, server_token: string} */
    protected function makeApp(array $settings = [], array $origins = ['https://test.litewms.ru'], string $name = 'ЛК litewms'): array
    {
        return ChatApp::register($name, $origins, $settings);
    }

    /** Токен, который выдал бы бэкенд сайта-хозяина. */
    protected function handoff(ChatApp $app, string $secret, string $sub, array $claims = [], ?int $ttl = 120): string
    {
        $now = time();

        return JWT::encode(array_merge([
            'iss' => $app->key,
            'sub' => $sub,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => (string) Str::uuid(),
            'name' => 'Пользователь '.$sub,
        ], $claims), $secret, 'HS256');
    }

    /** Создаёт участника напрямую и возвращает с готовым Bearer-токеном. */
    protected function actor(ChatApp $app, string $externalId, array $data = []): array
    {
        $participant = app(ParticipantService::class)->upsert($app, $externalId, $data + ['name' => 'U'.$externalId]);

        return [$participant, ['Authorization' => 'Bearer '.$participant->createToken('test')->plainTextToken]];
    }

    protected function server(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
