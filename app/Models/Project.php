<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id',
        'name',
        'client_name',
        'description',
        'status',
        'deadline',
        'hourly_rate',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Recompute status from task completion, unless the project was
     * manually archived — archiving is the only status a user sets
     * that the automatic recalculation must not override.
     */
    public function recalculateStatus(): void
    {
        if ($this->status === self::STATUS_ARCHIVED) {
            return;
        }

        $totalTasks = $this->tasks()->count();
        $newStatus = $totalTasks > 0 && $this->tasks()->where('status', '!=', 'completed')->doesntExist()
            ? self::STATUS_COMPLETED
            : self::STATUS_ACTIVE;

        if ($newStatus !== $this->status) {
            $this->update(['status' => $newStatus]);
        }
    }
}
