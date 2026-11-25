<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('deletes a single notification owned by the user', function () {
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
        ->deleteJson("/api/v1/notifications/{$id}")
        ->assertOk();

    expect(DB::table('notifications')->where('id', $id)->exists())->toBeFalse();
});

it('deletes all notifications for the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach ([$user, $user, $other] as $target) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Tasks\\TaskAssignedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $target->id,
            'data' => json_encode(['type' => 'task_assigned', 'title' => 'T', 'body' => 'B']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 2);

    expect($other->notifications()->count())->toBe(1);
});
