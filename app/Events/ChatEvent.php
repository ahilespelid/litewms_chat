<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Единственное событие реального времени. Все клиенты (веб, iframe, будущее мобильное приложение)
 * слушают свой приватный канал participant.{id} и получают события вида:
 *   message.created | message.updated | message.read | conversation.updated | conversation.removed
 * Сообщение «печатает…» идёт по presence-каналу conversation.{id}.
 */
class ChatEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $channel,
        public string $name,
        public array $payload,
        public bool $presence = false,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return $this->presence ? new PresenceChannel($this->channel) : new PrivateChannel($this->channel);
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
