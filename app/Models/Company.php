<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function contacts()
    {
        return $this->hasMany(CompanyContact::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function carePlans()
    {
        return $this->hasMany(CarePlan::class);
    }

    public function requests()
    {
        return $this->hasMany(SupportRequest::class);
    }

    public function workItems()
    {
        return $this->hasMany(WorkItem::class);
    }
}
