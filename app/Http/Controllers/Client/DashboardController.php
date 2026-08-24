<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $projects = Project::with(['stages', 'brief'])->where('company_id', $request->user()->company_id)->latest()->get();
        $currentProject = $projects->first(fn (Project $project) => in_array($project->status, ['awaiting_brief', 'active', 'scheduled'], true)) ?? $projects->first();

        return view('client.dashboard', compact('projects', 'currentProject'));
    }
}
