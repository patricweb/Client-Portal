<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['estimated_budget' => 'decimal:2', 'next_contact_at' => 'date'];
    }
}
