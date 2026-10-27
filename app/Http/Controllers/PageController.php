<?php

namespace App\Http\Controllers;

use App\Models\ChatApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** HTML-страницы: полный чат, чат для iframe, документация. Вся логика — в REST API. */
class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
    }

    /** Полная страница чата (для мобильной ссылки «Открыть чат» и операторов). */
    public function app(Request $request)
    {
        return $this->chat($request, 'page', null);
    }

    /** Вход по личной ссылке гостя или оператора. */
    public function link(Request $request, string $secret)
    {
        return $this->chat($request, 'page', null, ['linkSecret' => $secret]);
    }

    /** Страница для iframe. Встраивать её могут только сайты из списка приложения (CSP frame-ancestors). */
    public function embed(Request $request)
    {
        $app = ChatApp::where('key', (string) $request->query('app'))->where('is_active', true)->first();
        abort_unless($app, 404, 'Приложение не найдено.');

        return $this->chat($request, 'embed', $app);
    }

    public function docs()
    {
        return view('pages.docs');
    }

    private function chat(Request $request, string $mode, ?ChatApp $app, array $extra = [])
    {
        $origins = $app?->allowed_origins ?? [];

        // Самостоятельная страница: приложение по ?app= или единственное со входом через сайт.
        $login_app = $mode === 'page' ? ($this->activeApp($request->query('app')) ?? ChatApp::defaultForLogin()) : null;

        $config = [
            'mode' => $mode,
            'appKey' => $app?->key ?? $login_app?->key ?? $this->plainKey($request->query('app')),
            // Куда отправить человека за токеном входа (null — кнопки «Войти через сайт» нет).
            'loginUrl' => $login_app?->setting('login_url'),
            'allowedOrigins' => $origins,
            'view' => $request->query('view') === 'thread' ? 'thread' : 'full',
            'accent' => preg_match('/^#[0-9a-f]{6}$/i', (string) $request->query('accent')) ? $request->query('accent') : null,
            'context' => $this->context($request),
            'realtime' => [
                'key' => config('reverb.apps.apps.0.key'),
                'host' => env('REVERB_CLIENT_HOST', $request->getHost()),
                'port' => (int) env('REVERB_CLIENT_PORT', $request->isSecure() ? 443 : 80),
                'scheme' => env('REVERB_CLIENT_SCHEME', $request->isSecure() ? 'https' : 'http'),
            ],
            'vapid' => config('services.webpush.public_key'),
        ] + $extra;

        $response = response()->view('chat', ['config' => $config, 'title' => $app?->setting('title') ?? 'Чат']);

        // Кто может показывать эту страницу в iframe: только сайты приложения; полная страница — никто.
        $ancestors = $mode === 'embed' && $origins ? "'self' ".implode(' ', $origins) : "'none'";
        $response->headers->set('Content-Security-Policy', "frame-ancestors $ancestors");
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** Активное приложение по публичному ключу или null. */
    private function activeApp(mixed $key): ?ChatApp
    {
        $key = $this->plainKey($key);

        return $key ? ChatApp::where('key', $key)->where('is_active', true)->first() : null;
    }

    private function plainKey(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^lw_pub_[a-z0-9]{10,40}$/', $value) ? $value : null;
    }

    /** ?context=request:123&context_title=…&context_url=… */
    private function context(Request $request): ?array
    {
        $raw = (string) $request->query('context');

        if (! preg_match('/^([a-z0-9_.-]{1,64}):(.{1,191})$/i', $raw, $m)) {
            return null;
        }

        return array_filter([
            'type' => $m[1],
            'id' => $m[2],
            'title' => mb_substr((string) $request->query('context_title'), 0, 255) ?: null,
            'url' => mb_substr((string) $request->query('context_url'), 0, 500) ?: null,
        ], fn ($v) => $v !== null);
    }
}
