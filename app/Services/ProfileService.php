<?php

namespace App\Services;

use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    /**
     * Storage disk for profile avatars.
     */
    protected string $disk = 'public';

    /**
     * Get tasks assigned to user grouped by todo vs done.
     *
     * @return array{
     *     todo: array{count: int, data: Collection<int, Task>},
     *     done: array{count: int, data: Collection<int, Task>}
     * }
     */
    public function getUserTasks(User $user): array
    {
        $baseQuery = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });

        $todoTasks = (clone $baseQuery)
            ->whereIn('status', ['todo', 'in_progress', 'in_review'])
            ->orderBy('id', 'desc')
            ->get();

        $doneTasks = (clone $baseQuery)
            ->where('status', 'completed')
            ->orderBy('id', 'desc')
            ->get();

        return [
            'todo' => [
                'count' => $todoTasks->count(),
                'data' => $todoTasks,
            ],
            'done' => [
                'count' => $doneTasks->count(),
                'data' => $doneTasks,
            ],
        ];
    }

    /**
     * Get paginated files relevant to the user:
     * Either uploaded by the user or located in projects the user belongs to.
     */
    public function getUserFiles(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $projectIds = $user->projects()->pluck('projects.id')->toArray();

        return ProjectFile::query()
            ->with('uploader:id,name')
            ->where(function ($q) use ($user, $projectIds) {
                $q->where('uploaded_by', $user->id);
                if (! empty($projectIds)) {
                    $q->orWhereIn('project_id', $projectIds);
                }
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Resolve the deterministic current team & project label for the user.
     * E.g. "Design Team — CollabSpace" or "CollabSpace" or null.
     */
    public function getCurrentTeamProject(User $user): ?string
    {
        // 1. Check latest team membership
        $latestTeam = $user->projectTeams()
            ->with('project:id,name')
            ->orderBy('project_team_user.created_at', 'desc')
            ->first();

        if ($latestTeam && $latestTeam->project) {
            return "{$latestTeam->name} — {$latestTeam->project->name}";
        }

        // 2. Check latest project membership
        $latestProject = $user->projects()
            ->orderBy('project_user.created_at', 'desc')
            ->first();

        if ($latestProject) {
            return $latestProject->name;
        }

        return null;
    }

    /**
     * Update user profile settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(User $user, array $data): User
    {
        $allowed = [
            'name',
            'email',
            'phone',
            'availability_status',
            'job_title',
            'years_of_experience',
            'bio',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        if (! empty($payload)) {
            $user->update($payload);
        }

        return $user->fresh();
    }

    /**
     * Upload and update user profile avatar safely.
     */
    public function updateAvatar(User $user, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg';
        $filename = Str::random(40).'.'.$extension;
        $directory = "avatars/{$user->id}";

        $storedPath = $file->storeAs($directory, $filename, $this->disk);

        if (! $storedPath) {
            throw new \RuntimeException('Failed to store avatar file.');
        }

        $oldAvatar = $user->avatar;

        try {
            DB::transaction(function () use ($user, $storedPath) {
                $user->update(['avatar' => $storedPath]);
            });
        } catch (\Throwable $e) {
            Storage::disk($this->disk)->delete($storedPath);
            throw $e;
        }

        // Safely delete previous avatar only after database update succeeds
        if ($oldAvatar && Storage::disk($this->disk)->exists($oldAvatar)) {
            try {
                Storage::disk($this->disk)->delete($oldAvatar);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old avatar', [
                    'user_id' => $user->id,
                    'old_avatar' => $oldAvatar,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return Storage::disk($this->disk)->url($storedPath);
    }
}
