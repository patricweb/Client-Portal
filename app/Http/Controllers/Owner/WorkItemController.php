<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\SyncWorkItemChannels;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkItemController extends Controller
{
    public function index(Request $request): View
    {
        $base = WorkItem::query()->visibleTo($request->user())->whereNull('archived_at');
        $query = (clone $base)->with(['project.company', 'assignee', 'creator'])->latest();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(fn (Builder $match) => $match->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }
        if (array_key_exists((string) $request->input('status'), WorkItem::STATUSES)) {
            $query->where('status', $request->input('status'));
        }
        if (array_key_exists((string) $request->input('discipline'), WorkItem::DISCIPLINES)) {
            $query->where('discipline', $request->input('discipline'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        $statusCounts = (clone $base)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('owner.work-items.index', [
            'workItems' => $query->paginate(20)->withQueryString(),
            'statusCounts' => $statusCounts,
            'members' => $this->members(),
            'canViewFinancials' => $request->user()->hasPermission('view_work_item_financials'),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $project = $this->projectFor($request, $data['project_id'] ?? null);
        $assignee = $this->assignee($data['assigned_to'] ?? null);

        $workItem = DB::transaction(function () use ($request, $data, $project) {
            return WorkItem::create($this->attributes($request, $data, $project) + ['created_by' => $request->user()->id]);
        });

        $this->notifyAssignee($assignee, $workItem, 'New work item assigned');
        SyncWorkItemChannels::dispatch($workItem->id)->afterCommit();

        return redirect()->route('owner.work-items.edit', $workItem)->with('success', 'Internal work item created.');
    }

    public function edit(Request $request, WorkItem $workItem): View
    {
        $this->authorizeAccess($request, $workItem);

        return $this->form($request, $workItem->load(['project.company', 'assignee']));
    }

    public function update(Request $request, WorkItem $workItem): RedirectResponse
    {
        $this->authorizeAccess($request, $workItem);
        abort_if($workItem->archived_at, 422, 'Archived work items cannot be edited.');
        $data = $this->validated($request);
        $project = $this->projectFor($request, $data['project_id'] ?? null);
        $assignee = $this->assignee($data['assigned_to'] ?? null);
        $previousAssignee = $workItem->assigned_to;
        $previousStatus = $workItem->status;

        $workItem->update($this->attributes($request, $data, $project, $workItem) + $this->statusTimes($data['status'], $previousStatus));
        if ($assignee && $assignee->id !== $previousAssignee) {
            $this->notifyAssignee($assignee, $workItem, 'Work item assigned to you');
        }
        SyncWorkItemChannels::dispatch($workItem->id)->afterCommit();

        return back()->with('success', 'Work item updated.');
    }

    public function updateStatus(Request $request, WorkItem $workItem): RedirectResponse
    {
        $this->authorizeAccess($request, $workItem);
        abort_if($workItem->archived_at, 422, 'Archived work items cannot be changed.');
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(WorkItem::STATUSES))]]);
        $previous = $workItem->status;
        $workItem->update(['status' => $data['status']] + $this->statusTimes($data['status'], $previous));
        SyncWorkItemChannels::dispatch($workItem->id)->afterCommit();

        return back()->with('success', 'Work item status updated.');
    }

    public function sync(Request $request, WorkItem $workItem): RedirectResponse
    {
        $this->authorizeAccess($request, $workItem);
        $workItem->forceFill(['channel_sync_status' => 'pending', 'channel_sync_error' => null])->saveQuietly();
        SyncWorkItemChannels::dispatch($workItem->id);

        return back()->with('success', 'Channel synchronization queued.');
    }

    public function archive(Request $request, WorkItem $workItem): RedirectResponse
    {
        $this->authorizeAccess($request, $workItem);
        abort_if($workItem->archived_at, 422, 'Work item is already archived.');
        $workItem->update(['archived_at' => now()]);
        SyncWorkItemChannels::dispatch($workItem->id)->afterCommit();

        return redirect()->route('owner.work-items.index')->with('success', 'Work item archived.');
    }

    private function form(Request $request, ?WorkItem $workItem = null): View
    {
        return view('owner.work-items.form', [
            'workItem' => $workItem,
            'projects' => $this->projects($request),
            'members' => $this->members(),
            'canViewFinancials' => $request->user()->hasPermission('view_work_item_financials'),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNot('role', UserRole::Client->value)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'discipline' => ['required', Rule::in(array_keys(WorkItem::DISCIPLINES))],
            'status' => ['required', Rule::in(array_keys(WorkItem::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(WorkItem::PRIORITIES))],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'MDL'])],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    private function attributes(Request $request, array $data, ?Project $project, ?WorkItem $workItem = null): array
    {
        return [
            'company_id' => $project?->company_id,
            'project_id' => $project?->id,
            'assigned_to' => $data['assigned_to'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'discipline' => $data['discipline'],
            'status' => $data['status'],
            'priority' => $data['priority'],
            'price' => $request->user()->hasPermission('view_work_item_financials') ? ($data['price'] ?? null) : $workItem?->price,
            'currency' => $request->user()->hasPermission('view_work_item_financials') ? $data['currency'] : ($workItem?->currency ?? 'USD'),
            'due_date' => $data['due_date'] ?? null,
            'channel_sync_status' => 'pending',
            'channel_sync_error' => null,
        ];
    }

    private function statusTimes(string $status, string $previous): array
    {
        $times = [];
        if ($status === 'in_progress' && $previous !== 'in_progress') {
            $times['started_at'] = now();
        }
        if ($status === 'done' && $previous !== 'done') {
            $times['completed_at'] = now();
        } elseif ($status !== 'done' && $previous === 'done') {
            $times['completed_at'] = null;
        }

        return $times;
    }

    private function projectFor(Request $request, ?int $projectId): ?Project
    {
        if (! $projectId) {
            return null;
        }
        $project = Project::findOrFail($projectId);
        abort_unless($request->user()->canAccessProject($project), 404);

        return $project;
    }

    private function assignee(?int $userId): ?User
    {
        return $userId ? User::where('role', '!=', UserRole::Client->value)->findOrFail($userId) : null;
    }

    private function authorizeAccess(Request $request, WorkItem $workItem): void
    {
        abort_unless($workItem->isVisibleTo($request->user()), 404);
    }

    private function projects(Request $request)
    {
        $query = Project::with('company')->orderBy('name');
        if (! in_array($request->user()->role, [UserRole::Owner, UserRole::Admin], true)) {
            $query->whereHas('teamMembers', fn (Builder $members) => $members->whereKey($request->user()->id));
        }

        return $query->get();
    }

    private function members()
    {
        return User::where('role', '!=', UserRole::Client->value)->orderBy('name')->get();
    }

    private function notifyAssignee(?User $assignee, WorkItem $workItem, string $title): void
    {
        if (! $assignee) {
            return;
        }
        app(NotificationService::class)->send(
            $assignee,
            'work_item_assigned',
            'important_update',
            $title,
            $workItem->title,
            route('owner.work-items.edit', $workItem),
            false,
        );
    }
}
