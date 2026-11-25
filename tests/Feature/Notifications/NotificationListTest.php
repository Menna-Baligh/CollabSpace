<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createDbNotification(User $user, array $overrides = []): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert(array_merge([
        'id' => $id,
        'type' => 'App\\Notifications\\Tasks\\TaskAssignedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'type' => 'task_assigned',
            'title' => 'Task Assigned',
            'body' => 'You have been assigned to a task.',
            'url' => '/projects/1/tasks/1',
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

it('requires authentication to list notifications', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});

it('lists paginated notifications for the authenticated user only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    createDbNotification($user);
    createDbNotification($user, ['read_at' => now()]);
    createDbNotification($other);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications')
        ->assertOk();

    expect($response->json('data.data'))->toHaveCount(2);
    expect($response->json('data.meta.per_page'))->toBe(20);
});

it('filters unread notifications', function () {
    $user = User::factory()->create();
    createDbNotification($user);
    createDbNotification($user, ['read_at' => now()]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications?filter=unread')
        ->assertOk();

    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.read'))->toBeFalse();
});

it('caps per_page at 50', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications?per_page=100')
        ->assertUnprocessable();
});

it('returns unread notification count', function () {
    $user = User::factory()->create();
    createDbNotification($user);
    createDbNotification($user);
    createDbNotification($user, ['read_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 2);
});
