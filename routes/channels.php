<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
/* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
Broadcast::channel('internal-chat.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['user']]);

Broadcast::channel('internal-chat.conversation.{conversationId}', function ($user, $conversationId) {
    return DB::table('internal_conversation_members')
        ->where('conversation_id', (int) $conversationId)
        ->where('user_id', (int) $user->id)
        ->exists();
}, ['guards' => ['user']]);
