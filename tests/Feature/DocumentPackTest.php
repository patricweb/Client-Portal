<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\DocumentHtmlService;
use App\Services\DocumentPackService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Queue::fake();
        Storage::fake('local');
    }

    public function test_only_owner_can_read_or_change_provider_settings(): void
    {
        [$owner, $client] = $this->actors();
        $manager = User::factory()->create(['role' => UserRole::ProjectManager, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $this->actingAs($owner)->get(route('owner.settings.provider.edit'))->assertOk()->assertSee('Legal provider');
        $this->actingAs($manager)->get(route('owner.settings.provider.edit'))->assertForbidden();
        $this->actingAs($client)->put(route('owner.settings.provider.update'), ['legal_name' => 'Wrong person'])->assertForbidden();
        $this->assertSame('Matei Patric', ProviderProfile::current()->details['legal_name']);
    }

    public function test_all_pack_forms_render_without_html_editing(): void
    {
        [$owner, , $company, $project] = $this->actors();
        foreach (array_keys(DocumentPackService::TEMPLATES) as $key) {
            $this->actingAs($owner)->get(route('owner.document-pack.create', ['template' => $key, 'company_id' => $company->id, 'project_id' => $project->id]))
                ->assertOk()->assertSee('Save draft & preview', false)->assertDontSee('Custom HTML content');
        }
        $this->actingAs($owner)->get(route('owner.invoices.create'))->assertOk()->assertSee('Load milestone defaults');
    }

    public function test_incomplete_draft_is_saved_but_cannot_be_sent(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $document = $this->makePack($owner, $company, $project, 'proposal', complete: false);
        $this->assertNotEmpty($document->currentVersionRecord()->snapshot['missing_fields']);
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => ($document)->current_version])->assertSessionHasErrors('provider');
        $this->assertSame('draft', $document->fresh()->status);
        $this->assertNull($document->currentVersionRecord()->published_at);
    }

    public function test_profile_data_is_snapshotted_and_untrusted_field_text_is_escaped(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $this->completeProfile();
        $document = $this->makePack($owner, $company, $project, 'proposal', value: '<script>alert(1)</script> **not markup**');
        $version = $document->currentVersionRecord();
        $this->assertStringNotContainsString('<script>', $version->content);
        $this->assertStringContainsString('&lt;script&gt;', $version->content);
        $this->assertStringContainsString('Matei Patric', $version->content);
        $this->assertStringNotContainsString('Ikira Company', $version->content);
        ProviderProfile::current()->update(['details' => array_replace(ProviderProfile::current()->details, ['legal_name' => 'Later name'])]);
        $this->assertSame('Matei Patric', $version->fresh()->snapshot['provider']['legal_name']);
    }

    public function test_sent_pdf_is_byte_stable_and_draft_revisions_are_hidden_from_clients(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $document = $this->makePack($owner, $company, $project, 'proposal');
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => ($document)->current_version])->assertSessionHasNoErrors();
        $bytes = $this->actingAs($client)->get(route('client.documents.pdf', $document))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $original = $document->currentVersionRecord();
        $this->assertSame(hash('sha256', $bytes), $original->pdf_sha256);
        $company->update(['name' => 'Renamed client']);
        $payload = $this->payload($company, $project, 'proposal') + ['base_version' => 1];
        $payload['title'] = 'PRIVATE NEW DRAFT';
        $this->actingAs($owner)->put(route('owner.document-pack.update', $document), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $document->fresh()->current_version);
        $this->actingAs($client)->get(route('client.documents.show', $document))->assertOk()->assertDontSee('PRIVATE NEW DRAFT');
        $this->actingAs($client)->get(route('client.documents.index'))->assertOk()->assertDontSee('PRIVATE NEW DRAFT');
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => 1])->assertStatus(409);
        $this->actingAs($client)->get(route('client.documents.pdf', ['document' => $document, 'version' => 2]))->assertNotFound();
        $again = $this->actingAs($client)->get(route('client.documents.pdf', ['document' => $document, 'version' => 1]))->assertOk()->getContent();
        $this->assertSame($bytes, $again);
        $this->assertSame($original->content, $original->fresh()->content);
        $this->actingAs($owner)->put(route('owner.document-pack.update', $document), $payload)->assertStatus(409);
    }

    public function test_signature_upload_requires_review_and_is_tied_to_exact_version(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $msa = $this->makePack($owner, $company, $project, 'msa');
        $this->actingAs($owner)->post(route('owner.documents.send', $msa), ['version' => ($msa)->current_version])->assertSessionHasNoErrors();
        $this->assertSame('awaiting_signature', $msa->fresh()->status);
        $this->actingAs($client)->post(route('client.documents.signed', $msa), ['version' => 2, 'file' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf')])->assertStatus(409);
        $this->actingAs($client)->post(route('client.documents.signed', $msa), ['version' => 1, 'file' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('signature_received', $msa->fresh()->status);
        $this->assertNull($msa->currentVersionRecord()->signed_at);
        $attachment = $msa->attachments()->firstOrFail();
        $this->assertSame($msa->currentVersionRecord()->id, $attachment->document_version_id);
        $this->actingAs($owner)->post(route('owner.documents.confirm-signed', $msa), ['version' => 1, 'attachment_id' => $attachment->id, 'execution_confirmed' => 1])->assertRedirect();
        $this->assertSame('signed', $msa->fresh()->status);
        $this->assertNotNull($msa->currentVersionRecord()->signed_at);
        [, $other] = $this->actors();
        $this->actingAs($other)->get(route('attachments.download', $attachment))->assertNotFound();
    }

    public function test_sow_requires_a_signed_parent_and_cannot_link_a_foreign_project(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $this->completeProfile();
        $sow = $this->makePack($owner, $company, $project, 'sow');
        $this->actingAs($owner)->post(route('owner.documents.send', $sow), ['version' => ($sow)->current_version])->assertSessionHasErrors('parent_document_id');
        [, , , $otherProject] = $this->actors();
        $payload = $this->payload($company, $project, 'proposal');
        $payload['project_id'] = $otherProject->id;
        $this->actingAs($owner)->post(route('owner.document-pack.store'), $payload)->assertStatus(422);
    }

    public function test_v2_sow_uses_signature_not_click_approval_and_price_recalculates(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $msa = $this->signed($owner, $company, $project, 'msa');
        $payload = $this->payload($company, $project, 'sow', $msa);
        $payload['price'] = 2400;
        $this->actingAs($owner)->post(route('owner.document-pack.store'), $payload)->assertSessionHasNoErrors();
        $sow = Document::latest('id')->firstOrFail();
        $this->assertStringContainsString('2400.00', $sow->currentVersionRecord()->content);
        $this->assertStringContainsString('1200.00', $sow->currentVersionRecord()->content);
        $this->actingAs($owner)->post(route('owner.documents.send', $sow), ['version' => ($sow)->current_version])->assertSessionHasNoErrors();
        $this->assertSame('awaiting_signature', $sow->fresh()->status);
        $this->actingAs($client)->post(route('client.documents.decision', $sow), ['version' => 1, 'decision' => 'approved'])->assertStatus(422);
    }

    public function test_minor_acceptance_requires_provider_list_and_stale_decisions_are_rejected(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $msa = $this->signed($owner, $company, $project, 'msa');
        $sow = $this->signed($owner, $company, $project, 'sow', $msa);
        $acceptance = $this->makePack($owner, $company, $project, 'acceptance', $sow);
        $this->actingAs($owner)->post(route('owner.documents.send', $acceptance), ['version' => ($acceptance)->current_version])->assertSessionHasNoErrors();
        $this->actingAs($client)->post(route('client.documents.decision', $acceptance), ['version' => 1, 'decision' => 'accepted_with_minor_items'])->assertStatus(422);
        $this->actingAs($client)->post(route('client.documents.decision', $acceptance), ['version' => 99, 'decision' => 'approved'])->assertStatus(409);
        $this->actingAs($client)->post(route('client.documents.decision', $acceptance), ['version' => 1, 'decision' => 'changes_requested', 'comment' => 'D1 does not pass'])->assertRedirect();
        $this->actingAs($client)->get(route('client.documents.show', $acceptance))->assertOk();
        $payload = $this->payload($company, $project, 'acceptance', $sow) + ['base_version' => 1, 'minor_items' => 'Correct the footer by September 8, 2026.'];
        $this->actingAs($owner)->put(route('owner.document-pack.update', $acceptance), $payload)->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.documents.send', $acceptance->fresh()), ['version' => ($acceptance->fresh())->current_version])->assertSessionHasNoErrors();
        $this->actingAs($client)->post(route('client.documents.decision', $acceptance), ['version' => 2, 'decision' => 'accepted_with_minor_items'])->assertRedirect();
        $this->assertSame('accepted_with_minor_items', $acceptance->fresh()->status);
        $this->assertStringContainsString('September 8', $acceptance->approvals()->first()->comment);
    }

    public function test_issued_invoice_pdf_is_frozen_while_ledger_records_payments(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $invoice = app(InvoiceService::class)->create(['company_id' => $company->id, 'project_id' => $project->id, 'issue_date' => today(), 'due_date' => today()->addWeek(), 'currency' => 'USD', 'tax_amount' => 10, 'tax_description' => 'Test-only tax treatment'], [['description' => 'Test service', 'quantity' => 1, 'unit_price' => 100]]);
        $this->assertSame('110.00', $invoice->total);
        $this->actingAs($owner)->post(route('owner.invoices.send', $invoice))->assertSessionHasNoErrors();
        $before = $this->actingAs($client)->get(route('client.billing.pdf', $invoice))->assertOk()->getContent();
        $this->actingAs($owner)->post(route('owner.invoices.payments.store', $invoice), ['amount' => 110, 'paid_at' => now()->format('Y-m-d H:i:s'), 'payment_method' => 'bank_transfer'])->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $after = $this->actingAs($client)->get(route('client.billing.pdf', $invoice))->getContent();
        $this->assertSame($before, $after);
        $this->actingAs($owner)->post(route('owner.invoices.refresh-profile', $invoice))->assertStatus(422);
    }

    public function test_final_invoice_requires_acceptance_and_cannot_rebill_unpaid_advance(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $msa = $this->signed($owner, $company, $project, 'msa');
        $sow = $this->signed($owner, $company, $project, 'sow', $msa);
        $acceptance = $this->makePack($owner, $company, $project, 'acceptance', $sow);
        $invoiceData = ['company_id' => $company->id, 'project_id' => $project->id, 'issue_date' => today()->format('Y-m-d'), 'due_date' => today()->addWeek()->format('Y-m-d'), 'currency' => 'USD', 'sow_document_id' => $sow->id, 'kind' => 'advance', 'items' => [['description' => 'Advance', 'quantity' => 1, 'unit_price' => 500]]];
        $this->actingAs($owner)->post(route('owner.invoices.store'), $invoiceData)->assertSessionHasNoErrors();
        $advance = Invoice::latest('id')->firstOrFail();
        $this->actingAs($owner)->post(route('owner.invoices.send', $advance))->assertSessionHasNoErrors();
        $invoiceData['kind'] = 'final';
        $invoiceData['acceptance_document_id'] = $acceptance->id;
        $invoiceData['items'][0]['unit_price'] = 1000;
        $this->actingAs($owner)->post(route('owner.invoices.store'), $invoiceData)->assertSessionHasNoErrors();
        $wrongFinal = Invoice::latest('id')->firstOrFail();
        $this->actingAs($owner)->post(route('owner.invoices.send', $wrongFinal))->assertSessionHasErrors('acceptance_document_id');
        $this->actingAs($owner)->post(route('owner.documents.send', $acceptance), ['version' => ($acceptance)->current_version])->assertSessionHasNoErrors();
        $this->actingAs($client)->post(route('client.documents.decision', $acceptance), ['version' => 1, 'decision' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.invoices.send', $wrongFinal))->assertSessionHasErrors('total');
        $invoiceData['items'][0]['unit_price'] = 500;
        $this->actingAs($owner)->post(route('owner.invoices.store'), $invoiceData)->assertSessionHasNoErrors();
        $final = Invoice::latest('id')->firstOrFail();
        $this->actingAs($owner)->post(route('owner.invoices.send', $final))->assertSessionHasNoErrors();
        $this->assertSame('sent', $final->fresh()->status);
        $this->assertSame(0.0, $advance->paidAmount());
    }

    public function test_versions_cannot_be_mutated_and_custom_html_cannot_fetch_or_execute_resources(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $clean = app(DocumentHtmlService::class)->clean('<p onclick="evil()">Safe</p><script>alert(1)</script><img src="file:///etc/passwd"><iframe src="https://example.com"></iframe><a href="javascript:evil()">Bad</a>');
        $this->assertStringContainsString('Safe', $clean);
        foreach (['script', 'onclick', '<img', '<iframe', 'javascript:'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
        $document = $this->makePack($owner, $company, $project, 'proposal');
        $this->expectException(\LogicException::class);
        $document->currentVersionRecord()->update(['content' => 'silently rewritten']);
    }

    public function test_signed_change_order_remains_effective_during_a_draft_revision(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $this->completeProfile();
        $msa = $this->signed($owner, $company, $project, 'msa');
        $sow = $this->signed($owner, $company, $project, 'sow', $msa);
        $change = $this->signed($owner, $company, $project, 'change_order', $sow);
        $payload = $this->payload($company, $project, 'change_order', $sow) + ['base_version' => 1];
        $payload['price'] = 1500;
        $this->actingAs($owner)->put(route('owner.document-pack.update', $change), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1000.0, app(InvoiceService::class)->agreementTotal($sow));
        $this->actingAs($owner)->post(route('owner.documents.send', $change), ['version' => 2])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.documents.signed', $change), ['version' => 2, 'execution_confirmed' => 1, 'file' => UploadedFile::fake()->create('executed.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame(1500.0, app(InvoiceService::class)->agreementTotal($sow));
    }

    public function test_missing_archived_pdf_is_not_silently_regenerated(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $this->completeProfile();
        $document = $this->makePack($owner, $company, $project, 'proposal');
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => 1])->assertSessionHasNoErrors();
        Storage::disk('local')->delete($document->currentVersionRecord()->pdf_path);
        $this->actingAs($owner)->get(route('owner.documents.pdf', $document))->assertStatus(409);
    }

    public function test_oversized_table_fields_are_rejected_before_pdf_generation(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $prepared = app(DocumentPackService::class)->prepare('sow', $company, $project, null);
        $field = collect($prepared['fields'])->filter(fn ($field) => ! $field['automatic'] && $field['table_cell'])->keys()->first();
        $payload = $this->payload($company, $project, 'sow');
        $payload['fields'][$field] = str_repeat('x', 1001);
        $this->actingAs($owner)->post(route('owner.document-pack.store'), $payload)->assertSessionHasErrors('fields.'.$field);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_all_pack_pdfs_render_and_optionally_export_visual_qa_fixtures(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $this->completeProfile();
        $export = getenv('EXPORT_DOCUMENT_PDF_QA') === '1';
        $directory = base_path('tmp/pdfs/portal-qa');
        if ($export && ! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        foreach (array_keys(DocumentPackService::TEMPLATES) as $key) {
            $document = $this->makePack($owner, $company, $project, $key);
            $bytes = $this->actingAs($owner)->get(route('owner.documents.pdf', $document))->assertOk()->getContent();
            $this->assertStringStartsWith('%PDF-', $bytes);
            if ($export) {
                file_put_contents($directory.'/'.$key.'.pdf', $bytes);
            }
        }
        foreach (['advance', 'final'] as $kind) {
            $invoice = app(InvoiceService::class)->create(['company_id' => $company->id, 'project_id' => $project->id, 'kind' => $kind, 'issue_date' => today(), 'due_date' => today()->addWeek(), 'currency' => 'USD', 'public_notes' => 'TEST ONLY - not a real invoice.'], [['description' => 'Test website milestone', 'quantity' => 1, 'unit_price' => 500]]);
            $bytes = $this->actingAs($owner)->get(route('owner.invoices.pdf', $invoice))->assertOk()->getContent();
            $this->assertStringStartsWith('%PDF-', $bytes);
            if ($export) {
                file_put_contents($directory.'/'.$kind.'.pdf', $bytes);
            }
        }
    }

    private function actors(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $company = Company::create(['name' => 'Acme Test', 'billing_name' => 'Acme Test LLC', 'billing_address' => '100 Test Street, Test City, USA', 'email' => 'client@example.test', 'currency' => 'USD']);
        $client = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::Client, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Test website', 'type' => 'website', 'price' => 1000, 'currency' => 'USD', 'description' => 'A test project', 'scope' => 'One page', 'exclusions' => 'Hosting']);

        return [$owner, $client, $company, $project];
    }

    private function completeProfile(): void
    {
        ProviderProfile::current()->update(['details' => array_replace(ProviderProfile::current()->details, [
            'address' => 'Test address, Moldova', 'email' => 'provider@example.test', 'details_confirmed' => true,
            'bank_name' => 'TEST BANK - NOT PAYMENT INSTRUCTIONS', 'beneficiary' => 'Matei Patric', 'iban' => 'TEST-ACCOUNT-NOT-REAL', 'swift' => 'TEST-ONLY', 'bank_confirmed' => true,
            'tax_note' => 'Test-only tax treatment',
        ])]);
    }

    private function payload(Company $company, Project $project, string $key, ?Document $parent = null, bool $complete = true, string $value = 'Reviewed test value'): array
    {
        $pack = app(DocumentPackService::class);
        $prepared = $pack->prepare($key, $company, $project, $parent, ['price' => 1000]);
        $fields = collect($prepared['fields'])->reject(fn ($field) => $field['automatic'])->map(fn ($field) => $complete ? ($field['value'] ?: $value) : '')->all();

        return ['template' => $key, 'company_id' => $company->id, 'project_id' => $project->id, 'parent_document_id' => $parent?->id, 'title' => $prepared['definition']['title'].' TEST ONLY', 'price' => 1000, 'fields' => $fields, 'source_hash' => $prepared['source_hash']];
    }

    private function makePack(User $owner, Company $company, Project $project, string $key, ?Document $parent = null, bool $complete = true, string $value = 'Reviewed test value'): Document
    {
        $this->actingAs($owner)->post(route('owner.document-pack.store'), $this->payload($company, $project, $key, $parent, $complete, $value))->assertRedirect()->assertSessionHasNoErrors();

        return Document::latest('id')->firstOrFail();
    }

    private function signed(User $owner, Company $company, Project $project, string $key, ?Document $parent = null): Document
    {
        $document = $this->makePack($owner, $company, $project, $key, $parent);
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => ($document)->current_version])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.documents.signed', $document), ['version' => 1, 'execution_confirmed' => 1, 'file' => UploadedFile::fake()->create('executed.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();

        return $document->fresh();
    }
}
