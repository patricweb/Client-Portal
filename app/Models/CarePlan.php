<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class CarePlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2', 'additional_hourly_rate' => 'decimal:2',
            'included_services' => 'array', 'start_date' => 'date', 'next_billing_date' => 'date',
            'last_backup_at' => 'datetime', 'last_maintenance_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function activities()
    {
        return $this->hasMany(CareActivity::class)->orderByDesc('occurred_at');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function billingAmount(): float
    {
        return (float) $this->monthly_price * match ($this->billing_frequency) {
            'quarterly' => 3, 'yearly' => 12, default => 1,
        };
    }

    public function nextBillingDateAfterCurrent(): CarbonInterface
    {
        return match ($this->billing_frequency) {
            'quarterly' => $this->next_billing_date->copy()->addMonthsNoOverflow(3),
            'yearly' => $this->next_billing_date->copy()->addYearNoOverflow(),
            default => $this->next_billing_date->copy()->addMonthNoOverflow(),
        };
    }
}
