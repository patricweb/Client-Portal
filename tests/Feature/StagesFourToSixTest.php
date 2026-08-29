<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\CarePlan;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StagesFourToSixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_owner_can_create_and_send_a_proposal_with_a_frozen_version(): void
    {
        [$owner, , $company, $project] = $this->actors();

        $this->actingAs($owner)->post(route('owner.documents.store'), [
            'company_id' => $company->id, 'project_id' => $project->id, 'type' => 'proposal',
            'title' => 'Website Proposal', 'content' => '<h1>{{company.name}}</h1><p>{{project.price}}</p>',
            'expires_at' => today()->addWeek()->format('Y-m-d'),
        ])->assertRedirect();

        $document = Document::firstOrFail();
        $this->assertStringContainsString('Acme', $document->currentVersionRecord()->content);
        $this->actingAs($owner)->post(route('owner.documents.send', $document))->assertRedirect();
        $this->assertSame('awaiting_approval', $document->fresh()->status);
        $this->assertNotNull($document->currentVersionRecord()->fresh()->locked_at);
    }

    public function test_client_can_accept_own_document_but_cannot_view_another_company_document(): void
    {
        [, $client, $company, $project] = $this->actors();
        $document = $this->document($company, $project);
        [, $otherClient, $otherCompany, $otherProject] = $this->actors('Other');
        $otherDocument = $this->document($otherCompany, $otherProject);

        $this->actingAs($client)->post(route('client.documents.decision', $document), ['decision' => 'approved', 'confirm_intent' => 1])->assertRedirect();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('approvals', ['approvable_id' => $document->id, 'decision' => 'approved', 'user_id' => $client->id]);
        $this->actingAs($otherClient)->get(route('client.documents.show', $document))->assertForbidden();
        $this->actingAs($client)->get(route('client.documents.show', $otherDocument))->assertForbidden();
    }

    public function test_editing_an_accepted_document_creates_a_new_version(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $document = $this->document($company, $project, 'accepted');
        $oldContent = $document->currentVersionRecord()->content;

        $this->actingAs($owner)->put(route('owner.documents.update', $document), ['title' => 'Revised Proposal', 'content' => '<p>Revision two</p>'])->assertRedirect();

        $document->refresh();
        $this->assertSame(2, $document->current_version);
        $this->assertSame('draft', $document->status);
        $this->assertSame($oldContent, $document->versions()->where('version', 1)->value('content'));
    }

    public function test_client_stage_approval_is_audited_and_updates_progress(): void
    {
        [, $client, , $project] = $this->actors();
        $stage = $project->stages()->create(['title' => 'Design', 'position' => 1, 'requires_approval' => true, 'status' => 'approval_required']);

        $this->actingAs($client)->post(route('client.stages.decision', [$project, $stage]), ['decision' => 'approved'])->assertRedirect();

        $this->assertDatabaseHas('project_stages', ['id' => $stage->id, 'status' => 'approved']);
        $this->assertDatabaseHas('approvals', ['approvable_id' => $stage->id, 'decision' => 'approved']);
        $this->assertSame(100, $project->fresh()->progress);
    }

    public function test_invoice_supports_partial_and_full_payments_without_deletion(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $invoice = app(InvoiceService::class)->create([
            'company_id' => $company->id, 'project_id' => $project->id, 'issue_date' => today(),
            'due_date' => today()->addWeek(), 'currency' => 'USD', 'status' => 'sent', 'discount' => 0,
        ], [['description' => 'Development', 'quantity' => 1, 'unit_price' => 1000]]);

        $this->actingAs($owner)->post(route('owner.invoices.payments.store', $invoice), [
            'amount' => 400, 'paid_at' => now()->format('Y-m-d H:i:s'), 'payment_method' => 'bank_transfer',
        ])->assertRedirect();
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->actingAs($owner)->post(route('owner.invoices.payments.store', $invoice), [
            'amount' => 600, 'paid_at' => now()->format('Y-m-d H:i:s'), 'payment_method' => 'bank_transfer',
        ])->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, $invoice->remainingAmount());
    }

    public function test_invoice_can_be_sent_without_a_client_billing_address(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $profile = ProviderProfile::current();
        $profile->update(['details' => array_replace($profile->details, [
            'address' => 'Provider address', 'country' => 'Moldova', 'email' => 'provider@example.test',
            'details_confirmed' => true, 'bank_name' => 'Test Bank', 'beneficiary' => 'Test Provider',
            'iban' => 'TEST-IBAN', 'swift' => 'TESTSWIFT', 'currency' => 'USD', 'bank_confirmed' => true,
            'tax_note' => 'Issued by an individual service provider. VAT is not charged.',
        ])]);
        $invoice = app(InvoiceService::class)->create([
            'company_id' => $company->id, 'project_id' => $project->id, 'issue_date' => today(),
            'due_date' => today()->addDays(4), 'currency' => 'USD', 'status' => 'draft', 'discount' => 0,
        ], [['description' => 'Development', 'quantity' => 1, 'unit_price' => 100]]);

        $this->assertNull($company->billing_address);
        $this->actingAs($owner)->post(route('owner.invoices.send', $invoice))->assertSessionHasNoErrors();
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_client_billing_is_isolated_by_company(): void
    {
        [, $client, $company, $project] = $this->actors();
        [, , $otherCompany, $otherProject] = $this->actors('Other');
        $service = app(InvoiceService::class);
        $own = $service->create(['company_id' => $company->id, 'project_id' => $project->id, 'issue_date' => today(), 'due_date' => today(), 'currency' => 'USD', 'status' => 'sent', 'discount' => 0], [['description' => 'Own', 'quantity' => 1, 'unit_price' => 10]]);
        $other = $service->create(['company_id' => $otherCompany->id, 'project_id' => $otherProject->id, 'issue_date' => today(), 'due_date' => today(), 'currency' => 'USD', 'status' => 'sent', 'discount' => 0], [['description' => 'Other', 'quantity' => 1, 'unit_price' => 20]]);

        $this->actingAs($client)->get(route('client.billing.show', $own))->assertOk();
        $this->actingAs($client)->get(route('client.billing.show', $other))->assertForbidden();
    }

    public function test_payment_schedule_item_creates_invoice_once(): void
    {
        [$owner, , , $project] = $this->actors();
        $schedule = $project->paymentSchedule()->create(['name' => '50/50']);
        $item = $schedule->items()->create(['label' => 'Deposit', 'amount_type' => 'percentage', 'value' => 50, 'position' => 1]);

        $this->actingAs($owner)->post(route('owner.payment-schedules.invoice', $item))->assertRedirect();
        $this->assertDatabaseHas('invoices', ['project_id' => $project->id, 'total' => 500]);
        $this->actingAs($owner)->post(route('owner.payment-schedules.invoice', $item->fresh()))->assertStatus(422);
    }

    public function test_recurring_care_job_creates_one_draft_and_advances_billing_date(): void
    {
        [, , $company, $project] = $this->actors();
        $plan = CarePlan::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'type' => 'website_care',
            'name' => 'Website Care', 'monthly_price' => 150, 'currency' => 'USD', 'billing_frequency' => 'monthly',
            'included_support_minutes' => 60, 'additional_hourly_rate' => 100, 'start_date' => today(),
            'next_billing_date' => today(), 'status' => 'active',
        ]);

        Artisan::call('care:generate-invoices');

        $this->assertDatabaseHas('invoices', ['care_plan_id' => $plan->id, 'status' => 'draft', 'total' => 150]);
        $this->assertTrue($plan->fresh()->next_billing_date->isSameDay(today()->addMonthNoOverflow()));
        Artisan::call('care:generate-invoices');
        $this->assertSame(1, Invoice::where('care_plan_id', $plan->id)->count());
    }

    public function test_care_activity_tracks_minutes_and_client_access_is_isolated(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $plan = CarePlan::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'type' => 'website_care', 'name' => 'Care',
            'monthly_price' => 100, 'currency' => 'USD', 'billing_frequency' => 'monthly', 'included_support_minutes' => 60,
            'additional_hourly_rate' => 100, 'start_date' => today(), 'status' => 'active',
        ]);
        [, $otherClient] = $this->actors('Other');

        $this->actingAs($owner)->post(route('owner.care-plans.activities.store', $plan), [
            'type' => 'support', 'minutes' => 25, 'notes' => 'Content update', 'occurred_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $this->assertSame(25, $plan->fresh()->used_support_minutes);
        $this->actingAs($client)->get(route('client.care-plans.show', $plan))->assertOk();
        $this->actingAs($otherClient)->get(route('client.care-plans.show', $plan))->assertForbidden();
    }

    private function actors(string $companyName = 'Acme'): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $company = Company::create(['name' => $companyName, 'timezone' => 'America/New_York', 'currency' => 'USD']);
        $client = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::Client, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $project = Project::create(['company_id' => $company->id, 'name' => "{$companyName} Website", 'type' => 'website', 'status' => 'active', 'price' => 1000, 'currency' => 'USD']);

        return [$owner, $client, $company, $project];
    }

    private function document(Company $company, Project $project, string $status = 'awaiting_approval'): Document
    {
        $document = Document::create(['company_id' => $company->id, 'project_id' => $project->id, 'type' => 'proposal', 'title' => 'Proposal', 'status' => $status]);
        $document->versions()->create(['version' => 1, 'content' => '<p>Proposal</p>', 'locked_at' => now()]);

        return $document;
    }
}
