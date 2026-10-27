<?php

namespace App\Services\Auth;

use App\Models\Participant;

class TokenIssuer
{
    /**
     * Выдаёт рабочий Bearer-токен. Лишние старые токены отзываются: у человека ограниченное число живых сессий.
     *
     * @return array{token: string, expires_at: string}
     */
    public function issue(Participant $participant, string $device = 'web'): array
    {
        $keep = max(1, (int) config('chat.max_tokens_per_participant') - 1);
        $old = $participant->tokens()->orderByDesc('id')->skip($keep)->take(1000)->pluck('id');
        if ($old->isNotEmpty()) {
            $participant->tokens()->whereIn('id', $old)->delete();
        }

        $expires = now()->addDays((int) config('chat.token_ttl_days'));
        $plain = $participant->createToken($device, ['*'], $expires)->plainTextToken;

        return ['token' => $plain, 'expires_at' => $expires->toIso8601String()];
    }
}
