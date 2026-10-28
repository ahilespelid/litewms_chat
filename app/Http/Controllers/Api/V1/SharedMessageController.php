<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\ConversationMember;
use App\Models\MessageShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @tags Ссылки на сообщения
 */
class SharedMessageController extends Controller
{
    /**
     * Открыть сообщение по ссылке
     *
     * Без токена (`Authorization`) открывается только ссылка с доступом `anyone`. Ответы с полем `code`:
     * `auth_required` (401, нужно войти в чат), `forbidden` (403, нет доступа), `password_required` и `wrong_password` (403),
     * `expired` (410, срок истёк), `not_found` (404, ссылка отключена или сообщение удалено).
     * Файл и голосовое отдаются подписанными ссылками на несколько минут.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $share = MessageShare::where('token', $token)->with(['message.sender', 'message.conversation'])->first();
        $m = $share?->message;

        if (! $share || ! $m || $m->deleted_at || ! $m->conversation) {
            return $this->fail('not_found', 'Ссылка не работает: её отключили или сообщение удалено.', 404);
        }

        $viewer = $request->user('sanctum');
        $viewer = $viewer && ! $viewer->isBlocked() ? $viewer : null;
        $owner = $viewer && $viewer->id === $m->participant_id;

        if ($share->isExpired() && ! $owner) {
            return $this->fail('expired', 'Срок действия ссылки истёк.', 410);
        }

        $member = $viewer && ConversationMember::where('conversation_id', $m->conversation_id)
            ->where('participant_id', $viewer->id)->whereNull('left_at')->exists();

        if ($share->access !== 'anyone' && ! $owner) {
            if (! $viewer) {
                return $this->fail('auth_required', 'Чтобы открыть сообщение, войдите в чат.', 401);
            }
            if ($viewer->app_id !== $m->conversation->app_id || ($share->access === 'members' && ! $member)) {
                return $this->fail('forbidden', 'У вас нет доступа к этому сообщению.', 403);
            }
        }

        if ($share->password_hash && ! $owner) {
            $password = (string) $request->input('password', '');
            if ($password === '') {
                return $this->fail('password_required', 'Сообщение защищено паролем.', 403);
            }
            if (! Hash::check($password, $share->password_hash)) {
                return $this->fail('wrong_password', 'Неверный пароль.', 403);
            }
        }

        if (! $owner) {
            $share->increment('views');
        }

        $res = MessageResource::make($m)->resolve($request);

        return response()->json(['data' => [
            'type' => $res['type'],
            'body' => $res['body'],
            'edited' => $res['edited_at'] !== null,
            'forwarded_from' => $res['forwarded_from'],
            'sender' => ['name' => $m->sender?->name ?? 'Система', 'role' => $m->sender?->role],
            'voice' => $res['voice'],
            'file' => $res['file'],
            'created_at' => $res['created_at'],
            'access' => $share->access,
            'expires_at' => $share->expires_at?->toIso8601String(),
            /** Только участникам диалога: по нему можно открыть сообщение в чате. */
            'conversation_id' => $member || $owner ? $m->conversation_id : null,
        ]])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status)->header('Cache-Control', 'no-store');
    }
}
