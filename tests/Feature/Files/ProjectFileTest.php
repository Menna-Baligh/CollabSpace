<?php

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->pm = User::factory()->create();
    $this->member = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->project = Project::factory()->create([
        'status' => ProjectStatus::ACTIVE,
    ]);

    $this->project->members()->attach($this->pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $this->project->members()->attach($this->member->id, ['role' => ProjectRole::MEMBER->value]);
});

test('unauthenticated user receives 401 on files endpoints', function () {
    $this->getJson("/api/v1/projects/{$this->project->id}/files")
        ->assertStatus(401);
});

test('authenticated active user can list project files with recent_files and paginated all_files', function () {
    for ($i = 1; $i <= 5; $i++) {
        ProjectFile::create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
            'name' => "Document {$i}",
            'original_name' => "doc_{$i}.pdf",
            'file_path' => "projects/{$this->project->id}/files/doc_{$i}.pdf",
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024 * $i,
            'created_at' => now()->addMinutes($i),
        ]);
    }

    $response = $this->actingAs($this->outsider)
        ->getJson("/api/v1/projects/{$this->project->id}/files?per_page=2")
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'recent_files' => [
                    '*' => [
                        'id',
                        'name',
                        'original_name',
                        'extension',
                        'mime_type',
                        'size_bytes',
                        'formatted_size',
                        'uploaded_by' => ['id', 'name'],
                        'created_at',
                        'download_url',
                    ],
                ],
                'all_files' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('data.recent_files'))->toHaveCount(3)
        ->and($response->json('data.all_files'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(5);
});

test('file search works across display name and original filename', function () {
    ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->pm->id,
        'name' => 'Architecture Blueprint',
        'original_name' => 'blueprint_v1.pdf',
        'file_path' => "projects/{$this->project->id}/files/f1.pdf",
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 1024,
    ]);

    ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->pm->id,
        'name' => 'Financial Report',
        'original_name' => 'report_q4.xlsx',
        'file_path' => "projects/{$this->project->id}/files/f2.xlsx",
        'mime_type' => 'application/vnd.ms-excel',
        'extension' => 'xlsx',
        'size_bytes' => 2048,
    ]);

    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/files?search=Blueprint")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.all_files')
        ->assertJsonPath('data.all_files.0.name', 'Architecture Blueprint');
});

test('file upload succeeds with custom display name', function () {
    $uploadedFile = UploadedFile::fake()->create('project_doc.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->member)
        ->postJson("/api/v1/projects/{$this->project->id}/files", [
            'file' => $uploadedFile,
            'name' => 'My Custom Specification',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'My Custom Specification')
        ->assertJsonPath('data.original_name', 'project_doc.pdf')
        ->assertJsonPath('data.extension', 'pdf')
        ->assertJsonPath('data.uploaded_by.id', $this->member->id);

    $fileId = $response->json('data.id');
    $fileRecord = ProjectFile::find($fileId);

    expect($fileRecord)->not->toBeNull()
        ->and($fileRecord->name)->toBe('My Custom Specification')
        ->and(Storage::disk('public')->exists($fileRecord->file_path))->toBeTrue();
});

test('file upload defaults to original filename without extension when name is omitted', function () {
    $uploadedFile = UploadedFile::fake()->create('annual_financial_audit.docx', 1200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $this->actingAs($this->member)
        ->postJson("/api/v1/projects/{$this->project->id}/files", [
            'file' => $uploadedFile,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'annual_financial_audit')
        ->assertJsonPath('data.original_name', 'annual_financial_audit.docx');
});

test('uploading file over 20 MB fails validation with 422', function () {
    // 25 MB file (25600 KB)
    $largeFile = UploadedFile::fake()->create('large_video.mp4', 25600, 'video/mp4');

    $this->actingAs($this->member)
        ->postJson("/api/v1/projects/{$this->project->id}/files", [
            'file' => $largeFile,
        ])
        ->assertStatus(422);
});

test('uploading file to archived project fails with 422', function () {
    $this->project->update(['status' => ProjectStatus::ARCHIVED, 'archived_at' => now()]);

    $uploadedFile = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->actingAs($this->pm)
        ->postJson("/api/v1/projects/{$this->project->id}/files", [
            'file' => $uploadedFile,
        ])
        ->assertStatus(422);
});

test('file download succeeds with original filename and headers', function () {
    $content = 'Sample confidential project document content';
    $filePath = "projects/{$this->project->id}/files/random_hash.pdf";
    Storage::disk('public')->put($filePath, $content);

    $file = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->member->id,
        'name' => 'Project Specification',
        'original_name' => 'Real_Specification.pdf',
        'file_path' => $filePath,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => strlen($content),
    ]);

    $response = $this->actingAs($this->outsider)
        ->get("/api/v1/projects/{$this->project->id}/files/{$file->id}/download")
        ->assertStatus(200);

    $response->assertHeader('content-disposition', 'attachment; filename=Real_Specification.pdf');
});

test('download rejects a file belonging to another project with 404', function () {
    $otherProject = Project::factory()->create();
    $file = ProjectFile::create([
        'project_id' => $otherProject->id,
        'uploaded_by' => $this->member->id,
        'name' => 'Other File',
        'original_name' => 'other.pdf',
        'file_path' => "projects/{$otherProject->id}/files/other.pdf",
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/files/{$file->id}/download")
        ->assertStatus(404);
});

test('downloading file when physical file is missing returns 404', function () {
    $file = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->member->id,
        'name' => 'Missing File',
        'original_name' => 'missing.pdf',
        'file_path' => "projects/{$this->project->id}/files/missing_on_disk.pdf",
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/files/{$file->id}/download")
        ->assertStatus(404);
});

test('file deletion by uploader succeeds', function () {
    $filePath = "projects/{$this->project->id}/files/to_delete.pdf";
    Storage::disk('public')->put($filePath, 'file content');

    $file = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->member->id,
        'name' => 'My File',
        'original_name' => 'my_file.pdf',
        'file_path' => $filePath,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $this->actingAs($this->member)
        ->deleteJson("/api/v1/projects/{$this->project->id}/files/{$file->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('project_files', ['id' => $file->id]);
    expect(Storage::disk('public')->exists($filePath))->toBeFalse();
});

test('file deletion by project manager succeeds', function () {
    $filePath = "projects/{$this->project->id}/files/pm_delete.pdf";
    Storage::disk('public')->put($filePath, 'file content');

    $file = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->member->id,
        'name' => 'Member File',
        'original_name' => 'doc.pdf',
        'file_path' => $filePath,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $this->actingAs($this->pm)
        ->deleteJson("/api/v1/projects/{$this->project->id}/files/{$file->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('project_files', ['id' => $file->id]);
});

test('file deletion by unrelated user fails with 403', function () {
    $file = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->pm->id,
        'name' => 'PM File',
        'original_name' => 'doc.pdf',
        'file_path' => "projects/{$this->project->id}/files/doc.pdf",
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $this->actingAs($this->outsider)
        ->deleteJson("/api/v1/projects/{$this->project->id}/files/{$file->id}")
        ->assertStatus(403);
});

test('file deletion from archived project fails with 422', function () {
    $file = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->pm->id,
        'name' => 'File',
        'original_name' => 'doc.pdf',
        'file_path' => "projects/{$this->project->id}/files/doc.pdf",
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $this->project->update(['status' => ProjectStatus::ARCHIVED, 'archived_at' => now()]);

    $this->actingAs($this->pm)
        ->deleteJson("/api/v1/projects/{$this->project->id}/files/{$file->id}")
        ->assertStatus(422);
});
