<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\BriefTemplate;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const STAGE_STATUSES = [
        'not_started', 'in_progress', 'approval_required', 'changes_requested',
        'approved', 'completed', 'blocked',
    ];

    public function index(Request $request): View
    {
        $query = Project::with('company')->latest();
        if (! in_array($request->user()->role, [UserRole::Owner, UserRole::Admin], true)) {
            $query->whereHas('teamMembers', fn ($team) => $team->whereKey($request->user()->id));
        }

        return view('owner.projects.index', ['projects' => $query->paginate(20)]);
    }

    public function create(): View
    {
        return view('owner.projects.create', [
            'companies' => Company::orderBy('name')->get(),
            'workflows' => WorkflowTemplate::with('stages')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'], 'workflow_template_id' => ['nullable', 'exists:workflow_templates,id'],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'], 'scope' => ['nullable', 'string'], 'exclusions' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'], 'currency' => ['required', Rule::in(['USD', 'EUR', 'MDL'])],
            'status' => ['required', 'string'], 'start_date' => ['nullable', 'date'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project = DB::transaction(function () use ($data) {
            $project = Project::create($data);
            $workflow = isset($data['workflow_template_id']) ? WorkflowTemplate::with('stages')->find($data['workflow_template_id']) : null;
            foreach ($workflow?->stages ?? [] as $stage) {
                $project->stages()->create($stage->only(['title', 'client_description', 'position', 'requires_approval']));
            }

            $briefTemplate = BriefTemplate::where('project_type', $data['type'])->where('is_active', true)->first();
            $project->brief()->create(['brief_template_id' => $briefTemplate?->id, 'status' => 'draft']);

            return $project;
        });

        return redirect()->route('owner.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless($request->user()->canAccessProject($project), 404);

        return view('owner.projects.show', ['project' => $project->load(['company.contacts', 'stages', 'brief.answers.field', 'attachments', 'workItems.assignee'])]);
    }

    public function storeStage(Request $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()->canAccessProject($project), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'requires_approval' => ['required', 'boolean'],
        ]);

        $stage = $project->stages()->create($data + [
            'position' => ((int) $project->stages()->max('position')) + 1,
            'status' => 'not_started',
        ]);
        app(ActivityLogger::class)->log(
            'project_stage.created',
            'Project stage created: '.$stage->title,
            $stage,
            'internal',
            [],
            $project->company_id,
            $project->id,
        );
        $this->recalculateProgress($project);

        return back()->with('success', 'Project stage added.');
    }

    public function updateStage(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        abort_unless($stage->project_id === $project->id, 404);
        abort_unless($request->user()->canAccessProject($project), 404);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'client_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(self::STAGE_STATUSES)],
            'due_date' => ['nullable', 'date'],
            'requires_approval' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $requiresApproval = $data['status'] === 'approval_required'
            || (bool) ($data['requires_approval'] ?? $stage->requires_approval);
        $isOverride = $requiresApproval && in_array($data['status'], ['approved', 'completed'], true);
        if ($isOverride) {
            $request->validate(['override_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        }

        $wasWaitingForApproval = $stage->status === 'approval_required';
        $stage->update([
            'title' => $data['title'] ?? $stage->title,
            'client_description' => array_key_exists('client_description', $data) ? $data['client_description'] : $stage->client_description,
            'status' => $data['status'],
            'due_date' => $data['due_date'] ?? null,
            'requires_approval' => $requiresApproval,
            'approved_at' => $data['status'] === 'approved' ? now() : null,
        ]);
        if ($isOverride) {
            app(ActivityLogger::class)->log(
                'project_stage.owner_override',
                'Approval requirement overridden: '.$data['override_reason'],
                $stage,
                'internal',
                ['reason' => $data['override_reason']],
                $project->company_id,
                $project->id,
            );
        }
        if (! $wasWaitingForApproval && $data['status'] === 'approval_required') {
            app(NotificationService::class)->send(
                User::where('company_id', $project->company_id)->where('role', 'client')->get(),
                'stage_approval_requested',
                'action_required',
                'Project stage ready for approval',
                "{$project->name} — {$stage->title}",
                route('client.projects.show', $project),
                false,
            );
        }

        $this->recalculateProgress($project);

        return back()->with('success', 'Project stage updated.');
    }

    public function destroyStage(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        abort_unless($stage->project_id === $project->id, 404);
        abort_unless($request->user()->canAccessProject($project), 404);
        if ($stage->approvals()->exists()) {
            return back()->withErrors(['stage' => 'A stage with a recorded client decision cannot be deleted.']);
        }

        app(ActivityLogger::class)->log(
            'project_stage.deleted',
            'Project stage deleted: '.$stage->title,
            $stage,
            'internal',
            [],
            $project->company_id,
            $project->id,
        );
        $stage->delete();
        $project->stages()->orderBy('position')->get()->each(
            fn (ProjectStage $remainingStage, int $index) => $remainingStage->update(['position' => $index + 1])
        );
        $this->recalculateProgress($project);

        return back()->with('success', 'Project stage deleted.');
    }

    private function recalculateProgress(Project $project): void
    {
        $total = $project->stages()->count();
        $completed = $project->stages()->whereIn('status', ['approved', 'completed'])->count();
        $project->update(['progress' => $total === 0 ? 0 : (int) round(($completed / $total) * 100)]);
    }
}
