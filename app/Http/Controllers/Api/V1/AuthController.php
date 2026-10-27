<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\HandoffException;
use App\Http\Resources\ParticipantResource;
use App\Models\ChatApp;
use App\Models\Conversation;
use App\Models\Participant;
use App\Services\Auth\HandoffVerifier;
use App\Services\Auth\TokenIssuer;
use App\Services\ConversationService;
use App\Services\ParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Авторизация
 */
class AuthController extends ApiController
{
    /**
     * Обмен handoff-токена на рабочий Bearer-токен
     *
     * Бэкенд сайта-хозяина (например ЛК) подписывает JWT (HS256, секрет приложения) для уже вошедшего у себя
     * пользователя: `iss` — публичный ключ приложения, `sub` — id пользователя у хозяина, `exp` — не позже
     * чем через 10 минут, `jti` — одноразовый идентификатор; необязательно `name`, `email`, `avatar`, `role`,
     * `operator` (true — видит все диалоги поддержки), `meta`. Токен принимается один раз.
     */
    public function exchange(Request $request, HandoffVerifier $verifier, ParticipantService $participants, TokenIssuer $tokens): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);

        try {
            ['app' => $app, 'claims' => $claims] = $verifier->verify($data['token']);
        } catch (HandoffException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $this->assertOrigin($request, $app);

        $participant = $participants->upsert($app, (string) $claims->sub, [
            'name' => $claims->name ?? null,
            'email' => $claims->email ?? null,
            'avatar_url' => $claims->avatar ?? null,
            'role' => $claims->role ?? null,
            'is_operator' => (bool) ($claims->operator ?? false),
            'meta' => isset($claims->meta) ? (array) $claims->meta : null,
        ]);

        abort_if($participant->isBlocked(), 403, 'Доступ закрыт.');

        return $this->session($participant, $tokens, 'web');
    }

    /**
     * Анонимный гость (если включено для приложения)
     *
     * Создаёт гостя и его диалог поддержки. В ответе — рабочий токен и личная ссылка-секрет `link_secret`:
     * по ней гость вернётся в тот же диалог (см. «Вход по личной ссылке»).
     */
    public function guest(Request $request, TokenIssuer $tokens, ConversationService $conversations): JsonResponse
    {
        $data = $request->validate([
            'app_key' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        $app = ChatApp::where('key', $data['app_key'])->where('is_active', true)->first();
        abort_unless($app, 404, 'Приложение не найдено.');
        abort_unless($app->setting('guest_enabled'), 403, 'Гостевой режим выключен.');
        $this->assertOrigin($request, $app);

        $guest = Participant::create([
            'app_id' => $app->id,
            'kind' => 'guest',
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
        ]);
        $secret = $guest->issueLinkSecret();

        [$conversation] = $conversations->create($app, Conversation::TYPE_SUPPORT, [$guest], $guest);

        return $this->session($guest, $tokens, 'guest', ['link_secret' => $secret, 'conversation_id' => $conversation->id], 201);
    }

    /**
     * Вход по личной ссылке
     *
     * Гость или оператор, созданный командой `chat:operator`, обменивает секрет из своей ссылки на рабочий токен.
     */
    public function link(Request $request, TokenIssuer $tokens): JsonResponse
    {
        $data = $request->validate(['secret' => ['required', 'string', 'max:128']]);

        $participant = Participant::where('link_secret_hash', hash('sha256', $data['secret']))->first();
        abort_unless($participant && ! $participant->isBlocked() && $participant->app->is_active, 401, 'Ссылка недействительна.');
        $this->assertOrigin($request, $participant->app);

        return $this->session($participant, $tokens, 'link');
    }

    /** Кто я */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    /** Выход: текущий токен отзывается */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'ok']);
    }

    private function session(Participant $participant, TokenIssuer $tokens, string $device, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $tokens->issue($participant, $device) + ['token_type' => 'Bearer'] + $this->payload($participant) + $extra], $status);
    }

    /** @return array{participant: array, app: array} */
    private function payload(Participant $participant): array
    {
        $app = $participant->app;

        return [
            'participant' => ParticipantResource::make($participant)->resolve(),
            'app' => [
                'key' => $app->key,
                'name' => $app->name,
                'title' => $app->setting('title'),
                'accent' => $app->setting('accent'),
                'support_enabled' => (bool) $app->setting('support_enabled'),
                'user_conversations' => (bool) $app->setting('user_conversations'),
                'directory' => (bool) $app->setting('directory'),
            ],
        ];
    }

    /** Браузерные запросы допускаются только с сайтов из списка app; серверные (без Origin) — всегда. */
    private function assertOrigin(Request $request, ChatApp $app): void
    {
        $origin = $request->headers->get('Origin');

        if ($origin === null || $origin === rtrim((string) config('app.url'), '/')) {
            // Без Origin — не браузер (сервер, мобильное приложение). С Origin == сам чат — это страница
            // /embed, которая внутри iframe; её родителя проверяет frame-ancestors и postMessage-рукопожатие.
            return;
        }

        abort_unless($app->originAllowed($origin), 403, 'Этот сайт не подключён к чату.');
    }
}
