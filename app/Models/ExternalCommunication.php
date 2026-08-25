<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCommunication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function request()
    {
        return $this->belongsTo(SupportRequest::class, 'support_request_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
