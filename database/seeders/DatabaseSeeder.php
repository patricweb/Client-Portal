<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\BriefTemplate;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('IKIRA_OWNER_EMAIL', 'owner@ikira.company')],
            [
                'name' => env('IKIRA_OWNER_NAME', 'Ikira Owner'),
                'password' => env('IKIRA_OWNER_PASSWORD', 'ChangeMe123!'),
                'role' => UserRole::Owner,
                'status' => AccountStatus::Active,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );

        $workflows = [
            'website' => ['Brief', 'Content', 'Design', 'Development', 'Testing', 'Client Review', 'Launch', 'Care & Support'],
            'web_application' => ['Requirements', 'UX & Architecture', 'Development', 'Integrations', 'Testing', 'Client Review', 'Deployment', 'Support'],
            'telegram_bot' => ['Requirements', 'Conversation Flow', 'Development', 'Integrations', 'Testing', 'Client Review', 'Deployment', 'Support'],
            'custom' => ['Discovery', 'Planning', 'Development', 'Testing', 'Client Review', 'Delivery'],
        ];

        foreach ($workflows as $type => $stages) {
            $workflow = WorkflowTemplate::updateOrCreate(
                ['project_type' => $type],
                ['name' => str($type)->replace('_', ' ')->title().' Workflow', 'is_active' => true]
            );
            foreach ($stages as $position => $title) {
                $workflow->stages()->updateOrCreate(
                    ['position' => $position + 1],
                    ['title' => $title, 'client_description' => "We are working on the {$title} stage.", 'requires_approval' => in_array($title, ['Design', 'Client Review'])]
                );
            }
        }

        $questions = [
            ['company_overview', 'Tell us about your company and what you offer.', true],
            ['project_goals', 'What should this project achieve?', true],
            ['target_audience', 'Who is your target audience?', true],
            ['competitors', 'Who are your main competitors?', false],
            ['functionality', 'What functionality do you need?', true],
            ['references', 'Share examples or references you like.', false],
            ['content', 'Who will provide text, images and other content?', true],
            ['additional_notes', 'Anything else we should know?', false],
        ];

        foreach (array_keys($workflows) as $type) {
            $template = BriefTemplate::updateOrCreate(
                ['project_type' => $type],
                ['name' => str($type)->replace('_', ' ')->title().' Brief', 'is_active' => true]
            );
            foreach ($questions as $position => [$key, $label, $required]) {
                $template->fields()->updateOrCreate(
                    ['key' => $key],
                    ['label' => $label, 'type' => 'textarea', 'is_required' => $required, 'position' => $position + 1]
                );
            }
        }

        DocumentTemplate::updateOrCreate(
            ['name' => 'Standard Proposal'],
            [
                'type' => 'proposal',
                'is_active' => true,
                'content' => '<h2>Project proposal for {{company.name}}</h2><p>Prepared for {{contact.name}} on {{today}}.</p><h3>Project</h3><p>{{project.name}}</p><p>{{project.description}}</p><h3>Scope</h3><p>{{project.scope}}</p><h3>Exclusions</h3><p>{{project.exclusions}}</p><h3>Investment</h3><p>{{project.price}}</p><p>This template must be reviewed and completed before it is sent.</p>',
            ]
        );

        DocumentTemplate::updateOrCreate(
            ['name' => 'Standard Contract'],
            [
                'type' => 'contract',
                'is_active' => true,
                'content' => '<h2>Service agreement</h2><p>This agreement is between Ikira Company and {{company.billing_name}} for {{project.name}}.</p><h3>Scope</h3><p>{{project.scope}}</p><h3>Fees</h3><p>{{project.price}}</p><p><strong>Legal review required:</strong> replace this placeholder with counsel-approved terms before production use.</p>',
            ]
        );
    }
}
