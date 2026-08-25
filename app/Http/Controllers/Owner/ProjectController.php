<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\BriefTemplate;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\WorkflowTemplate;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
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

        return view('owner.projects.show', ['project' => $project->load(['company.contacts', 'stages', 'brief.answers.field', 'attachments'])]);
    }

    public function updateStage(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        abort_unless($stage->project_id === $project->id, 404);
        abort_unless($request->user()->canAccessProject($project), 404);
        $data = $request->validate([
            'status' => ['required', 'string'],
            'due_date' => ['nullable', 'date'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $isOverride = $stage->requires_approval && in_array($data['status'], ['approved', 'completed'], true);
        if ($isOverride) {
            $request->validate(['override_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        }

        $stage->update([
            'status' => $data['status'],
            'due_date' => $data['due_date'] ?? null,
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

        $completed = $project->stages()->whereIn('status', ['approved', 'completed'])->count();
        $total = max(1, $project->stages()->count());
        $project->update(['progress' => (int) round(($completed / $total) * 100)]);

        return back()->with('success', 'Project stage updated.');
    }
}
