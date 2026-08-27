<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkItem extends Model
{
    public const DISCIPLINES = [
        'web' => 'Web Development',
        'telegram_bot' => 'Telegram Bot',
        'python' => 'Python',
        'design' => 'Design',
        'development' => 'Development',
        'three_d' => '3D',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'new' => 'New',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'review' => 'Review',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_channel_sync_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (in_array($user->role, [UserRole::Owner, UserRole::Admin], true)) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('project.teamMembers', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return self::query()->visibleTo($user)->whereKey($this->id)->exists();
    }
}
