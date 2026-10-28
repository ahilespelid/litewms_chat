<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Свои маршруты документации (/docs, /docs/openapi.json) — стандартные Scramble не нужны.
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Вход: перебор токенов/ссылок ограничиваем по IP.
        RateLimiter::for('chat-auth', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        // Обычные запросы клиента — по участнику (фронт дёргает API часто: список, история, read).
        // Перебор паролей к ссылке «Поделиться»: считается по адресу и ссылке.
        RateLimiter::for('chat-share', fn (Request $r) => Limit::perMinute(20)->by($r->ip().'|'.$r->route('token')));

        RateLimiter::for('chat-api', fn (Request $r) => Limit::perMinute(600)->by($r->user()?->id ?: $r->ip()));
        // Отправка сообщений и файлов.
        RateLimiter::for('chat-send', fn (Request $r) => Limit::perMinute(90)->by($r->user()?->id ?: $r->ip()));
        // Бэкенд сайта-хозяина.
        RateLimiter::for('chat-server', fn (Request $r) => Limit::perMinute(1200)->by($r->bearerToken() ? hash('sha256', $r->bearerToken()) : $r->ip()));

        $this->documentApi();
    }

    /** OpenAPI-описание генерируется из кода роутов, валидации и ресурсов — оно не может устареть молча. */
    private function documentApi(): void
    {
        Scramble::configure()
            ->routes(fn (Route $route) => Str::startsWith($route->uri(), 'api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->info->title = 'LiteWMS Chat API';
                $openApi->info->setDescription(file_get_contents(resource_path('docs/api-overview.md')));

                $openApi->components->addSecurityScheme('participant', SecurityScheme::http('bearer')->as('participant')
                    ->setDescription('Рабочий токен участника: `data.token` из `POST /auth/exchange`, `/auth/guest` или `/auth/link`.'));
                $openApi->components->addSecurityScheme('server', SecurityScheme::http('bearer')->as('server')
                    ->setDescription('Серверный токен приложения (`lcs_…`). Хранится только на бэкенде сайта-хозяина.'));
            })
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
                $uri = $routeInfo->route->uri();

                // Scramble добавляет тег по имени контроллера (Auth, Message…) рядом с нашим @tags:
                // оставляем только наш, иначе каждый метод показывается в двух группах.
                $own = array_values(array_filter($operation->tags, fn ($t) => preg_match('/[^\x00-\x7F]/', $t)));
                $operation->tags = $own ?: ['Прочее'];

                if (str_contains($uri, '/server/')) {
                    $operation->security = [new SecurityRequirement(['server' => []])];
                } elseif (in_array('auth:sanctum', $routeInfo->route->gatherMiddleware(), true)) {
                    $operation->security = [new SecurityRequirement(['participant' => []])];
                } else {
                    $operation->security = [];
                }
            });
    }
}
