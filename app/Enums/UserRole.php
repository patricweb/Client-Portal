<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case ProjectManager = 'project_manager';
    case Developer = 'developer';
    case Support = 'support';
    case Accountant = 'accountant';
    case Client = 'client';

    public function isStaff(): bool
    {
        return $this !== self::Client;
    }
}
