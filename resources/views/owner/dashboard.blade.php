<x-layouts.app title="Today — Ikira Client Portal">
    <div class="mb-7 flex items-start justify-between gap-4"><div><h1 class="text-3xl font-semibold">Today</h1><p class="mt-1 text-slate-500">Work that needs your attention now.</p></div><a href="{{ route('owner.projects.create') }}" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white">Create project</a></div>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach([['Active leads', $leadCount], ['Client companies', $clientCount], ['Active projects', $activeProjectCount]] as [$label, $value])
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-semibold">{{ $value }}</p></section>
        @endforeach
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Waiting for client approval</h2><div class="mt-4 divide-y divide-slate-100">
            @forelse($waitingStages as $stage)<a href="{{ route('owner.projects.show', $stage->project) }}" class="flex items-center justify-between gap-3 py-3"><div><p class="font-medium">{{ $stage->title }}</p><p class="text-sm text-slate-500">{{ $stage->project->company->name }} · {{ $stage->project->name }}</p></div><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-700">Waiting</span></a>@empty<p class="py-4 text-sm text-slate-500">Nothing is waiting for approval.</p>@endforelse
        </div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Recent projects</h2><div class="mt-4 divide-y divide-slate-100">
            @forelse($recentProjects as $project)<a href="{{ route('owner.projects.show', $project) }}" class="flex items-center justify-between gap-3 py-3"><div><p class="font-medium">{{ $project->name }}</p><p class="text-sm text-slate-500">{{ $project->company->name }}</p></div><span class="text-sm font-medium">{{ $project->progress }}%</span></a>@empty<p class="py-4 text-sm text-slate-500">Create your first project.</p>@endforelse
        </div></section>
    </div>
</x-layouts.app>

