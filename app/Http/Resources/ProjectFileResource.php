<?php

namespace App\Http\Resources;

use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectFile
 */
class ProjectFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $uploaderData = null;
        if ($this->uploader) {
            $uploaderData = [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'formatted_size' => $this->formatted_size,
            'uploaded_by' => $uploaderData,
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => url("/api/v1/projects/{$this->project_id}/files/{$this->id}/download"),
        ];
    }
}
