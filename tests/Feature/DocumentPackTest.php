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

    public function test_only_three_simple_confirmation_templates_are_offered(): void
    {
        [$owner, $client, $company, $project] = $this->actors();

        $this->assertSame(['project_confirmation', 'change_confirmation', 'delivery_confirmation'], array_keys(DocumentPackService::TEMPLATES));
        foreach (array_keys(DocumentPackService::TEMPLATES) as $key) {
            $this->actingAs($owner)->get(route('owner.document-pack.create', ['template' => $key, 'company_id' => $company->id, 'project_id' => $project->id]))
                ->assertOk()->assertSee('Save draft & preview', false)->assertDontSee('Custom HTML content');
        }
        $this->actingAs($owner)->get(route('owner.document-pack.create'))->assertOk()->assertDontSee('Master Services Agreement')->assertDontSee('Statement of Work');
    }

    public function test_incomplete_draft_is_saved_but_cannot_be_sent(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $document = $this->makePack($owner, $company, $project, 'project_confirmation', complete: false);

        $this->assertNotEmpty($document->currentVersionRecord()->snapshot['missing_fields']);
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => 1])->assertSessionHasErrors('provider');
        $this->assertSame('draft', $document->fresh()->status);
        $this->assertNull($document->currentVersionRecord()->published_at);
    }

    public function test_profile_data_is_snapshotted_and_untrusted_text_is_escaped(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $this->completeProfile();
        $document = $this->makePack($owner, $company, $project, 'project_confirmation', value: '<script>alert(1)</script> **not markup**');
        $version = $document->currentVersionRecord();

        $this->assertStringNotContainsString('<script>', $version->content);
        $this->assertStringContainsString('&lt;script&gt;', $version->content);
        $this->assertStringContainsString('Matei Patric', $version->content);
        ProviderProfile::current()->update(['details' => array_replace(ProviderProfile::current()->details, ['legal_name' => 'Later name'])]);
        $this->assertSame('Matei Patric', $version->fresh()->snapshot['provider']['legal_name']);
    }

    public function test_client_confirmation_requires_explicit_intent_and_records_exact_version(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $document = $this->makePack($owner, $company, $project, 'project_confirmation');
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => 1])->assertSessionHasNoErrors();

        $this->actingAs($client)->post(route('client.documents.decision', $document), ['version' => 1, 'decision' => 'approved'])->assertSessionHasErrors('confirm_intent');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->actingAs($client)->post(route('client.documents.decision', $document), [
            'version' => 1, 'decision' => 'approved', 'confirm_intent' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approval = $document->approvals()->firstOrFail();
        $this->assertSame('accepted', $document->fresh()->status);
        $this->assertSame(1, $approval->version);
        $this->assertSame($client->id, $approval->user_id);
        $this->assertNotNull($approval->decided_at);
        $this->assertNotNull($document->currentVersionRecord()->pdf_sha256);
    }

    public function test_sent_pdf_is_stable_and_new_draft_is_hidden_from_client(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $document = $this->makePack($owner, $company, $project, 'project_confirmation');
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => 1])->assertSessionHasNoErrors();
        $bytes = $this->actingAs($client)->get(route('client.documents.pdf', $document))->assertOk()->getContent();
        $original = $document->currentVersionRecord();

        $payload = $this->payload($company, $project, 'project_confirmation') + ['base_version' => 1];
        $payload['title'] = 'PRIVATE NEW DRAFT';
        $this->actingAs($owner)->put(route('owner.document-pack.update', $document), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($client)->get(route('client.documents.show', $document))->assertOk()->assertDontSee('PRIVATE NEW DRAFT');
        $this->actingAs($client)->get(route('client.documents.pdf', ['document' => $document, 'version' => 2]))->assertNotFound();
        $again = $this->actingAs($client)->get(route('client.documents.pdf', ['document' => $document, 'version' => 1]))->assertOk()->getContent();
        $this->assertSame($bytes, $again);
        $this->assertSame(hash('sha256', $bytes), $original->pdf_sha256);
    }

    public function test_change_and_delivery_require_accepted_project_confirmation(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $projectConfirmation = $this->makePack($owner, $company, $project, 'project_confirmation');
        $change = $this->makePack($owner, $company, $project, 'change_confirmation');

        $this->actingAs($owner)->post(route('owner.documents.send', $change), ['version' => 1])->assertSessionHasErrors('parent_document_id');
        $this->confirm($owner, $client, $projectConfirmation);

        foreach (['change_confirmation', 'delivery_confirmation'] as $key) {
            $document = $this->makePack($owner, $company, $project, $key, $projectConfirmation);
            $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => 1])->assertSessionHasNoErrors();
            $this->assertSame('awaiting_approval', $document->fresh()->status);
        }
    }

    public function test_confirmed_change_updates_project_total(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $projectConfirmation = $this->accepted($owner, $client, $company, $project, 'project_confirmation');
        $payload = $this->payload($company, $project, 'change_confirmation', $projectConfirmation);
        $payload['price'] = 1500;
        $this->actingAs($owner)->post(route('owner.document-pack.store'), $payload)->assertSessionHasNoErrors();
        $change = Document::latest('id')->firstOrFail();

        $this->assertStringContainsString('500.00', $change->currentVersionRecord()->content);
        $this->confirm($owner, $client, $change);
        $this->assertSame(1500.0, app(InvoiceService::class)->agreementTotal($projectConfirmation));
    }

    public function test_delivery_confirmation_supports_precise_minor_items(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $projectConfirmation = $this->accepted($owner, $client, $company, $project, 'project_confirmation');
        $payload = $this->payload($company, $project, 'delivery_confirmation', $projectConfirmation);
        $payload['minor_items'] = 'Correct the footer by September 8, 2026.';
        $this->actingAs($owner)->post(route('owner.document-pack.store'), $payload)->assertSessionHasNoErrors();
        $delivery = Document::latest('id')->firstOrFail();
        $this->actingAs($owner)->post(route('owner.documents.send', $delivery), ['version' => 1])->assertSessionHasNoErrors();

        $this->actingAs($client)->post(route('client.documents.decision', $delivery), [
            'version' => 1, 'decision' => 'accepted_with_minor_items', 'confirm_intent' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted_with_minor_items', $delivery->fresh()->status);
        $this->assertStringContainsString('September 8', $delivery->approvals()->first()->comment);
    }

    public function test_final_invoice_requires_delivery_and_does_not_rebill_advance(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $agreement = $this->accepted($owner, $client, $company, $project, 'project_confirmation');
        $delivery = $this->accepted($owner, $client, $company, $project, 'delivery_confirmation', $agreement);

        $base = [
            'company_id' => $company->id, 'project_id' => $project->id,
            'issue_date' => today()->format('Y-m-d'), 'due_date' => today()->addWeek()->format('Y-m-d'),
            'currency' => 'USD', 'sow_document_id' => $agreement->id,
            'items' => [['description' => 'Milestone', 'quantity' => 1, 'unit_price' => 500]],
        ];
        $this->actingAs($owner)->post(route('owner.invoices.store'), $base + ['kind' => 'advance'])->assertSessionHasNoErrors();
        $advance = Invoice::latest('id')->firstOrFail();
        $this->actingAs($owner)->post(route('owner.invoices.send', $advance))->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('owner.invoices.store'), $base + ['kind' => 'final', 'acceptance_document_id' => $delivery->id])->assertSessionHasNoErrors();
        $final = Invoice::latest('id')->firstOrFail();
        $this->actingAs($owner)->post(route('owner.invoices.send', $final))->assertSessionHasNoErrors();
        $this->assertSame('sent', $final->fresh()->status);
        $this->assertSame(0.0, $advance->paidAmount());
    }

    public function test_versions_are_immutable_and_custom_html_is_cleaned(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $clean = app(DocumentHtmlService::class)->clean('<p onclick="evil()">Provider: Safe</p><script>alert(1)</script><img src="file:///etc/passwd"><iframe src="https://example.com"></iframe>');
        foreach (['script', 'onclick', '<img', '<iframe'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
        $this->assertStringContainsString('<strong>Provider:</strong> Safe', $clean);
        $document = $this->makePack($owner, $company, $project, 'project_confirmation');
        $this->expectException(\LogicException::class);
        $document->currentVersionRecord()->update(['content' => 'silently rewritten']);
    }

    public function test_missing_archived_pdf_is_not_silently_regenerated(): void
    {
        [$owner, , $company, $project] = $this->actors();
        $this->completeProfile();
        $document = $this->makePack($owner, $company, $project, 'project_confirmation');
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => 1])->assertSessionHasNoErrors();
        Storage::disk('local')->delete($document->currentVersionRecord()->pdf_path);
        $this->actingAs($owner)->get(route('owner.documents.pdf', $document))->assertStatus(409);
    }

    public function test_all_pack_pdfs_render_and_optionally_export_visual_qa_fixtures(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $this->completeProfile();
        $export = getenv('EXPORT_DOCUMENT_PDF_QA') === '1';
        $directory = base_path('tmp/pdfs/portal-qa');
        if ($export && ! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $parent = null;
        foreach (array_keys(DocumentPackService::TEMPLATES) as $key) {
            $document = $this->makePack($owner, $company, $project, $key, $parent);
            if ($key === 'project_confirmation') {
                $parent = $this->confirm($owner, $client, $document);
            }
            $bytes = $this->actingAs($owner)->get(route('owner.documents.pdf', $document))->assertOk()->getContent();
            $this->assertStringStartsWith('%PDF-', $bytes);
            if ($export) {
                file_put_contents($directory.'/'.$key.'.pdf', $bytes);
            }
        }
    }

    private function actors(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $company = Company::create(['name' => 'Acme Test', 'billing_name' => 'Acme Test LLC', 'billing_address' => '100 Test Street, Test City, USA', 'email' => 'client@example.test', 'currency' => 'USD']);
        $client = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::Client, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Test website', 'type' => 'website', 'price' => 1000, 'currency' => 'USD', 'description' => 'A test project', 'scope' => 'One responsive website', 'exclusions' => 'Hosting']);

        return [$owner, $client, $company, $project];
    }

    private function completeProfile(): void
    {
        ProviderProfile::current()->update(['details' => array_replace(ProviderProfile::current()->details, [
            'address' => 'Test address, Moldova', 'email' => 'provider@example.test', 'details_confirmed' => true,
            'bank_name' => 'TEST BANK', 'beneficiary' => 'Matei Patric', 'iban' => 'TEST-ACCOUNT', 'swift' => 'TEST', 'bank_confirmed' => true,
            'tax_note' => 'No tax charged - test fixture',
        ])]);
    }

    private function payload(Company $company, Project $project, string $key, ?Document $parent = null, bool $complete = true, string $value = 'Reviewed test value'): array
    {
        $pack = app(DocumentPackService::class);
        $prepared = $pack->prepare($key, $company, $project, $parent, ['price' => 1000, 'target_date' => '2026-09-30']);
        $fields = collect($prepared['fields'])->reject(fn ($field) => $field['automatic'])->map(fn ($field) => $complete ? ($field['value'] ?: $value) : '')->all();

        return ['template' => $key, 'company_id' => $company->id, 'project_id' => $project->id, 'parent_document_id' => $parent?->id, 'title' => $prepared['definition']['title'].' TEST ONLY', 'price' => 1000, 'target_date' => '2026-09-30', 'fields' => $fields, 'source_hash' => $prepared['source_hash']];
    }

    private function makePack(User $owner, Company $company, Project $project, string $key, ?Document $parent = null, bool $complete = true, string $value = 'Reviewed test value'): Document
    {
        $this->actingAs($owner)->post(route('owner.document-pack.store'), $this->payload($company, $project, $key, $parent, $complete, $value))->assertRedirect()->assertSessionHasNoErrors();

        return Document::latest('id')->firstOrFail();
    }

    private function confirm(User $owner, User $client, Document $document): Document
    {
        $this->actingAs($owner)->post(route('owner.documents.send', $document), ['version' => $document->current_version])->assertSessionHasNoErrors();
        $this->actingAs($client)->post(route('client.documents.decision', $document), ['version' => $document->current_version, 'decision' => 'approved', 'confirm_intent' => 1])->assertSessionHasNoErrors();

        return $document->fresh();
    }

    private function accepted(User $owner, User $client, Company $company, Project $project, string $key, ?Document $parent = null): Document
    {
        return $this->confirm($owner, $client, $this->makePack($owner, $company, $project, $key, $parent));
    }
}
