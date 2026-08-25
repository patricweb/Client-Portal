<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentScheduleItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'due_date' => 'date'];
    }

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }

    public function stage()
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function amount(): float
    {
        $price = (float) $this->schedule->project->price;

        return $this->amount_type === 'percentage' ? round($price * (float) $this->value / 100, 2) : (float) $this->value;
    }
}
