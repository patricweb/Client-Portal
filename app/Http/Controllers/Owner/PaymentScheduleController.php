<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PaymentScheduleItem;
use App\Models\Project;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentScheduleController extends Controller
{
    public function create(Project $project): View
    {
        return view('owner.payment-schedules.create', ['project' => $project->load(['company', 'stages', 'paymentSchedule.items'])]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $request->merge(['items' => collect($request->input('items', []))->filter(fn ($item) => filled($item['label'] ?? null))->values()->all()]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'items' => ['required', 'array', 'min:1'],
            'items.*.label' => ['required', 'string', 'max:255'], 'items.*.amount_type' => ['required', 'in:fixed,percentage'],
            'items.*.value' => ['required', 'numeric', 'gt:0'], 'items.*.due_date' => ['nullable', 'date'],
            'items.*.project_stage_id' => ['nullable', 'exists:project_stages,id'], 'items.*.description' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($project, $data) {
            $schedule = $project->paymentSchedule()->updateOrCreate([], ['name' => $data['name']]);
            $schedule->items()->whereNull('invoice_id')->delete();
            foreach ($data['items'] as $position => $item) {
                abort_if(isset($item['project_stage_id']) && ! $project->stages()->whereKey($item['project_stage_id'])->exists(), 422);
                $schedule->items()->create($item + ['position' => $position + 1]);
            }
        });

        return redirect()->route('owner.projects.show', $project)->with('success', 'Payment schedule saved.');
    }

    public function invoice(PaymentScheduleItem $item, InvoiceService $service): RedirectResponse
    {
        abort_if($item->invoice_id, 422, 'Invoice already created.');
        $item->load('schedule.project.company');
        $project = $item->schedule->project;
        $invoice = $service->create([
            'company_id' => $project->company_id, 'project_id' => $project->id,
            'issue_date' => today(), 'due_date' => $item->due_date ?? today()->addDays(14),
            'currency' => $project->currency, 'status' => 'draft', 'discount' => 0,
            'public_notes' => $item->description,
        ], [['description' => $item->label, 'quantity' => 1, 'unit_price' => $item->amount()]]);
        $item->update(['invoice_id' => $invoice->id]);

        return redirect()->route('owner.invoices.show', $invoice)->with('success', 'Invoice created from payment schedule.');
    }
}
