<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function nextNumber(): string
    {
        $year = now()->format('Y');
        $latest = Invoice::where('invoice_number', 'like', "INV-{$year}-%")
            ->lockForUpdate()->orderByDesc('invoice_number')->value('invoice_number');
        $sequence = $latest ? ((int) substr($latest, -5)) + 1 : 1;

        return sprintf('INV-%s-%05d', $year, $sequence);
    }

    public function create(array $data, array $items): Invoice
    {
        return DB::transaction(function () use ($data, $items) {
            $profile = ProviderProfile::current();
            $company = Company::findOrFail($data['company_id']);
            $data['snapshot'] ??= [
                'provider' => $profile->details,
                'company' => $company->only(['id', 'name', 'billing_name', 'billing_address', 'email']),
                'generated_at' => now()->toIso8601String(),
            ];
            $data['payment_instructions'] = filled($data['payment_instructions'] ?? null) ? $data['payment_instructions'] : $profile->paymentInstructions();
            $data['tax_amount'] ??= 0;
            $data['tax_description'] ??= $profile->details['tax_note'] ?? null;
            $invoice = Invoice::create($data + ['invoice_number' => $this->nextNumber(), 'subtotal' => 0, 'total' => 0]);
            $subtotal = 0;
            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $total = round($quantity * (float) $item['unit_price'], 2);
                $subtotal += $total;
                $invoice->items()->create($item + ['quantity' => $quantity, 'total' => $total]);
            }
            $invoice->update(['subtotal' => $subtotal, 'total' => max(0, $subtotal - (float) ($data['discount'] ?? 0)) + (float) $data['tax_amount']]);

            return $invoice;
        });
    }

    public function agreementTotal(Document $agreement): float
    {
        $confirmationIds = Document::where('parent_document_id', $agreement->id)->where('pack_template', 'change_confirmation')->pluck('id');
        $confirmationDecision = Approval::where('approvable_type', Document::class)->whereIn('approvable_id', $confirmationIds)
            ->where('decision', 'approved')->latest('decided_at')->latest('id')->first();
        $confirmationChange = $confirmationDecision ? DocumentVersion::where('document_id', $confirmationDecision->approvable_id)->where('version', $confirmationDecision->version)->first() : null;
        $legacyChange = DocumentVersion::whereNotNull('signed_at')->whereHas('document', fn ($query) => $query->where('parent_document_id', $agreement->id)->where('pack_template', 'change_order'))->latest('signed_at')->latest('id')->first();
        $change = $confirmationChange ?: $legacyChange;

        return (float) ($change?->snapshot['commercial']['price'] ?? $agreement->currentVersionRecord()?->snapshot['commercial']['price'] ?? $agreement->project?->price ?? 0);
    }

    public function assertReady(Invoice $invoice): void
    {
        if ((float) $invoice->total <= 0) {
            throw ValidationException::withMessages(['total' => 'An invoice must have a positive amount.']);
        }
        if ($invoice->snapshot) {
            $profile = new ProviderProfile(['details' => $invoice->snapshot['provider'] ?? []]);
            if ($profile->missing(true)) {
                throw ValidationException::withMessages(['provider' => 'Complete and confirm provider / bank settings, then refresh this invoice draft profile.']);
            }
            if (($profile->details['currency'] ?? '') !== $invoice->currency || blank($invoice->tax_description) || blank($invoice->snapshot['company']['billing_address'] ?? null)) {
                throw ValidationException::withMessages(['invoice' => 'Confirm the account currency, invoice tax treatment and client billing address before sending.']);
            }
        }
        if (! in_array($invoice->kind, ['advance', 'final'])) {
            return;
        }
        $agreement = Document::lockForUpdate()->find($invoice->sow_document_id);
        $validAgreement = $agreement && (($agreement->type === 'project_confirmation' && $agreement->status === 'accepted') || ($agreement->type === 'scope_of_work' && $agreement->status === 'signed'));
        if (! $validAgreement || $agreement->company_id !== $invoice->company_id || $agreement->project_id !== $invoice->project_id) {
            throw ValidationException::withMessages(['sow_document_id' => 'An accepted Project Confirmation for this client and project is required.']);
        }
        $snapshotAgreementVersion = $invoice->snapshot['agreement_version'] ?? $invoice->snapshot['sow_version'] ?? null;
        if ($snapshotAgreementVersion !== $agreement->current_version) {
            throw ValidationException::withMessages(['sow_document_id' => 'The Project Confirmation version changed. Recreate this unissued draft from the current accepted version.']);
        }
        if ($invoice->currency !== ($agreement->currentVersionRecord()?->snapshot['commercial']['currency'] ?? $agreement->project?->currency)) {
            throw ValidationException::withMessages(['currency' => 'Invoice currency must match the Project Confirmation.']);
        }
        if ($invoice->kind === 'final') {
            $acceptance = Document::find($invoice->acceptance_document_id);
            $deliveryVersion = $invoice->snapshot['delivery_version'] ?? $invoice->snapshot['acceptance_version'] ?? null;
            if (! $acceptance || ! in_array($acceptance->type, ['delivery_confirmation', 'delivery_acceptance'], true) || $acceptance->company_id !== $invoice->company_id || $acceptance->parent_document_id !== $agreement->id || ! in_array($acceptance->status, ['accepted', 'accepted_with_minor_items']) || ($acceptance->currentVersionRecord()?->snapshot['parent_version'] ?? null) !== $agreement->current_version || $deliveryVersion !== $acceptance->current_version) {
                throw ValidationException::withMessages(['acceptance_document_id' => 'Final billing requires explicit confirmation of delivery for this Project Confirmation version.']);
            }
        }
        $alreadyInvoiced = (float) Invoice::where('sow_document_id', $agreement->id)->whereNotIn('status', ['draft', 'void'])->whereKeyNot($invoice->id)->sum('total');
        if (round((float) ($invoice->snapshot['project_total'] ?? 0), 2) !== round($this->agreementTotal($agreement), 2)) {
            throw ValidationException::withMessages(['total' => 'The confirmed total changed. Recreate this unissued draft from the current confirmation.']);
        }
        if (round($alreadyInvoiced + (float) $invoice->total, 2) > round($this->agreementTotal($agreement), 2)) {
            throw ValidationException::withMessages(['total' => 'This would exceed the confirmed project total. Prior invoices count even when unpaid; do not rebill the advance.']);
        }
    }
}
