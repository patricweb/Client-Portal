<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(string $event, string $description, ?Model $subject = null, string $visibility = 'internal', array $properties = [], ?int $companyId = null, ?int $projectId = null): ActivityLog
    {
        $request = app()->runningInConsole() ? null : request();

        return ActivityLog::create([
            'actor_id' => auth()->id(), 'company_id' => $companyId, 'project_id' => $projectId,
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'event' => $event, 'visibility' => $visibility, 'description' => $description,
            'properties' => $this->sanitize($properties), 'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(), 'created_at' => now(),
        ]);
    }

    private function sanitize(array $properties): array
    {
        return collect($properties)->except([
            'password', 'remember_token', 'token', 'telegram_bot_token', 'internal_notes',
            'content', 'body', 'billing_address',
        ])->map(fn ($value) => is_scalar($value) || $value === null ? $value : '[complex value]')->all();
    }
}
