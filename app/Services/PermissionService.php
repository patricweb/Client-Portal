<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

class PermissionService
{
    private const MATRIX = [
        'admin' => ['manage_leads', 'manage_clients', 'manage_projects', 'manage_documents', 'manage_billing', 'manage_care', 'manage_requests', 'manage_work_items', 'view_work_item_financials', 'view_activity', 'manage_team'],
        'project_manager' => ['manage_clients', 'manage_projects', 'manage_documents', 'manage_requests', 'manage_work_items', 'view_work_item_financials', 'view_activity'],
        'developer' => ['manage_projects', 'manage_work_items', 'view_activity'],
        'support' => ['manage_care', 'manage_requests', 'manage_work_items', 'view_activity'],
        'accountant' => ['manage_billing', 'view_work_item_financials', 'view_activity'],
        'client' => ['client_portal'],
    ];

    public function allows(User $user, string $permission): bool
    {
        if ($user->role === UserRole::Owner) {
            return true;
        }

        return in_array($permission, self::MATRIX[$user->role->value] ?? [], true);
    }
}
