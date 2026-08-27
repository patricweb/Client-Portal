<x-layouts.app title="Work Items — Ikira Portal">
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-indigo-600">Internal team workspace</p>
            <h1 class="mt-1 text-3xl font-semibold">Work Items</h1>
            <p class="mt-2 text-slate-500">Private assignments for Ikira staff. Clients cannot access this area.</p>
        </div>
        <a href="{{ route('owner.work-items.create') }}" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white">New work item</a>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach(\App\Models\WorkItem::STATUSES as $key => $label)
            <a href="{{ route('owner.work-items.index', ['status' => $key]) }}" class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold">{{ $statusCounts[$key] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input name="search" value="{{ request('search') }}" placeholder="Search title or description" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All statuses</option>@foreach(\App\Models\WorkItem::STATUSES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
        <select name="discipline" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All areas</option>@foreach(\App\Models\WorkItem::DISCIPLINES as $key => $label)<option value="{{ $key }}" @selected(request('discipline') === $key)>{{ $label }}</option>@endforeach</select>
        <div class="flex gap-2"><select name="assigned_to" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All team members</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string) request('assigned_to') === (string) $member->id)>{{ $member->name }}</option>@endforeach</select><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Filter</button></div>
    </form>

    <div class="space-y-4">
        @forelse($workItems as $workItem)
            @php
                $statusStyle = match($workItem->status) {
                    'done' => 'bg-emerald-50 text-emerald-700',
                    'cancelled' => 'bg-red-50 text-red-700',
                    'review' => 'bg-violet-50 text-violet-700',
                    'in_progress' => 'bg-amber-50 text-amber-700',
                    default => 'bg-slate-100 text-slate-700',
                };
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusStyle }}">{{ \App\Models\WorkItem::STATUSES[$workItem->status] }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ \App\Models\WorkItem::DISCIPLINES[$workItem->discipline] }}</span>
                            @if($workItem->priority !== 'normal')<span class="rounded-full bg-orange-50 px-2.5 py-1 text-xs text-orange-700">{{ \App\Models\WorkItem::PRIORITIES[$workItem->priority] }}</span>@endif
                        </div>
                        <a href="{{ route('owner.work-items.edit', $workItem) }}" class="mt-3 block text-lg font-semibold hover:text-indigo-600">{{ $workItem->title }}</a>
                        <p class="mt-1 text-sm text-slate-500">{{ $workItem->project?->name ?? 'Internal / no project' }}@if($workItem->project) · {{ $workItem->project->company->name }}@endif</p>
                        @if($workItem->description)<p class="mt-3 text-sm text-slate-600">{{ str($workItem->description)->limit(220) }}</p>@endif
                    </div>
                    <dl class="grid min-w-52 gap-2 text-sm">
                        <div class="flex justify-between gap-5"><dt class="text-slate-500">Assigned</dt><dd>{{ $workItem->assignee?->name ?? 'Unassigned' }}</dd></div>
                        <div class="flex justify-between gap-5"><dt class="text-slate-500">Due</dt><dd>{{ $workItem->due_date?->format('M j, Y') ?? 'Not set' }}</dd></div>
                        @if($canViewFinancials && $workItem->price !== null)<div class="flex justify-between gap-5"><dt class="text-slate-500">Internal price</dt><dd>{{ $workItem->currency }} {{ number_format((float) $workItem->price, 2) }}</dd></div>@endif
                        <div class="flex justify-between gap-5"><dt class="text-slate-500">Channels</dt><dd>{{ str($workItem->channel_sync_status)->replace('_', ' ')->title() }}</dd></div>
                    </dl>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                    <form method="POST" action="{{ route('owner.work-items.status', $workItem) }}" class="flex gap-2">@csrf @method('PATCH')<select name="status" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">@foreach(\App\Models\WorkItem::STATUSES as $key => $label)<option value="{{ $key }}" @selected($workItem->status === $key)>{{ $label }}</option>@endforeach</select><button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white">Update status</button></form>
                    <a href="{{ route('owner.work-items.edit', $workItem) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs">Edit details</a>
                    <form method="POST" action="{{ route('owner.work-items.sync', $workItem) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs">Sync channels</button></form>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No internal work items match these filters.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $workItems->links() }}</div>
</x-layouts.app>
