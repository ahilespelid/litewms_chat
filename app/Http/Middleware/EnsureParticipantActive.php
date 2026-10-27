<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Заблокированный участник работать не может, даже с ещё живым токеном; заодно ведём «был в сети». */
class EnsureParticipantActive
{
    public function handle(Request $request, Closure $next)
    {
        $participant = $request->user();

        if ($participant?->isBlocked() || ! $participant?->app?->is_active) {
            $participant?->tokens()->delete();
            abort(403, 'Доступ закрыт.');
        }

        if (! $participant->last_seen_at || $participant->last_seen_at->lt(now()->subMinute())) {
            $participant->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
