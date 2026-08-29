<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Jobs\DeliverTelegramNotification;
use App\Models\Company;
use App\Models\NotificationDelivery;
use App\Models\Project;
use App\Models\RequestMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StagesSevenToElevenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_client_request_is_isolated_and_internal_notes_are_hidden(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        [, $otherClient] = $this->actors('Other');
        $request = SupportRequest::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'created_by' => $client->id,
            'category' => 'bug', 'subject' => 'Checkout issue', 'description' => 'It fails.',
        ]);
        RequestMessage::create(['support_request_id' => $request->id, 'user_id' => $owner->id, 'body' => 'Visible reply', 'is_internal' => false]);
        RequestMessage::create(['support_request_id' => $request->id, 'user_id' => $owner->id, 'body' => 'Private diagnosis', 'is_internal' => true]);

        $this->actingAs($client)->get(route('client.requests.show', $request))->assertOk()->assertSee('Visible reply')->assertDontSee('Private diagnosis');
        $this->actingAs($otherClient)->get(route('client.requests.show', $request))->assertForbidden();
    }

    public function test_notification_preferences_control_delivery_channels(): void
    {
        [, $client] = $this->actors();
        $client->update(['notification_preferences' => ['portal' => true, 'email' => false, 'telegram' => false]]);

        app(NotificationService::class)->send($client, 'test_event', 'important_update', 'Test', 'Message', '/portal');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $client->id]);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $client->id, 'channel' => 'portal']);
        $this->assertDatabaseMissing('notification_deliveries', ['user_id' => $client->id, 'channel' => 'email']);
    }

    public function test_telegram_notification_contains_portal_link_and_respects_message_limit(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.owner_chat_id' => '12345',
        ]);
        $delivery = NotificationDelivery::create([
            'event' => 'brief_submitted', 'level' => 'action_required', 'channel' => 'telegram',
            'recipient' => '12345', 'status' => 'pending',
            'payload' => [
                'title' => 'Brief submitted', 'message' => str_repeat('Answer ', 1000),
                'url' => 'https://portal.test/owner/projects/1',
            ],
        ]);

        (new DeliverTelegramNotification($delivery->id))->handle();

        Http::assertSent(function ($request) {
            $text = $request->data()['text'];

            return mb_strlen($text) <= 4096
                && str_contains($text, 'Open in portal:')
                && str_contains($text, 'https://portal.test/owner/projects/1');
        });
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_activity_log_separates_public_and_internal_events(): void
    {
        [$owner, $client, $company, $project] = $this->actors();
        $public = SupportRequest::create(['company_id' => $company->id, 'project_id' => $project->id, 'category' => 'general_question', 'subject' => 'Public update', 'description' => 'Question']);
        RequestMessage::create(['support_request_id' => $public->id, 'user_id' => $owner->id, 'body' => 'Secret note', 'is_internal' => true]);

        $this->actingAs($client)->get(route('client.activity.index'))->assertOk()->assertSee('Support Request Created')->assertDontSee('Request Message Created');
        $this->assertDatabaseHas('activity_logs', ['event' => 'request_message.created', 'visibility' => 'internal']);
    }

    public function test_owner_override_requires_reason_and_is_audited(): void
    {
        [$owner, , , $project] = $this->actors();
        $stage = $project->stages()->create(['title' => 'Design approval', 'position' => 1, 'requires_approval' => true, 'status' => 'approval_required']);

        $this->actingAs($owner)->patch(route('owner.projects.stages.update', [$project, $stage]), ['status' => 'completed'])->assertSessionHasErrors('override_reason');
        $this->actingAs($owner)->patch(route('owner.projects.stages.update', [$project, $stage]), ['status' => 'completed', 'override_reason' => 'Approved during client call'])->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['event' => 'project_stage.owner_override', 'actor_id' => $owner->id]);
    }

    public function test_role_permissions_and_project_assignments_are_enforced(): void
    {
        [$owner, , , $project] = $this->actors();
        [, , , $otherProject] = $this->actors('Other');
        $developer = User::factory()->create(['role' => UserRole::Developer, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $accountant = User::factory()->create(['role' => UserRole::Accountant, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $developer->assignedProjects()->attach($project, ['assigned_by' => $owner->id]);

        $this->actingAs($developer)->get(route('owner.projects.show', $project))->assertOk();
        $this->actingAs($developer)->get(route('owner.projects.show', $otherProject))->assertNotFound();
        $this->actingAs($accountant)->get(route('owner.projects.index'))->assertForbidden();
    }

    public function test_owner_can_invite_team_member_with_project_assignment(): void
    {
        [$owner, , , $project] = $this->actors();
        $this->actingAs($owner)->post(route('owner.team.store'), [
            'name' => 'Project Manager', 'email' => 'pm@example.test', 'role' => UserRole::ProjectManager->value,
            'project_ids' => [$project->id],
        ])->assertRedirect()->assertSessionHas('temporary_credentials');

        $member = User::where('email', 'pm@example.test')->firstOrFail();
        $this->assertSame(AccountStatus::Invited, $member->status);
        $this->assertTrue($member->must_change_password);
        $this->assertTrue($member->assignedProjects()->whereKey($project->id)->exists());
    }

    public function test_security_headers_and_login_rate_limit_are_active(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong']);
        }
        $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    private function actors(string $companyName = 'Acme'): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $company = Company::create(['name' => $companyName, 'timezone' => 'America/New_York', 'currency' => 'USD']);
        $client = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::Client, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $project = Project::create(['company_id' => $company->id, 'name' => "$companyName Website", 'type' => 'website', 'status' => 'active', 'price' => 1000, 'currency' => 'USD']);

        return [$owner, $client, $company, $project];
    }
}
