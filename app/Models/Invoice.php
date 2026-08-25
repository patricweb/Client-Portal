<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2',
            'discount' => 'decimal:2', 'total' => 'decimal:2', 'sent_at' => 'datetime',
            'viewed_at' => 'datetime', 'voided_at' => 'datetime',
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

    public function carePlan()
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->total - $this->paidAmount());
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->status === 'void') {
            return;
        }
        $paid = $this->paidAmount();
        $status = $paid >= (float) $this->total && (float) $this->total > 0
            ? 'paid'
            : ($paid > 0 ? 'partially_paid' : ($this->due_date->isPast() && $this->status !== 'draft' ? 'overdue' : $this->status));
        $this->update(['status' => $status]);
    }
}
