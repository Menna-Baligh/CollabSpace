<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('marks a single notification as read idempotently', function () {
    $user = User::factory()->create();
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\Tasks\\TaskAssignedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['type' => 'task_assigned', 'title' => 'T', 'body' => 'B']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.read', true);

    // Idempotent second call
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.read', true);
});

it('marks all unread notifications as read', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Tasks\\TaskAssignedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['type' => 'task_assigned', 'title' => 'T', 'body' => 'B']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.marked_count', 3);

    expect($user->unreadNotifications()->count())->toBe(0);
});
