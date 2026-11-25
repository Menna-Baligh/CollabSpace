<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'task_status_histories';

    protected $fillable = [
        'task_id',
        'from_status',
        'to_status',
        'changed_by',
        'is_baseline',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => TaskStatus::class,
            'to_status' => TaskStatus::class,
            'is_baseline' => 'boolean',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
