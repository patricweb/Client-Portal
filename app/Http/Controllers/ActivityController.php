<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function owner(Request $request): View
    {
        $query = ActivityLog::with(['actor', 'company', 'project'])->latest('created_at');
        if (! in_array($request->user()->role, [UserRole::Owner, UserRole::Admin], true)) {
            $query->whereIn('project_id', $request->user()->assignedProjects()->select('projects.id'));
        }

        return view('activity.index', ['activities' => $query->paginate(50), 'ownerView' => true, 'title' => 'Activity Log']);
    }

    public function client(Request $request): View
    {
        return view('activity.index', ['activities' => ActivityLog::with(['actor', 'project'])
            ->where('company_id', $request->user()->company_id)->where('visibility', 'public')->latest('created_at')->paginate(50), 'ownerView' => false, 'title' => 'Project Updates']);
    }
}
