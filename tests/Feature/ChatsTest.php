<?php

use App\Enums\ConversationType;
use App\Enums\ProjectRole;
use App\Events\CallInitiated;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('user can list their conversations', function () {
    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $response = $this->getJson(route('api.v1.conversations.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'participants', 'unread_count'],
            ],
            'meta',
        ]);
});

test('user can start a direct conversation with another user', function () {
    $response = $this->postJson(route('api.v1.conversations.direct'), [
        'user_id' => $this->otherUser->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'direct');

    $this->assertDatabaseHas('conversations', [
        'type' => 'direct',
    ]);
});

test('user cannot start direct conversation with themselves', function () {
    $response = $this->postJson(route('api.v1.conversations.direct'), [
        'user_id' => $this->user->id,
    ]);

    $response->assertStatus(422);
});

test('project manager can create a project channel', function () {
    $project = Project::factory()->create();
    $project->members()->attach($this->user->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->postJson(route('api.v1.conversations.project-channel'), [
        'project_id' => $project->id,
        'name' => 'General Channel',
        'description' => 'General announcements',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'General Channel');
});

test('non-project member cannot access project channel', function () {
    $project = Project::factory()->create();
    $conv = Conversation::create([
        'type' => ConversationType::PROJECT_CHANNEL,
        'project_id' => $project->id,
        'name' => 'Secret channel',
    ]);

    $response = $this->getJson(route('api.v1.conversations.show', $conv));
    $response->assertStatus(403);
});

test('user can send a message and broadcast event is fired', function () {
    Event::fake([MessageSent::class]);

    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $response = $this->postJson(route('api.v1.conversations.send-message', $conv), [
        'body' => 'Hello there!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.body', 'Hello there!');

    $this->assertDatabaseHas('chat_messages', [
        'conversation_id' => $conv->id,
        'sender_id' => $this->user->id,
        'body' => 'Hello there!',
    ]);

    Event::assertDispatched(MessageSent::class);
});

test('user can upload file attachments with message', function () {
    Storage::fake('local');

    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $response = $this->postJson(route('api.v1.conversations.send-message', $conv), [
        'body' => 'Here is the report',
        'attachments' => [$file],
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('chat_attachments', [
        'file_name' => 'document.pdf',
    ]);
});

test('user can edit their own message', function () {
    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $msg = ChatMessage::create([
        'conversation_id' => $conv->id,
        'sender_id' => $this->user->id,
        'body' => 'Initial text',
    ]);

    $response = $this->patchJson(route('api.v1.conversations.update-message', [$conv, $msg]), [
        'body' => 'Updated text',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.body', 'Updated text');

    $this->assertDatabaseHas('chat_messages', [
        'id' => $msg->id,
        'body' => 'Updated text',
    ]);
});

test('user cannot edit another user message', function () {
    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $msg = ChatMessage::create([
        'conversation_id' => $conv->id,
        'sender_id' => $this->otherUser->id,
        'body' => 'Other text',
    ]);

    $response = $this->patchJson(route('api.v1.conversations.update-message', [$conv, $msg]), [
        'body' => 'Hacked text',
    ]);

    $response->assertStatus(403);
});

test('user can delete their own message', function () {
    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $msg = ChatMessage::create([
        'conversation_id' => $conv->id,
        'sender_id' => $this->user->id,
        'body' => 'To be deleted',
    ]);

    $response = $this->deleteJson(route('api.v1.conversations.delete-message', [$conv, $msg]));

    $response->assertOk();
    $this->assertSoftDeleted('chat_messages', [
        'id' => $msg->id,
    ]);
});

test('user can create thread and add replies', function () {
    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $msg = ChatMessage::create([
        'conversation_id' => $conv->id,
        'sender_id' => $this->user->id,
        'body' => 'Root discussion',
    ]);

    $threadRes = $this->postJson(route('api.v1.conversations.store-thread', $conv), [
        'parent_message_id' => $msg->id,
        'title' => 'Thread Topic 1',
    ]);

    $threadRes->assertCreated()
        ->assertJsonPath('data.title', 'Thread Topic 1');

    $threadId = $threadRes->json('data.id');

    $replyRes = $this->postJson(route('api.v1.threads.add-reply', $threadId), [
        'body' => 'First reply in thread',
    ]);

    $replyRes->assertCreated()
        ->assertJsonPath('data.body', 'First reply in thread');
});

test('user can initiate call in conversation', function () {
    Event::fake([CallInitiated::class]);

    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $response = $this->postJson(route('api.v1.conversations.calls.initiate', $conv), [
        'type' => 'video',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'call' => ['id', 'room_name', 'status', 'type'],
                'join_url',
            ],
        ]);

    Event::assertDispatched(CallInitiated::class);
});

test('user can edit and delete their own thread reply, but not others', function () {
    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $msg = ChatMessage::create([
        'conversation_id' => $conv->id,
        'sender_id' => $this->user->id,
        'body' => 'Root',
    ]);

    $thread = $msg->thread()->create([
        'conversation_id' => $conv->id,
        'title' => 'Topic',
        'created_by' => $this->user->id,
    ]);

    $myReply = $thread->replies()->create([
        'sender_id' => $this->user->id,
        'body' => 'My reply',
    ]);

    $otherReply = $thread->replies()->create([
        'sender_id' => $this->otherUser->id,
        'body' => 'Other reply',
    ]);

    // Edit own reply
    $this->patchJson(route('api.v1.threads.update-reply', [$thread, $myReply]), [
        'body' => 'Edited reply',
    ])->assertOk()->assertJsonPath('data.body', 'Edited reply');

    // Cannot edit other's reply
    $this->patchJson(route('api.v1.threads.update-reply', [$thread, $otherReply]), [
        'body' => 'Hacked',
    ])->assertStatus(403);

    // Delete own reply
    $this->deleteJson(route('api.v1.threads.delete-reply', [$thread, $myReply]))
        ->assertOk();

    $this->assertSoftDeleted('chat_thread_replies', ['id' => $myReply->id]);
});

test('user can broadcast typing indicator in authorized conversation', function () {
    Event::fake([UserTyping::class]);

    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $this->postJson(route('api.v1.conversations.typing', $conv), [
        'is_typing' => true,
    ])->assertOk();

    Event::assertDispatched(UserTyping::class);
});

test('unauthorized user cannot download private chat attachment', function () {
    Storage::fake('local');

    $conv = Conversation::create([
        'type' => ConversationType::DIRECT,
        'created_by' => $this->user->id,
    ]);
    $conv->participants()->attach([$this->user->id, $this->otherUser->id]);

    $file = UploadedFile::fake()->create('secret.pdf', 100, 'application/pdf');
    $res = $this->postJson(route('api.v1.conversations.send-message', $conv), [
        'attachments' => [$file],
    ])->assertCreated();

    $attachmentId = $res->json('data.attachments.0.id');

    // Authorized user can download
    $this->get(route('api.v1.chat-attachments.download', $attachmentId))
        ->assertOk();

    // Outsider cannot download
    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);
    $this->getJson(route('api.v1.chat-attachments.download', $attachmentId))
        ->assertStatus(403);
});
