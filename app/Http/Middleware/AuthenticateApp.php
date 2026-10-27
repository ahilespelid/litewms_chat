<?php

namespace App\Http\Middleware;

use App\Models\ChatApp;
use Closure;
use Illuminate\Http\Request;

/** Серверный API: бэкенд сайта-хозяина представляется своим серверным токеном (Bearer lcs_…). */
class AuthenticateApp
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        $app = $token && str_starts_with($token, 'lcs_')
            ? ChatApp::where('server_token_hash', ChatApp::hashServerToken($token))->where('is_active', true)->first()
            : null;

        abort_unless($app, 401, 'Нужен серверный токен приложения.');

        $request->attributes->set('chat_app', $app);

        return $next($request);
    }
}
