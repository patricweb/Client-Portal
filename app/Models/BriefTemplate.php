<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function fields()
    {
        return $this->hasMany(BriefTemplateField::class)->orderBy('position');
    }
}
