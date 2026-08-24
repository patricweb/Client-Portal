<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stages()
    {
        return $this->hasMany(WorkflowTemplateStage::class)->orderBy('position');
    }
}
