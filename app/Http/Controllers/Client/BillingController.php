<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::with(['project', 'payments'])->where('company_id', $request->user()->company_id)
            ->where('status', '!=', 'draft')->latest('issue_date')->get();

        return view('client.billing.index', [
            'invoices' => $invoices,
            'total' => $invoices->where('status', '!=', 'void')->sum(fn ($invoice) => (float) $invoice->total),
            'paid' => $invoices->sum(fn ($invoice) => $invoice->paidAmount()),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        if (! $invoice->viewed_at) {
            $invoice->update(['viewed_at' => now(), 'status' => $invoice->status === 'sent' ? 'viewed' : $invoice->status]);
        }

        return view('client.billing.show', ['invoice' => $invoice->load(['company', 'project', 'items', 'payments'])]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $invoice->load(['company', 'project', 'items', 'payments']);
        $dompdf = new Dompdf;
        $dompdf->loadHtml(view('pdf.invoice', compact('invoice'))->render());
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf']);
    }
}
