<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Notifications\Files\ProjectFileUploadedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProjectFileService
{
    /**
     * The storage disk to use.
     */
    protected string $disk = 'public';

    /**
     * List recent files (latest 3 files uploaded to the project).
     *
     * @return Collection<int, ProjectFile>
     */
    public function getRecentFiles(Project $project): Collection
    {
        return ProjectFile::where('project_id', $project->id)
            ->with('uploader:id,name')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(3)
            ->get();
    }

    /**
     * List paginated files with search.
     *
     * @param  array{search?: ?string}  $filters
     */
    public function listAllFiles(Project $project, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProjectFile::where('project_id', $project->id)
            ->with('uploader:id,name');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Upload and store a project file safely.
     *
     * @param  array{name?: ?string}  $data
     */
    public function uploadFile(Project $project, User $uploader, UploadedFile $uploadedFile, array $data = []): ProjectFile
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        $originalFilename = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: 'bin';
        $mimeType = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
        $sizeBytes = $uploadedFile->getSize();

        // Default display name: original filename without its extension
        $defaultName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $displayName = ! empty($data['name']) ? trim($data['name']) : $defaultName;

        // Generate safe, unique storage filename
        $safeFileName = Str::random(40).'.'.$extension;
        $directory = "projects/{$project->id}/files";
        $storedPath = $uploadedFile->storeAs($directory, $safeFileName, $this->disk);

        if (! $storedPath) {
            throw new \RuntimeException('Failed to store physical file.');
        }

        try {
            return DB::transaction(function () use ($project, $uploader, $displayName, $originalFilename, $storedPath, $mimeType, $extension, $sizeBytes) {
                $file = ProjectFile::create([
                    'project_id' => $project->id,
                    'uploaded_by' => $uploader->id,
                    'name' => $displayName,
                    'original_name' => $originalFilename,
                    'file_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'size_bytes' => $sizeBytes,
                ]);

                DB::afterCommit(function () use ($project, $file, $uploader) {
                    $members = $project->members()->where('users.id', '!=', $uploader->id)->get();
                    foreach ($members as $member) {
                        $member->notify(new ProjectFileUploadedNotification($member, $file, $project, $uploader));
                    }
                });

                return $file->load('uploader:id,name');
            });
        } catch (\Throwable $e) {
            // Cleanup orphaned file if DB persistence failed
            Storage::disk($this->disk)->delete($storedPath);

            throw $e;
        }
    }

    /**
     * Verify and prepare file download.
     *
     * @return array{path: string, original_name: string, mime_type: string}
     */
    public function getFileForDownload(ProjectFile $file): array
    {
        if (! Storage::disk($this->disk)->exists($file->file_path)) {
            throw new InvalidArgumentException(__('projects.file_not_found_on_disk'));
        }

        $fullPath = Storage::disk($this->disk)->path($file->file_path);

        return [
            'path' => $fullPath,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
        ];
    }

    /**
     * Soft delete a project file and safely cleanup physical storage.
     */
    public function deleteFile(ProjectFile $file): bool
    {
        if ($file->project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        return DB::transaction(function () use ($file) {
            $deleted = $file->delete();

            // Safe cleanup of physical file
            try {
                if (Storage::disk($this->disk)->exists($file->file_path)) {
                    Storage::disk($this->disk)->delete($file->file_path);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to delete physical file from storage', [
                    'file_id' => $file->id,
                    'file_path' => $file->file_path,
                    'exception' => $e->getMessage(),
                ]);
            }

            return (bool) $deleted;
        });
    }
}
