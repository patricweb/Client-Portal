<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTemplateStage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean'];
    }

    public function workflowTemplate()
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }
}
