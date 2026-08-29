<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        return view('client.projects.index', ['projects' => Project::with('stages')->where('company_id', $request->user()->company_id)->latest()->get()]);
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorizeCompany($request, $project);

        return view('client.projects.show', ['project' => $project->load(['stages.attachments', 'brief.answers.field', 'attachments'])]);
    }

    private function authorizeCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id === $request->user()->company_id, 404);
    }
}
