<?php

namespace Webkul\Admin\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InternalChatRealtimeEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $kind,
        public readonly array $payload,
        public readonly string $eventId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'crm.internal-chat';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'kind' => $this->kind,
            'payload' => $this->payload,
            'emitted_at' => now()->toIso8601String(),
        ];
    }
}
