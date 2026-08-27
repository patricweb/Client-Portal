<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PortalPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(): View
    {
        Invoice::whereNotIn('status', ['draft', 'paid', 'void'])->whereDate('due_date', '<', today())->update(['status' => 'overdue']);

        return view('owner.invoices.index', ['invoices' => Invoice::with(['company', 'project'])->latest()->paginate(25)]);
    }

    public function create(Request $request, InvoiceService $service): View
    {
        $selectedSow = Document::where(fn ($query) => $query->where(fn ($legacy) => $legacy->where('type', 'scope_of_work')->where('status', 'signed'))
            ->orWhere(fn ($current) => $current->where('type', 'project_confirmation')->where('status', 'accepted')))->find($request->input('sow_document_id'));
        $kind = in_array($request->input('kind'), ['advance', 'final']) ? $request->input('kind') : 'standard';
        $previouslyInvoiced = $selectedSow ? (float) Invoice::where('sow_document_id', $selectedSow->id)->where('status', '!=', 'void')->sum('total') : 0;
        $suggestedAmount = $selectedSow ? ($kind === 'advance' ? $service->agreementTotal($selectedSow) / 2 : max(0, $service->agreementTotal($selectedSow) - $previouslyInvoiced)) : null;

        return view('owner.invoices.create', [
            'companies' => Company::orderBy('name')->get(),
            'projects' => Project::with('company')->orderBy('name')->get(),
            'sows' => Document::with('company')->where(fn ($query) => $query->where(fn ($legacy) => $legacy->where('type', 'scope_of_work')->where('status', 'signed'))
                ->orWhere(fn ($current) => $current->where('type', 'project_confirmation')->where('status', 'accepted')))->get(),
            'acceptances' => Document::whereIn('type', ['delivery_confirmation', 'delivery_acceptance'])->whereIn('status', ['accepted', 'accepted_with_minor_items'])->get(),
            'profile' => ProviderProfile::current(), 'selectedSow' => $selectedSow, 'kind' => $kind, 'suggestedAmount' => $suggestedAmount,
        ]);
    }

    public function store(Request $request, InvoiceService $service): RedirectResponse
    {
        $request->merge(['items' => collect($request->input('items', []))->filter(fn ($item) => filled($item['description'] ?? null))->values()->all()]);
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'], 'project_id' => ['nullable', 'exists:projects,id'],
            'issue_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'MDL'])], 'discount' => ['nullable', 'numeric', 'min:0'],
            'kind' => ['nullable', 'in:standard,advance,final'],
            'sow_document_id' => ['nullable', 'required_if:kind,advance,final', 'exists:documents,id'],
            'acceptance_document_id' => ['nullable', 'required_if:kind,final', 'exists:documents,id'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'], 'tax_description' => ['nullable', 'string', 'max:2000'],
            'payment_instructions' => ['nullable', 'string'], 'public_notes' => ['nullable', 'string'], 'internal_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'], 'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $project = isset($data['project_id']) ? Project::findOrFail($data['project_id']) : null;
        abort_if($project && $project->company_id !== (int) $data['company_id'], 422, 'Project does not belong to company.');
        $sow = isset($data['sow_document_id']) ? Document::findOrFail($data['sow_document_id']) : null;
        if ($sow) {
            abort_unless(in_array($sow->type, ['project_confirmation', 'scope_of_work'], true) && in_array($sow->status, ['accepted', 'signed'], true) && $sow->company_id === (int) $data['company_id'] && $sow->project_id === $project?->id, 422, 'Project Confirmation does not match this client / project.');
        }
        $acceptance = isset($data['acceptance_document_id']) ? Document::findOrFail($data['acceptance_document_id']) : null;
        abort_if($acceptance && (! in_array($acceptance->type, ['delivery_confirmation', 'delivery_acceptance'], true) || $acceptance->parent_document_id !== $sow?->id), 422, 'Delivery Confirmation must refer to the selected Project Confirmation.');
        if ($sow) {
            $data['snapshot'] = [
                'provider' => ProviderProfile::current()->details,
                'company' => Company::findOrFail($data['company_id'])->only(['id', 'name', 'billing_name', 'billing_address', 'email']),
                'sow_number' => $sow->document_number, 'sow_version' => $sow->current_version,
                'agreement_number' => $sow->document_number, 'agreement_version' => $sow->current_version,
                'project_total' => $service->agreementTotal($sow), 'acceptance_number' => $acceptance?->document_number,
                'acceptance_version' => $acceptance?->current_version,
                'delivery_number' => $acceptance?->document_number, 'delivery_version' => $acceptance?->current_version,
            ];
        }
        $items = $data['items'];
        unset($data['items']);
        $invoice = $service->create($data + ['status' => 'draft', 'discount' => $data['discount'] ?? 0], $items);

        return redirect()->route('owner.invoices.show', $invoice)->with('success', 'Invoice draft created.');
    }

    public function show(Invoice $invoice): View
    {
        return view('owner.invoices.show', ['invoice' => $invoice->load(['company', 'project', 'items', 'payments.recorder'])]);
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === 'draft', 422);
        DB::transaction(function () use ($invoice, $request) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->status === 'draft', 422);
            abort_if($request->filled('snapshot_hash') && ! hash_equals(hash('sha256', json_encode($invoice->snapshot)), (string) $request->input('snapshot_hash')), 409, 'The invoice details changed. Review the current draft before sending.');
            app(InvoiceService::class)->assertReady($invoice);
            $invoice->update(['status' => 'sent', 'sent_at' => now()]);
            app(PortalPdfService::class)->invoice($invoice, true);
        });
        app(NotificationService::class)->send(
            User::where('company_id', $invoice->company_id)->get(), 'invoice_sent', 'action_required',
            'New invoice', "{$invoice->invoice_number} is due {$invoice->due_date->format('M j, Y')}", route('client.billing.show', $invoice), false
        );

        return back()->with('success', 'Invoice sent to client.');
    }

    public function payment(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->status === 'void', 422);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$invoice->remainingAmount()],
            'paid_at' => ['required', 'date'], 'payment_method' => ['required', 'string', 'max:100'],
            'transaction_reference' => ['nullable', 'string', 'max:255'], 'internal_note' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($invoice, $data, $request) {
            $invoice->payments()->create($data + ['recorded_by' => $request->user()->id]);
            $invoice->refreshPaymentStatus();
        });
        app(NotificationService::class)->send(
            User::where('company_id', $invoice->company_id)->get(), 'payment_confirmed', 'important_update',
            'Payment confirmed', "A payment was recorded for {$invoice->invoice_number}.", route('client.billing.show', $invoice), false
        );

        return back()->with('success', 'Payment recorded.');
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->status === 'paid', 422, 'A paid invoice cannot be voided.');
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $invoice->update(['status' => 'void', 'voided_at' => now(), 'internal_notes' => trim($invoice->internal_notes."\nVoid reason: ".$request->input('reason'))]);

        return back()->with('success', 'Invoice voided.');
    }

    public function pdf(Invoice $invoice): Response
    {
        $invoice->load(['company', 'project', 'items', 'payments']);

        return response(app(PortalPdfService::class)->invoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    public function refreshProfile(Invoice $invoice): RedirectResponse
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->status === 'draft', 422);
            $profile = ProviderProfile::current();
            $invoice->update([
                'snapshot' => array_replace($invoice->snapshot ?? [], ['provider' => $profile->details, 'company' => $invoice->company->only(['id', 'name', 'billing_name', 'billing_address', 'email'])]),
                'payment_instructions' => $profile->paymentInstructions(),
                'tax_description' => $invoice->tax_description ?: ($profile->details['tax_note'] ?? null),
            ]);
        });

        return back()->with('success', 'Draft provider / billing details refreshed. Issued invoices are unchanged.');
    }
}
