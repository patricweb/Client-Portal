<x-layouts.app :title="($workItem ? 'Edit' : 'New').' Work Item — Ikira Portal'">
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-indigo-600">Internal team workspace</p>
            <h1 class="mt-1 text-3xl font-semibold">{{ $workItem ? 'Edit work item' : 'New work item' }}</h1>
            <p class="mt-2 text-slate-500">Visible only to authorized Ikira team members.</p>
        </div>
        <a href="{{ route('owner.work-items.index') }}" class="text-sm text-indigo-600">Back to work items</a>
    </div>

    <form method="POST" action="{{ $workItem ? route('owner.work-items.update', $workItem) : route('owner.work-items.store') }}" class="grid gap-6 xl:grid-cols-[1fr_340px]">
        @csrf @if($workItem) @method('PUT') @endif
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            <label class="block text-sm font-medium">Title<input name="title" required maxlength="255" value="{{ old('title', $workItem?->title) }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label>
            <label class="block text-sm font-medium">Description<textarea name="description" rows="8" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">{{ old('description', $workItem?->description) }}</textarea></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium">Project<select name="project_id" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"><option value="">Internal / no project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id', $workItem?->project_id ?? request('project_id')) === (string) $project->id)>{{ $project->name }} — {{ $project->company->name }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Assigned to<select name="assigned_to" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"><option value="">Unassigned</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string) old('assigned_to', $workItem?->assigned_to) === (string) $member->id)>{{ $member->name }} — {{ str($member->role->value)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Area<select name="discipline" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">@foreach(\App\Models\WorkItem::DISCIPLINES as $key => $label)<option value="{{ $key }}" @selected(old('discipline', $workItem?->discipline ?? 'other') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Priority<select name="priority" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">@foreach(\App\Models\WorkItem::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected(old('priority', $workItem?->priority ?? 'normal') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Status<select name="status" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">@foreach(\App\Models\WorkItem::STATUSES as $key => $label)<option value="{{ $key }}" @selected(old('status', $workItem?->status ?? 'new') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Due date<input type="date" name="due_date" value="{{ old('due_date', $workItem?->due_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label>
            </div>
            @if($canViewFinancials)
                <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Internal price (optional)<input type="number" min="0" step="0.01" name="price" value="{{ old('price', $workItem?->price) }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label><label class="text-sm font-medium">Currency<select name="currency" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">@foreach(['USD','EUR','MDL'] as $currency)<option @selected(old('currency', $workItem?->currency ?? 'USD') === $currency)>{{ $currency }}</option>@endforeach</select></label></div>
            @else
                <input type="hidden" name="currency" value="{{ $workItem?->currency ?? 'USD' }}">
            @endif
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white">{{ $workItem ? 'Save changes' : 'Create work item' }}</button>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Channel synchronization</h2>
                <p class="mt-2 text-sm text-slate-500">After saving, this item is queued for the configured private Telegram topic and Discord forum.</p>
                @if($workItem)
                    <dl class="mt-4 space-y-2 text-sm"><div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd>{{ str($workItem->channel_sync_status)->replace('_', ' ')->title() }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Last sync</dt><dd>{{ $workItem->last_channel_sync_at?->format('M j, H:i') ?? 'Never' }}</dd></div></dl>
                    @if($workItem->channel_sync_error)<p class="mt-3 break-words rounded-lg bg-red-50 p-3 text-xs text-red-700">{{ $workItem->channel_sync_error }}</p>@endif
                @endif
            </section>
            @if($workItem && !$workItem->archived_at)
                <section class="rounded-2xl border border-red-200 bg-white p-5"><h2 class="font-semibold">Archive</h2><p class="mt-2 text-sm text-slate-500">Keeps the history and marks connected channel messages as archived.</p><form method="POST" action="{{ route('owner.work-items.archive', $workItem) }}" class="mt-4" onsubmit="return confirm('Archive this work item?')">@csrf<button type="submit" class="rounded-lg border border-red-300 px-3 py-2 text-sm text-red-700">Archive work item</button></form></section>
            @endif
        </aside>
    </form>
</x-layouts.app>
