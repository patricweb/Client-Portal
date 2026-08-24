<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBrief extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function template()
    {
        return $this->belongsTo(BriefTemplate::class, 'brief_template_id');
    }

    public function answers()
    {
        return $this->hasMany(BriefAnswer::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
