<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;

class GlobalSearchService
{
    private const PER_CATEGORY_LIMIT = 5;

    /**
     * Search across accessible Projects, Tasks, Project Files, and Meetings.
     *
     * @return array<string, mixed>
     */
    public function search(User $user, string $query): array
    {
        $term = trim($query);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $projects = Project::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get(['id', 'name', 'status'])
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'type' => 'project',
                'title' => $p->name,
                'status' => $p->status?->value,
            ])
            ->values()
            ->all();

        $tasks = Task::query()
            ->whereHas('project.members', fn ($q) => $q->where('users.id', $user->id))
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get(['id', 'project_id', 'title', 'status', 'priority'])
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'type' => 'task',
                'project_id' => $t->project_id,
                'title' => $t->title,
                'status' => $t->status?->value,
                'priority' => $t->priority?->value,
            ])
            ->values()
            ->all();

        $files = ProjectFile::query()
            ->whereHas('project.members', fn ($q) => $q->where('users.id', $user->id))
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('original_name', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get(['id', 'project_id', 'name', 'extension'])
            ->map(fn (ProjectFile $f) => [
                'id' => $f->id,
                'type' => 'file',
                'project_id' => $f->project_id,
                'title' => $f->name,
                'extension' => $f->extension,
                'download_url' => url("/api/v1/files/{$f->id}/download"),
            ])
            ->values()
            ->all();

        $meetings = Meeting::query()
            ->forUser($user)
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->orderByDesc('start_time')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get(['id', 'title', 'start_time', 'status'])
            ->map(fn (Meeting $m) => [
                'id' => $m->id,
                'type' => 'meeting',
                'title' => $m->title,
                'start_time' => $m->start_time?->toIso8601String(),
                'status' => $m->status?->value,
            ])
            ->values()
            ->all();

        return [
            'projects' => $projects,
            'tasks' => $tasks,
            'files' => $files,
            'meetings' => $meetings,
            'total_count' => count($projects) + count($tasks) + count($files) + count($meetings),
        ];
    }
}
