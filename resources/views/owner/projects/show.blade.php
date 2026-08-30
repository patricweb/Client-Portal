<x-layouts.app :title="$project->name.' — Ikira Client Portal'">
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-medium text-indigo-600">{{ $project->company->name }}</p><h1 class="mt-1 text-3xl font-semibold">{{ $project->name }}</h1><p class="mt-1 text-slate-500">{{ str($project->type)->replace('_',' ')->title() }} · {{ str($project->status)->replace('_',' ')->title() }}</p></div><div class="flex items-center gap-4"><a href="{{ route('owner.payment-schedules.create', $project) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium">Payment schedule</a><div class="text-right"><p class="text-3xl font-semibold">{{ $project->progress }}%</p><p class="text-sm text-slate-500">Project progress</p></div></div></div>
    @if($project->brief)@include('partials.brief-answers', ['brief' => $project->brief, 'class' => 'mb-6'])@endif
    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <div><h2 class="font-semibold">Workflow</h2><p class="mt-1 text-sm text-slate-500">Add delivery stages and choose when the client must approve the result.</p></div>
            <div class="mt-5 space-y-4">
                @forelse($project->stages as $stage)
                    <div class="rounded-xl border border-slate-100 p-4">
                        <form method="POST" action="{{ route('owner.projects.stages.update', [$project, $stage]) }}" class="space-y-3">@csrf @method('PATCH')
                            <div class="grid gap-3 sm:grid-cols-[54px_1fr_190px] sm:items-end">
                                <div><p class="text-xs text-slate-500">Stage</p><p class="py-2 font-semibold">{{ $stage->position }}</p></div>
                                <label class="text-xs text-slate-500">Title<input name="title" value="{{ $stage->title }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"></label>
                                <label class="text-xs text-slate-500">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm text-slate-900">@foreach(['not_started','in_progress','approval_required','changes_requested','approved','completed','blocked'] as $status)<option value="{{ $status }}" @selected($stage->status === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
                            </div>
                            <label class="block text-xs text-slate-500">Description visible to client<textarea name="client_description" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900">{{ $stage->client_description }}</textarea></label>
                            <div class="grid gap-3 sm:grid-cols-[180px_1fr] sm:items-end">
                                <label class="text-xs text-slate-500">Due date<input type="date" name="due_date" value="{{ $stage->due_date?->format('Y-m-d') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"></label>
                                <div><input type="hidden" name="requires_approval" value="0"><label class="flex items-center gap-2 py-2 text-sm"><input type="checkbox" name="requires_approval" value="1" @checked($stage->requires_approval)> Client approval required</label></div>
                            </div>
                            <input name="override_reason" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900" placeholder="Reason required only if you approve on the client's behalf">
                            <div class="flex flex-wrap justify-between gap-3"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Save stage</button></div>
                        </form>
                        <form method="POST" action="{{ route('owner.projects.stages.destroy', [$project, $stage]) }}" class="mt-3 border-t border-slate-100 pt-3" onsubmit="return confirm('Delete this stage?')">@csrf @method('DELETE')<button class="text-sm text-red-600">Delete stage</button></form>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">No stages yet. Add the first stage below.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('owner.projects.stages.store', $project) }}" class="mt-6 space-y-3 rounded-xl border border-slate-200 p-4">@csrf
                <h3 class="font-medium">Add stage</h3>
                <label class="block text-xs text-slate-500">Title<input name="title" value="{{ old('title') }}" required maxlength="255" placeholder="For example: Design review" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"></label>
                <label class="block text-xs text-slate-500">Description visible to client<textarea name="client_description" rows="2" maxlength="5000" placeholder="What will be delivered or reviewed at this stage" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900">{{ old('client_description') }}</textarea></label>
                <div class="grid gap-3 sm:grid-cols-[180px_1fr] sm:items-end"><label class="text-xs text-slate-500">Due date<input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"></label><div><input type="hidden" name="requires_approval" value="0"><label class="flex items-center gap-2 py-2 text-sm"><input type="checkbox" name="requires_approval" value="1" @checked(old('requires_approval'))> Client approval required</label></div></div>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Add stage</button>
            </form>
        </section>
        <div class="space-y-6"><section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Project details</h2><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-4"><dt class="text-slate-500">Budget</dt><dd>{{ $project->currency }} {{ number_format($project->price, 2) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Target</dt><dd>{{ $project->target_completion_date?->format('M j, Y') ?? 'Not set' }}</dd></div><div><dt class="text-slate-500">Scope</dt><dd class="mt-1 whitespace-pre-line">{{ $project->scope ?: 'Not defined' }}</dd></div></dl></section>
        @if(auth()->user()->hasPermission('manage_work_items'))<section class="rounded-2xl border border-slate-200 bg-white p-5"><div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Internal work items</h2><a href="{{ route('owner.work-items.create', ['project_id' => $project->id]) }}" class="text-sm text-indigo-600">Add</a></div><div class="mt-4 space-y-3">@forelse($project->workItems->whereNull('archived_at')->take(5) as $workItem)<a href="{{ route('owner.work-items.edit', $workItem) }}" class="block rounded-lg border border-slate-100 p-3"><p class="text-sm font-medium">{{ $workItem->title }}</p><p class="mt-1 text-xs text-slate-500">{{ \App\Models\WorkItem::STATUSES[$workItem->status] }} · {{ $workItem->assignee?->name ?? 'Unassigned' }}</p></a>@empty<p class="text-sm text-slate-500">No internal assignments.</p>@endforelse</div></section>@endif
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Client files</h2><div class="mt-4 space-y-2">@forelse($project->attachments as $attachment)<a class="block truncate text-sm text-indigo-600" href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a>@empty<p class="text-sm text-slate-500">No files uploaded.</p>@endforelse</div><form method="POST" enctype="multipart/form-data" action="{{ route('owner.projects.attachments.store', $project) }}" class="mt-4">@csrf<input type="file" name="file" required class="block w-full text-sm"><button class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-sm text-white">Upload file</button></form></section></div>
    </div>
</x-layouts.app>
