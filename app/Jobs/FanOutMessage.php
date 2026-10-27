<?php

namespace App\Jobs;

use App\Events\ChatEvent;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Services\ConversationService;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

/**
 * Рассылает событие о сообщении всем участникам диалога (включая другие устройства отправителя)
 * и отправляет push тем, кому писали. Вне запроса, чтобы отправка сообщения отвечала мгновенно.
 */
class FanOutMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  string  $event  message.created | message.updated */
    public function __construct(public int $messageId, public string $event)
    {
    }

    public function handle(ConversationService $conversations, WebPushService $webPush): void
    {
        $message = Message::with(['conversation', 'sender', 'replyTo.sender'])->find($this->messageId);

        if (! $message) {
            return;
        }

        $request = Request::create('/');
        $messagePayload = MessageResource::make($message)->resolve($request);
        $members = ConversationMember::with('participant')
            ->where('conversation_id', $message->conversation_id)->whereNull('left_at')->get();

        foreach ($members as $member) {
            $viewer = $member->participant;
            $conversation = $conversations->findFor($viewer, $message->conversation_id);

            broadcast(new ChatEvent('participant.'.$viewer->id, $this->event, [
                'message' => $messagePayload,
                'conversation' => $conversation ? ConversationResource::make($conversation)->forViewer($viewer)->resolve($request) : null,
            ]));
        }

        if ($this->event !== 'message.created') {
            return;
        }

        $recipients = $members->reject(fn ($m) => $m->participant_id === $message->participant_id || $m->isMuted());
        $subscriptions = PushSubscription::whereIn('participant_id', $recipients->pluck('participant_id'))->get();

        if ($subscriptions->isNotEmpty()) {
            $title = $message->sender?->name ?: ($message->conversation->context_title ?: 'Новое сообщение');
            $webPush->sendToMany($subscriptions, $title, $message->preview(120), '/app?c='.$message->conversation_id);
        }
    }
}
