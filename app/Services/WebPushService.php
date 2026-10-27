<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /** @param  iterable<PushSubscription>  $subscriptions */
    public function sendToMany(iterable $subscriptions, string $title, string $body, string $url): void
    {
        $public = config('services.webpush.public_key');
        $private = config('services.webpush.private_key');

        if (! $public || ! $private) {
            Log::info('Web push: VAPID-ключи не заданы, уведомление пропущено.');

            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => $public,
            'privateKey' => $private,
        ]]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_UNICODE);
        $queued = false;

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
            $queued = true;
        }

        if (! $queued) {
            return;
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess() && in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
            }
        }
    }
}
