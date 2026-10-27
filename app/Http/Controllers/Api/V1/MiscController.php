<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ParticipantResource;
use App\Models\DeviceToken;
use App\Models\Participant;
use App\Models\PushSubscription;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * @tags Прочее
 */
class MiscController extends ApiController
{
    /**
     * Сколько непрочитанного
     *
     * Для бейджа «Чат (3)» в меню сайта: `total` — сообщений, `conversations` — диалогов с непрочитанным.
     */
    public function unread(Request $request, ConversationService $conversations): JsonResponse
    {
        return response()->json(['data' => $conversations->unreadTotals($this->viewer($request))]);
    }

    /**
     * Поиск людей приложения
     *
     * Работает, только если сайт-хозяин включил `directory`. Иначе — пустой список.
     */
    public function participants(Request $request): AnonymousResourceCollection
    {
        $me = $this->viewer($request);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $people = ! $me->app->setting('directory') ? collect() : Participant::where('app_id', $me->app_id)
            ->whereNull('blocked_at')->whereKeyNot($me->id)->where('kind', 'member')
            ->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $data['q']).'%')
            ->orderBy('name')->limit(20)->get();

        return ParticipantResource::collection($people);
    }

    /**
     * Подписать браузер на push (Web Push / VAPID)
     *
     * Поля берутся из `PushSubscription.toJSON()`: `endpoint` и `keys.p256dh` / `keys.auth`.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'participant_id' => $this->viewer($request)->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
        ]);

        return response()->json(['message' => 'ok'], 201);
    }

    /** Отписать браузер от push */
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:2000']]);

        PushSubscription::where('participant_id', $this->viewer($request)->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Зарегистрировать устройство мобильного приложения
     *
     * Токен FCM/APNs. Отправка на устройства подключается вместе с мобильным приложением.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'token' => ['required', 'string', 'max:4096'],
        ]);

        DeviceToken::updateOrCreate(['token_hash' => hash('sha256', $data['token'])], [
            'participant_id' => $this->viewer($request)->id,
            'platform' => $data['platform'],
            'token' => $data['token'],
        ]);

        return response()->json(['message' => 'ok'], 201);
    }

    /** Забыть устройство мобильного приложения */
    public function forgetDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);

        DeviceToken::where('participant_id', $this->viewer($request)->id)->where('token_hash', hash('sha256', $data['token']))->delete();

        return response()->json(['message' => 'ok']);
    }
}
