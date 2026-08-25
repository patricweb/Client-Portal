<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use Dompdf\Dompdf;
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

    public function create(): View
    {
        return view('owner.invoices.create', [
            'companies' => Company::orderBy('name')->get(),
            'projects' => Project::with('company')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, InvoiceService $service): RedirectResponse
    {
        $request->merge(['items' => collect($request->input('items', []))->filter(fn ($item) => filled($item['description'] ?? null))->values()->all()]);
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'], 'project_id' => ['nullable', 'exists:projects,id'],
            'issue_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'MDL'])], 'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_instructions' => ['nullable', 'string'], 'public_notes' => ['nullable', 'string'], 'internal_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'], 'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $project = isset($data['project_id']) ? Project::findOrFail($data['project_id']) : null;
        abort_if($project && $project->company_id !== (int) $data['company_id'], 422, 'Project does not belong to company.');
        $items = $data['items'];
        unset($data['items']);
        $invoice = $service->create($data + ['status' => 'draft', 'discount' => $data['discount'] ?? 0], $items);

        return redirect()->route('owner.invoices.show', $invoice)->with('success', 'Invoice draft created.');
    }

    public function show(Invoice $invoice): View
    {
        return view('owner.invoices.show', ['invoice' => $invoice->load(['company', 'project', 'items', 'payments.recorder'])]);
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === 'draft', 422);
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);
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
        $dompdf = new Dompdf;
        $dompdf->loadHtml(view('pdf.invoice', compact('invoice'))->render());
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }
}
