<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function carePlan()
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
