<?php

use Illuminate\Broadcasting\PrivateChannel;
use Webkul\Admin\Events\InternalChatRealtimeEvent;

it('uses a private channel and stable event name', function () {
    $event = new InternalChatRealtimeEvent(
        'internal-chat.conversation.12',
        'conversation.changed',
        ['conversation_id' => 12],
        'event-123',
    );

    expect($event->broadcastAs())->toBe('crm.internal-chat')
        ->and($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($event->broadcastOn()[0]->name)->toBe('private-internal-chat.conversation.12')
        ->and($event->broadcastWith()['event_id'])->toBe('event-123')
        ->and($event->broadcastWith()['payload']['conversation_id'])->toBe(12);
});
