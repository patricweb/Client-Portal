<?php

namespace App\Http\Controllers\Owner;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('owner.team.index', [
            'members' => User::where('role', '!=', UserRole::Client->value)->with('assignedProjects')->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'roles' => collect(UserRole::cases())->reject(fn (UserRole $role) => in_array($role, [UserRole::Owner, UserRole::Client], true)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class), Rule::notIn([UserRole::Owner->value, UserRole::Client->value])],
            'project_ids' => ['array'], 'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);
        $password = Str::password(18);
        $user = DB::transaction(function () use ($data, $password, $request) {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $password,
                'role' => $data['role'], 'status' => AccountStatus::Invited, 'must_change_password' => true,
                'notification_preferences' => ['portal' => true, 'email' => true, 'telegram' => false],
            ]);
            $user->assignedProjects()->syncWithPivotValues($data['project_ids'] ?? [], ['assigned_by' => $request->user()->id]);

            return $user;
        });
        app(ActivityLogger::class)->log('team.invited', 'Team member invited: '.$user->email, $user, 'internal', ['role' => $user->role->value]);
        session()->flash('temporary_credentials', ['email' => $user->email, 'password' => $password]);

        return back()->with('success', 'Team member invited.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role === UserRole::Client || $user->role === UserRole::Owner, 422);
        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class), Rule::notIn([UserRole::Owner->value, UserRole::Client->value])],
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'project_ids' => ['array'], 'project_ids.*' => ['integer', 'exists:projects,id'],
            'notify_portal' => ['nullable', 'boolean'], 'notify_email' => ['nullable', 'boolean'], 'notify_telegram' => ['nullable', 'boolean'],
        ]);
        $user->update([
            'role' => $data['role'], 'status' => $data['status'],
            'notification_preferences' => ['portal' => $request->boolean('notify_portal'), 'email' => $request->boolean('notify_email'), 'telegram' => $request->boolean('notify_telegram')],
        ]);
        $user->assignedProjects()->syncWithPivotValues($data['project_ids'] ?? [], ['assigned_by' => $request->user()->id]);
        app(ActivityLogger::class)->log('team.updated', 'Team member access updated: '.$user->email, $user, 'internal', ['role' => $user->role->value, 'status' => $user->status->value]);

        return back()->with('success', 'Team member updated.');
    }
}
