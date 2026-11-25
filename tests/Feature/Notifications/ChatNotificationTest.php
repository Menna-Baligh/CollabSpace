<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\Chats\NewChannelMessageNotification;
use App\Notifications\Chats\NewDirectMessageNotification;
use App\Services\ChatMessageService;
use Illuminate\Support\Facades\Notification;

it('notifies unread recipients and excludes sender', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $sender->id,
    ]);

    $conversation->participants()->attach([
        $sender->id => ['last_read_message_id' => 0],
        $recipient->id => ['last_read_message_id' => 0],
    ]);

    $service = app(ChatMessageService::class);
    $service->sendMessage($conversation, $sender, 'Hello there!');

    Notification::assertNotSentTo($sender, NewDirectMessageNotification::class);
    Notification::assertSentTo($recipient, NewDirectMessageNotification::class);
});

it('sends channel notification for project channel messages', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $member = User::factory()->create();

    $conversation = Conversation::create([
        'type' => ConversationType::PROJECT_CHANNEL,
        'name' => 'general',
        'created_by' => $sender->id,
    ]);

    $conversation->participants()->attach([
        $sender->id => ['last_read_message_id' => 0],
        $member->id => ['last_read_message_id' => 0],
    ]);

    $service = app(ChatMessageService::class);
    $service->sendMessage($conversation, $sender, 'Channel update');

    Notification::assertNotSentTo($sender, NewChannelMessageNotification::class);
    Notification::assertSentTo($member, NewChannelMessageNotification::class);
});
