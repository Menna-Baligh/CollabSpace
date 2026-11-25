<?php

use App\Models\Project;
use App\Models\User;
use App\Notifications\Projects\ProjectMemberAddedNotification;
use Illuminate\Broadcasting\PrivateChannel;

it('uses database and broadcast channels only (no email)', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $notification = new ProjectMemberAddedNotification($user, $project, 'member');

    expect($notification->via($user))->toBe(['database', 'broadcast']);
    expect($notification->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
    expect($notification->broadcastOn()->name)->toBe('private-user.'.$user->id);
    expect($notification->broadcastType())->toBe('notification');
});
