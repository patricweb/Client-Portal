<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $projectQuery = Project::query();
        $stageQuery = ProjectStage::query();
        if (! in_array($user->role, [UserRole::Owner, UserRole::Admin], true)) {
            $projectIds = $user->assignedProjects()->select('projects.id');
            $projectQuery->whereIn('id', clone $projectIds);
            $stageQuery->whereIn('project_id', $projectIds);
        }

        return view('owner.dashboard', [
            'leadCount' => $user->hasPermission('manage_leads') ? Lead::whereNotIn('status', ['declined', 'archived'])->count() : 0,
            'clientCount' => $user->hasPermission('manage_clients') ? Company::count() : 0,
            'activeProjectCount' => (clone $projectQuery)->whereIn('status', ['scheduled', 'active'])->count(),
            'waitingStages' => $stageQuery->with('project.company')->where('status', 'approval_required')->latest()->limit(6)->get(),
            'recentProjects' => $projectQuery->with('company')->latest()->limit(6)->get(),
        ]);
    }
}
