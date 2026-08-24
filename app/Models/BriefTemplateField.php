<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefTemplateField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean'];
    }

    public function template()
    {
        return $this->belongsTo(BriefTemplate::class, 'brief_template_id');
    }
}
