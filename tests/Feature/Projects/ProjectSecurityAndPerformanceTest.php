<?php

use App\Enums\ProjectRole;
use App\Enums\SystemRole;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

test('cross-project ID manipulation is rejected on team and member endpoints', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project1 = Project::factory()->active()->create();
    $project1->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $project2 = Project::factory()->active()->create();
    $team2 = ProjectTeam::factory()->create(['project_id' => $project2->id]);
    $user2 = User::factory()->create();

    // Attempt to add member to team2 via project1 route URL
    $response = $this->postJson("/api/v1/projects/{$project1->id}/teams/{$team2->id}/members", [
        'user_id' => $user2->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'team_not_in_project');
});

test('users cannot elevate system roles or inject unauthorized fields during project creation', function () {
    $user = User::factory()->create(['system_role' => SystemRole::USER]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/projects', [
        'name' => 'Attempted Privilege Escalation',
        'system_role' => 'admin',
        'is_admin' => true,
        'user_id' => 9999,
        'role' => 'superadmin',
    ]);

    $response->assertCreated();

    // Authenticated user's system_role must remain unchanged
    expect($user->fresh()->system_role)->toBe(SystemRole::USER);
});

test('suspended users cannot access project endpoints with existing tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('active_token')->plainTextToken;

    // Verify token works initially
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/projects')
        ->assertOk();

    // Now suspend the user account
    $user->update(['suspended_at' => now()]);

    $this->app['auth']->forgetGuards();

    // Attempting to access project endpoints with old token is rejected by active.user middleware
    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/projects');

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'account_suspended');
});

test('project listing query execution count is bounded to prevent N+1 queries', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Create 10 projects, each with multiple members
    for ($i = 0; $i < 10; $i++) {
        $p = Project::factory()->active()->create(['name' => "Batch Project {$i}"]);
        $members = User::factory()->count(3)->create();
        foreach ($members as $m) {
            $p->members()->attach($m->id, ['role' => ProjectRole::MEMBER->value]);
        }
    }

    DB::enableQueryLog();

    $response = $this->getJson('/api/v1/projects?per_page=10');
    $response->assertOk();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Count should be bounded (count query + projects query + members eager loading query)
    // Regardless of 10 or 50 projects, query count should be under 5
    expect(count($queries))->toBeLessThanOrEqual(5);
});
