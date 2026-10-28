<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Message;
use App\Models\MessageShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @tags Ссылки на сообщения
 */
class ShareController extends ApiController
{
    /**
     * Ссылка на моё сообщение
     *
     * Текущие настройки ссылки или `data: null`, если сообщением ещё не делились.
     * Делиться можно только своими неудалёнными сообщениями.
     */
    public function show(Request $request, string $conversation, int $message): JsonResponse
    {
        $m = $this->ownMessage($request, $conversation, $message);

        return response()->json(['data' => $m->share ? $this->payload($m->share) : null]);
    }

    /**
     * Поделиться сообщением
     *
     * Создаёт ссылку (`url`) или меняет её настройки, адрес при этом не меняется. Доступ (`access`):
     * `members` — участники диалога, `app` — любой вошедший в чат этого приложения, `anyone` — все, у кого есть ссылка.
     * `expires_in` — срок в секундах (3600, 86400, 604800, 2592000; null — без срока). `password` — необязательный пароль
     * (от 4 символов), `clear_password` — снять пароль.
     */
    public function update(Request $request, string $conversation, int $message): JsonResponse
    {
        $m = $this->ownMessage($request, $conversation, $message);

        $data = $request->validate([
            'access' => ['required', Rule::in(MessageShare::ACCESS)],
            'expires_in' => ['sometimes', 'nullable', 'integer', Rule::in(MessageShare::TTL)],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:64'],
            'clear_password' => ['sometimes', 'boolean'],
        ]);

        $share = $m->share ?: new MessageShare([
            'message_id' => $m->id,
            'token' => Str::lower(Str::random(40)),
            'created_by' => $this->viewer($request)->id,
        ]);
        $created = ! $share->exists;

        $share->access = $data['access'];
        if (array_key_exists('expires_in', $data)) {
            $share->expires_at = $data['expires_in'] ? now()->addSeconds($data['expires_in']) : null;
        }
        if (! empty($data['password'])) {
            $share->password_hash = Hash::make($data['password']);
        } elseif (! empty($data['clear_password'])) {
            $share->password_hash = null;
        }
        $share->save();

        return response()->json(['data' => $this->payload($share)], $created ? 201 : 200);
    }

    /**
     * Отключить ссылку
     *
     * Ссылка перестаёт работать сразу; новая ссылка на то же сообщение будет с другим адресом.
     */
    public function destroy(Request $request, string $conversation, int $message): JsonResponse
    {
        $this->ownMessage($request, $conversation, $message)->share?->delete();

        return response()->json(['message' => 'ok']);
    }

    private function payload(MessageShare $share): array
    {
        return [
            'url' => $share->url(),
            'access' => $share->access,
            'has_password' => $share->password_hash !== null,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'expired' => $share->isExpired(),
            'views' => (int) $share->views,
            'created_at' => $share->created_at->toIso8601String(),
        ];
    }

    private function ownMessage(Request $request, string $conversation, int $message): Message
    {
        $c = $this->conversation($request, $conversation);
        $m = Message::with('share')->where('conversation_id', $c->id)
            ->when($c->hidden_from_message_id, fn ($q, $from) => $q->where('id', '>', $from))->findOrFail($message);

        abort_unless($m->participant_id === $this->viewer($request)->id, 403, 'Делиться можно только своими сообщениями.');
        abort_if($m->deleted_at || $m->type === Message::TYPE_SYSTEM, 422, 'Этим сообщением поделиться нельзя.');

        return $m;
    }
}
