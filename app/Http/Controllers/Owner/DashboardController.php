<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('owner.dashboard', [
            'leadCount' => Lead::whereNotIn('status', ['declined', 'archived'])->count(),
            'clientCount' => Company::count(),
            'activeProjectCount' => Project::whereIn('status', ['scheduled', 'active'])->count(),
            'waitingStages' => ProjectStage::with('project.company')->where('status', 'approval_required')->latest()->limit(6)->get(),
            'recentProjects' => Project::with('company')->latest()->limit(6)->get(),
        ]);
    }
}
