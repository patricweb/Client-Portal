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
            'snapshot' => 'array', 'tax_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $invoice) {
            if (($invoice->getOriginal('sent_at') || $invoice->getOriginal('pdf_path')) && $invoice->isDirty(['company_id', 'project_id', 'invoice_number', 'issue_date', 'due_date', 'currency', 'subtotal', 'discount', 'tax_amount', 'tax_description', 'total', 'payment_instructions', 'public_notes', 'snapshot', 'kind', 'sow_document_id', 'acceptance_document_id'])) {
                throw new \LogicException('An issued invoice cannot be rewritten. Use a documented correction.');
            }
        });
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

    public function paymentDescription(): string
    {
        $agreementNumber = data_get($this->snapshot, 'agreement_number')
            ?: data_get($this->snapshot, 'sow_number');

        return 'Payment for software development services under Invoice '.$this->invoice_number
            .($agreementNumber ? ' and Agreement '.$agreementNumber : '')
            .'.';
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
