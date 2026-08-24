<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefAnswer extends Model
{
    protected $guarded = [];

    public function brief()
    {
        return $this->belongsTo(ProjectBrief::class, 'project_brief_id');
    }

    public function field()
    {
        return $this->belongsTo(BriefTemplateField::class, 'brief_template_field_id');
    }
}
