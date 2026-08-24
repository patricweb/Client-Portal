<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\BriefTemplate;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_owner_can_sign_in_and_open_dashboard(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.com', 'password' => 'secret-password', 'role' => UserRole::Owner,
            'status' => AccountStatus::Active, 'must_change_password' => false,
        ]);

        $this->post('/login', ['email' => $owner->email, 'password' => 'secret-password'])
            ->assertRedirect(route('owner.dashboard'));
        $this->get(route('owner.dashboard'))->assertOk()->assertSee('Today');
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'role' => UserRole::Client, 'status' => AccountStatus::Active, 'must_change_password' => false,
        ]);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_client_is_forced_to_replace_temporary_password(): void
    {
        $company = Company::create(['name' => 'Acme', 'timezone' => 'America/New_York', 'currency' => 'USD']);
        $client = User::factory()->create([
            'company_id' => $company->id, 'password' => 'temporary-password', 'role' => UserRole::Client,
            'status' => AccountStatus::Invited, 'must_change_password' => true,
        ]);

        $this->post('/login', ['email' => $client->email, 'password' => 'temporary-password']);
        $this->get(route('client.dashboard'))->assertRedirect(route('password.edit'));
    }

    public function test_client_cannot_open_another_company_project(): void
    {
        [$client] = $this->clientWithProject('Acme');
        [, $otherProject] = $this->clientWithProject('Other');

        $this->actingAs($client)->get(route('client.projects.show', $otherProject))->assertNotFound();
    }

    public function test_client_can_save_and_submit_a_brief(): void
    {
        [$client, $project] = $this->clientWithProject('Acme');
        $template = BriefTemplate::create(['name' => 'Website Brief', 'project_type' => 'website']);
        $field = $template->fields()->create(['key' => 'goals', 'label' => 'Project goals', 'is_required' => true, 'position' => 1]);
        $brief = $project->brief()->create(['brief_template_id' => $template->id]);

        $this->actingAs($client)->put(route('client.brief.update', $project), [
            'answers' => [$field->id => 'Generate qualified leads.'], 'submit' => 1,
        ])->assertRedirect(route('client.projects.show', $project));

        $this->assertDatabaseHas('project_briefs', ['id' => $brief->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('brief_answers', ['project_brief_id' => $brief->id, 'value' => 'Generate qualified leads.']);
    }

    public function test_owner_can_create_a_client_with_portal_access(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner, 'status' => AccountStatus::Active, 'must_change_password' => false,
        ]);

        $this->actingAs($owner)->post(route('owner.companies.store'), [
            'name' => 'Acme Company', 'email' => 'hello@acme.test', 'timezone' => 'America/New_York',
            'currency' => 'USD', 'contact_name' => 'John Smith', 'contact_email' => 'john@acme.test',
            'create_access' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('companies', ['name' => 'Acme Company']);
        $this->assertDatabaseHas('users', ['email' => 'john@acme.test', 'role' => 'client', 'must_change_password' => true]);
        $this->assertNotNull(session('temporary_credentials.password'));
    }

    public function test_owner_can_create_project_from_workflow_and_brief_templates(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner, 'status' => AccountStatus::Active, 'must_change_password' => false,
        ]);
        $company = Company::create(['name' => 'Acme', 'timezone' => 'America/New_York', 'currency' => 'USD']);
        $workflow = WorkflowTemplate::create(['name' => 'Website Workflow', 'project_type' => 'website']);
        $workflow->stages()->create(['title' => 'Brief', 'position' => 1]);
        $briefTemplate = BriefTemplate::create(['name' => 'Website Brief', 'project_type' => 'website']);

        $this->actingAs($owner)->post(route('owner.projects.store'), [
            'company_id' => $company->id, 'workflow_template_id' => $workflow->id, 'name' => 'Acme Website',
            'type' => 'website', 'price' => 2500, 'currency' => 'USD', 'status' => 'awaiting_brief',
        ])->assertRedirect();

        $project = Project::where('name', 'Acme Website')->firstOrFail();
        $this->assertDatabaseHas('project_stages', ['project_id' => $project->id, 'title' => 'Brief']);
        $this->assertDatabaseHas('project_briefs', ['project_id' => $project->id, 'brief_template_id' => $briefTemplate->id]);
    }

    private function clientWithProject(string $companyName): array
    {
        $company = Company::create(['name' => $companyName, 'timezone' => 'America/New_York', 'currency' => 'USD']);
        $client = User::factory()->create([
            'company_id' => $company->id, 'role' => UserRole::Client,
            'status' => AccountStatus::Active, 'must_change_password' => false,
        ]);
        $project = Project::create([
            'company_id' => $company->id, 'name' => "{$companyName} Website", 'type' => 'website',
            'status' => 'awaiting_brief', 'price' => 1000, 'currency' => 'USD',
        ]);

        return [$client, $project];
    }
}
