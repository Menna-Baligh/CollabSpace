<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('prevents reading or deleting another users notification (IDOR)', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\Tasks\\TaskAssignedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $owner->id,
        'data' => json_encode(['type' => 'task_assigned', 'title' => 'T', 'body' => 'B']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($attacker, 'sanctum')
        ->patchJson("/api/v1/notifications/{$id}/read")
        ->assertNotFound();

    $this->actingAs($attacker, 'sanctum')
        ->deleteJson("/api/v1/notifications/{$id}")
        ->assertNotFound();

    expect(DB::table('notifications')->where('id', $id)->exists())->toBeTrue();
});

it('blocks suspended users from notification endpoints', function () {
    $suspended = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($suspended, 'sanctum')
        ->getJson('/api/v1/notifications')
        ->assertForbidden();
});
