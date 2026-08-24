<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\BriefTemplate;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\WorkflowTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('owner.projects.index', ['projects' => Project::with('company')->latest()->paginate(20)]);
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

    public function show(Project $project): View
    {
        return view('owner.projects.show', ['project' => $project->load(['company.contacts', 'stages', 'brief.answers.field', 'attachments'])]);
    }

    public function updateStage(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        abort_unless($stage->project_id === $project->id, 404);
        $data = $request->validate(['status' => ['required', 'string'], 'due_date' => ['nullable', 'date']]);
        $stage->update($data + ['approved_at' => $data['status'] === 'approved' ? now() : null]);

        $completed = $project->stages()->whereIn('status', ['approved', 'completed'])->count();
        $total = max(1, $project->stages()->count());
        $project->update(['progress' => (int) round(($completed / $total) * 100)]);

        return back()->with('success', 'Project stage updated.');
    }
}
