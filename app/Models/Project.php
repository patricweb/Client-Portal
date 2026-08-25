<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_date' => 'date',
            'target_completion_date' => 'date',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function workflowTemplate()
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    public function stages()
    {
        return $this->hasMany(ProjectStage::class)->orderBy('position');
    }

    public function brief()
    {
        return $this->hasOne(ProjectBrief::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function paymentSchedule()
    {
        return $this->hasOne(PaymentSchedule::class);
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

    public function teamMembers()
    {
        return $this->belongsToMany(User::class)->withPivot('assigned_by')->withTimestamps();
    }

    public function currentStage(): ?ProjectStage
    {
        return $this->stages->first(fn (ProjectStage $stage) => ! in_array($stage->status, ['approved', 'completed'], true));
    }
}
