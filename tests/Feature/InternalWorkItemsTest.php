<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Jobs\SyncWorkItemChannels;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\WorkItemChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InternalWorkItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_create_private_project_work_item(): void
    {
        Queue::fake();
        [$owner, $client, $project] = $this->actors();
        $developer = $this->staff(UserRole::Developer);

        $this->actingAs($owner)->post(route('owner.work-items.store'), [
            'project_id' => $project->id,
            'assigned_to' => $developer->id,
            'title' => 'Build Telegram onboarding',
            'description' => 'Internal implementation notes.',
            'discipline' => 'telegram_bot',
            'status' => 'assigned',
            'priority' => 'high',
            'price' => '450.00',
            'currency' => 'USD',
            'due_date' => '2026-09-10',
        ])->assertRedirect();

        $workItem = WorkItem::firstOrFail();
        $this->assertSame($project->company_id, $workItem->company_id);
        $this->assertSame($developer->id, $workItem->assigned_to);
        $this->assertSame('450.00', $workItem->price);
        $this->assertDatabaseHas('activity_logs', ['subject_type' => WorkItem::class, 'subject_id' => $workItem->id, 'visibility' => 'internal']);
        Queue::assertPushed(SyncWorkItemChannels::class, fn ($job) => $job->workItemId === $workItem->id);

        $this->actingAs($client)->get('/owner/work-items')->assertForbidden();
        $this->actingAs($client)->get(route('client.dashboard'))->assertOk()->assertDontSee('Work Items');
    }

    public function test_staff_only_see_assigned_or_accessible_project_work_items(): void
    {
        [$owner, , $project] = $this->actors();
        [, , $otherProject] = $this->actors('Other');
        $developer = $this->staff(UserRole::Developer);
        $developer->assignedProjects()->attach($project, ['assigned_by' => $owner->id]);
        $visible = WorkItem::create(['project_id' => $project->id, 'company_id' => $project->company_id, 'created_by' => $owner->id, 'title' => 'Visible assignment']);
        $hidden = WorkItem::create(['project_id' => $otherProject->id, 'company_id' => $otherProject->company_id, 'title' => 'Private other assignment']);

        $this->actingAs($developer)->get(route('owner.work-items.index'))->assertOk()->assertSee($visible->title)->assertDontSee($hidden->title);
        $this->actingAs($developer)->get(route('owner.work-items.edit', $hidden))->assertNotFound();
    }

    public function test_developer_cannot_see_or_overwrite_internal_price(): void
    {
        Queue::fake();
        [$owner, , $project] = $this->actors();
        $developer = $this->staff(UserRole::Developer);
        $developer->assignedProjects()->attach($project, ['assigned_by' => $owner->id]);
        $workItem = WorkItem::create([
            'project_id' => $project->id, 'company_id' => $project->company_id, 'created_by' => $owner->id,
            'title' => 'Private price task', 'price' => 900, 'currency' => 'USD',
        ]);

        $this->actingAs($developer)->get(route('owner.work-items.edit', $workItem))->assertOk()->assertDontSee('Internal price');
        $this->actingAs($developer)->put(route('owner.work-items.update', $workItem), [
            'project_id' => $project->id,
            'title' => 'Updated task',
            'description' => 'No access to financial fields.',
            'discipline' => 'development',
            'status' => 'in_progress',
            'priority' => 'normal',
            'price' => 1,
            'currency' => 'EUR',
        ])->assertRedirect();

        $workItem->refresh();
        $this->assertSame('900.00', $workItem->price);
        $this->assertSame('USD', $workItem->currency);
        $this->assertNotNull($workItem->started_at);
    }

    public function test_status_update_is_audited_and_queued_for_channels(): void
    {
        Queue::fake();
        [$owner, , $project] = $this->actors();
        $workItem = WorkItem::create(['project_id' => $project->id, 'company_id' => $project->company_id, 'title' => 'Review item']);

        $this->actingAs($owner)->patch(route('owner.work-items.status', $workItem), ['status' => 'done'])->assertRedirect();

        $this->assertDatabaseHas('work_items', ['id' => $workItem->id, 'status' => 'done']);
        $this->assertNotNull($workItem->fresh()->completed_at);
        $this->assertDatabaseHas('activity_logs', ['subject_type' => WorkItem::class, 'subject_id' => $workItem->id, 'visibility' => 'internal']);
        Queue::assertPushed(SyncWorkItemChannels::class);
    }

    public function test_telegram_webhook_requires_secret_chat_and_allowed_user(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.webhook_secret' => 'test-webhook-secret',
            'services.telegram.work_chat_id' => '-100123',
            'services.telegram.allowed_user_ids' => ['77'],
        ]);
        $workItem = WorkItem::create(['title' => 'Telegram controlled item']);
        $payload = ['callback_query' => [
            'id' => 'callback-1', 'data' => "work:{$workItem->id}:in_progress",
            'from' => ['id' => 77], 'message' => ['chat' => ['id' => -100123]],
        ]];

        $this->postJson(route('integrations.telegram.webhook'), $payload)->assertForbidden();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret')->postJson(route('integrations.telegram.webhook'), $payload)->assertOk();

        $this->assertSame('in_progress', $workItem->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['event' => 'work_item.telegram_status_changed', 'visibility' => 'internal']);
        Queue::assertPushed(SyncWorkItemChannels::class);
    }

    public function test_telegram_webhook_rejects_unknown_user_and_wrong_chat(): void
    {
        config([
            'services.telegram.webhook_secret' => 'test-webhook-secret',
            'services.telegram.work_chat_id' => '-100123',
            'services.telegram.allowed_user_ids' => ['77'],
        ]);
        $workItem = WorkItem::create(['title' => 'Protected item']);
        $payload = ['callback_query' => [
            'id' => 'callback-2', 'data' => "work:{$workItem->id}:done",
            'from' => ['id' => 88], 'message' => ['chat' => ['id' => -100999]],
        ]];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret')->postJson(route('integrations.telegram.webhook'), $payload)->assertForbidden();
        $this->assertSame('new', $workItem->fresh()->status);
    }

    public function test_channel_service_creates_telegram_message_and_discord_forum_post(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 321]]),
            'discord.com/api/v10/channels/forum-1/threads' => Http::response(['id' => 'thread-1', 'message' => ['id' => 'message-1']]),
        ]);
        config([
            'services.telegram.bot_token' => 'telegram-token',
            'services.telegram.work_chat_id' => '-100123',
            'services.telegram.work_topic_id' => '2',
            'services.discord.bot_token' => 'discord-token',
            'services.discord.work_forums.web' => 'forum-1',
        ]);
        $workItem = WorkItem::create(['title' => 'Website build', 'discipline' => 'web', 'priority' => 'high']);

        app(WorkItemChannelService::class)->sync($workItem->fresh());

        $workItem->refresh();
        $this->assertSame('321', $workItem->telegram_message_id);
        $this->assertSame('thread-1', $workItem->discord_thread_id);
        $this->assertSame('message-1', $workItem->discord_message_id);
        $this->assertSame('synced', $workItem->channel_sync_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
        Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/v10/channels/forum-1/threads' && $request->hasHeader('Authorization', 'Bot discord-token'));
    }

    public function test_channel_sync_does_not_expose_price_by_default_and_accepts_unchanged_telegram_message(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'editMessageText')) {
                $this->assertStringNotContainsString('Internal price', (string) $request['text']);

                return Http::response(['ok' => false, 'description' => 'Bad Request: message is not modified'], 400);
            }

            return Http::response(['id' => 'thread-1']);
        });
        config([
            'services.telegram.bot_token' => 'telegram-token',
            'services.telegram.work_chat_id' => '-100123',
            'services.discord.bot_token' => '',
            'services.work_item_channels.include_price' => false,
        ]);
        $workItem = WorkItem::create([
            'title' => 'Unchanged item', 'price' => 500, 'currency' => 'USD',
            'telegram_chat_id' => '-100123', 'telegram_message_id' => '321',
        ]);

        app(WorkItemChannelService::class)->sync($workItem->fresh());

        $this->assertSame('synced', $workItem->fresh()->channel_sync_status);
    }

    public function test_archiving_preserves_record_and_queues_channel_update(): void
    {
        Queue::fake();
        [$owner] = $this->actors();
        $workItem = WorkItem::create(['title' => 'Archive me']);

        $this->actingAs($owner)->post(route('owner.work-items.archive', $workItem))->assertRedirect(route('owner.work-items.index'));

        $this->assertDatabaseHas('work_items', ['id' => $workItem->id]);
        $this->assertNotNull($workItem->fresh()->archived_at);
        Queue::assertPushed(SyncWorkItemChannels::class);
    }

    private function actors(string $companyName = 'Acme'): array
    {
        $owner = $this->staff(UserRole::Owner);
        $company = Company::create(['name' => $companyName, 'timezone' => 'America/New_York', 'currency' => 'USD']);
        $client = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::Client, 'status' => AccountStatus::Active, 'must_change_password' => false]);
        $project = Project::create(['company_id' => $company->id, 'name' => "{$companyName} Project", 'type' => 'website', 'status' => 'active', 'price' => 1000, 'currency' => 'USD']);

        return [$owner, $client, $project];
    }

    private function staff(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => AccountStatus::Active, 'must_change_password' => false]);
    }
}
