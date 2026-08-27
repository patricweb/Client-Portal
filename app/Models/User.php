<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PermissionService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['company_id', 'name', 'email', 'password', 'role', 'status', 'must_change_password', 'last_login_at', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function hasPermission(string $permission): bool
    {
        return app(PermissionService::class)->allows($this, $permission);
    }

    public function assignedProjects()
    {
        return $this->belongsToMany(Project::class)->withPivot('assigned_by')->withTimestamps();
    }

    public function assignedWorkItems()
    {
        return $this->hasMany(WorkItem::class, 'assigned_to');
    }

    public function createdWorkItems()
    {
        return $this->hasMany(WorkItem::class, 'created_by');
    }

    public function canAccessProject(Project $project): bool
    {
        return $this->role === UserRole::Owner
            || $this->role === UserRole::Admin
            || $this->assignedProjects()->whereKey($project->id)->exists();
    }
}
