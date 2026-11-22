<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProjectMemberResource extends JsonResource
{
    /**
     * Optional is_leader flag override.
     */
    protected ?bool $isLeaderOverride;

    public function __construct($resource, ?bool $isLeader = null)
    {
        parent::__construct($resource);
        $this->isLeaderOverride = $isLeader;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->resource->getAttribute('role') ?? $this->resource->role ?? $this->pivot?->role ?? 'member';

        $isLeader = $this->isLeaderOverride
            ?? (bool) ($this->resource->getAttribute('is_leader') ?? $this->resource->is_leader ?? false);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => null,
            'role' => $role,
            'is_leader' => $isLeader,
        ];
    }
}
