<?php

namespace App\Console\Commands;

use App\Models\CarePlan;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'care:generate-invoices';

    protected $description = 'Create draft invoices for active Care & Support plans that are due';

    public function handle(InvoiceService $service, NotificationService $notifications): int
    {
        $created = 0;
        CarePlan::where('status', 'active')->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', today())->each(function (CarePlan $plan) use ($service, $notifications, &$created) {
                DB::transaction(function () use ($plan, $service, $notifications, &$created) {
                    $plan = CarePlan::lockForUpdate()->findOrFail($plan->id);
                    if ($plan->status !== 'active' || ! $plan->next_billing_date || $plan->next_billing_date->isAfter(today())) {
                        return;
                    }
                    if ($plan->invoices()->whereDate('issue_date', $plan->next_billing_date)->exists()) {
                        $plan->update(['next_billing_date' => $plan->nextBillingDateAfterCurrent()]);

                        return;
                    }
                    $invoice = $service->create([
                        'company_id' => $plan->company_id, 'project_id' => $plan->project_id, 'care_plan_id' => $plan->id,
                        'issue_date' => $plan->next_billing_date, 'due_date' => $plan->next_billing_date->copy()->addDays(14),
                        'currency' => $plan->currency, 'status' => 'draft', 'discount' => 0,
                        'public_notes' => 'Recurring '.$plan->billing_frequency.' Care & Support invoice. Review before sending.',
                    ], [['description' => $plan->name.' — '.$plan->billing_frequency.' service', 'quantity' => 1, 'unit_price' => $plan->billingAmount()]]);
                    $plan->update(['next_billing_date' => $plan->nextBillingDateAfterCurrent()]);
                    $created++;
                    $notifications->send(
                        User::where('role', 'owner')->get(), 'recurring_invoice_draft', 'action_required',
                        'Recurring invoice draft created', "{$invoice->invoice_number} for {$plan->name}", route('owner.invoices.show', $invoice)
                    );
                });
            });

        $this->info("Created {$created} recurring invoice draft(s).");

        return self::SUCCESS;
    }
}
