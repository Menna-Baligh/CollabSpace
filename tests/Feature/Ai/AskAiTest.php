<?php

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Jobs\ProcessAiAttachmentJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.gemini.api_key', 'test-gemini-key');
    Config::set('services.gemini.model', 'gemini-2.5-flash');
});

it('rejects unauthenticated requests to ai endpoints', function () {
    $this->postJson('/api/v1/ai/chat', ['message' => 'Hello'])->assertUnauthorized();
    $this->getJson('/api/v1/ai/conversations')->assertUnauthorized();
});

it('validates that either message or attachment is provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ai/chat', [])
        ->assertUnprocessable();
});

it('creates a new conversation and stores user and assistant messages for text-only chat', function () {
    $user = User::factory()->create();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'REST APIs use HTTP methods to manipulate resources.'],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 15,
                'candidatesTokenCount' => 12,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ai/chat', [
            'message' => 'Explain REST APIs https://example.com/rest',
        ])
        ->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'conversation' => ['id', 'title', 'last_message_at'],
            'user_message' => ['id', 'role', 'content', 'link_preview'],
            'assistant_message' => ['id', 'role', 'content', 'status'],
        ],
    ]);

    expect($response->json('data.assistant_message.status'))->toBe(AiMessageStatus::COMPLETED->value);
    expect($response->json('data.user_message.link_preview.domain'))->toBe('example.com');
});

it('prevents accessing or continuing another users conversation', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $conversation = AiConversation::create([
        'user_id' => $otherUser->id,
        'title' => 'Private Chat',
        'last_message_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/ai/conversations/{$conversation->id}")
        ->assertForbidden();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ai/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Trying to hijack',
        ])
        ->assertForbidden();
});

it('marks assistant message as failed when gemini api key is missing or provider fails', function () {
    $user = User::factory()->create();
    Config::set('services.gemini.api_key', null);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['message' => 'Hello'])
        ->assertOk()
        ->assertJsonPath('data.assistant_message.status', AiMessageStatus::FAILED->value);

    Config::set('services.gemini.api_key', 'test-key');
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Server error'], 500),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['message' => 'Hello'])
        ->assertOk()
        ->assertJsonPath('data.assistant_message.status', AiMessageStatus::FAILED->value);
});

it('dispatches async job for image and pdf attachments and rejects invalid mime types', function () {
    Queue::fake();
    Storage::fake('private');
    $user = User::factory()->create();

    $badFile = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['attachment' => $badFile])
        ->assertUnprocessable();

    $image = UploadedFile::fake()->image('diagram.png');
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ai/chat', [
            'message' => 'Analyze this image',
            'attachment' => $image,
        ])
        ->assertStatus(202)
        ->assertJsonPath('data.user_message.status', AiMessageStatus::PROCESSING->value);

    Queue::assertPushed(ProcessAiAttachmentJob::class);
});

it('supports feedback, regeneration, and soft deleting conversations', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'My Chat',
        'last_message_at' => now(),
    ]);

    $userMsg = AiMessage::create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::USER,
        'content' => 'What is Agile?',
        'status' => AiMessageStatus::COMPLETED,
    ]);

    $assistantMsg = AiMessage::create([
        'ai_conversation_id' => $conversation->id,
        'parent_message_id' => $userMsg->id,
        'role' => AiMessageRole::ASSISTANT,
        'content' => 'Agile is an iterative methodology.',
        'status' => AiMessageStatus::COMPLETED,
    ]);

    // Feedback on assistant message
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/ai/messages/{$assistantMsg->id}/feedback", ['feedback' => 'like'])
        ->assertOk()
        ->assertJsonPath('data.feedback', 'like');

    // Feedback on user message rejected
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/ai/messages/{$userMsg->id}/feedback", ['feedback' => 'like'])
        ->assertUnprocessable();

    // Regenerate
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Agile focuses on iterative delivery and collaboration.']]]],
            ],
        ], 200),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/ai/messages/{$assistantMsg->id}/regenerate")
        ->assertOk()
        ->assertJsonPath('data.content', 'Agile focuses on iterative delivery and collaboration.');

    // Soft delete conversation
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/ai/conversations/{$conversation->id}")
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/ai/conversations/{$conversation->id}")
        ->assertNotFound();
});
