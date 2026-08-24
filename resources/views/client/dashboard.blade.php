<x-layouts.app title="Home — Ikira Client Portal">
    <div class="mb-7"><h1 class="text-3xl font-semibold">Welcome back, {{ str(auth()->user()->name)->before(' ') }}</h1><p class="mt-1 text-slate-500">Here is the latest on your work with Ikira.</p></div>
    @if($currentProject)
        @php($currentStage = $currentProject->currentStage())
        @if($currentProject->brief?->status === 'draft')
            <section class="mb-6 rounded-2xl bg-indigo-600 p-6 text-white"><p class="text-xs font-semibold uppercase tracking-wider text-indigo-200">Action required</p><h2 class="mt-2 text-xl font-semibold">Complete your project brief</h2><p class="mt-1 text-indigo-100">Your answers help Ikira confirm the scope and begin the project.</p><a href="{{ route('client.brief.edit', $currentProject) }}" class="mt-5 inline-block rounded-xl bg-white px-4 py-2.5 text-sm font-medium text-indigo-700">Open brief</a></section>
        @elseif($currentStage?->status === 'approval_required')
            <section class="mb-6 rounded-2xl bg-indigo-600 p-6 text-white"><p class="text-xs font-semibold uppercase tracking-wider text-indigo-200">Action required</p><h2 class="mt-2 text-xl font-semibold">Review {{ $currentStage->title }}</h2><p class="mt-1 text-indigo-100">This stage is ready for your review.</p><a href="{{ route('client.projects.show', $currentProject) }}" class="mt-5 inline-block rounded-xl bg-white px-4 py-2.5 text-sm font-medium text-indigo-700">Review project</a></section>
        @else
            <section class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6"><p class="font-semibold text-emerald-900">No action is required from you right now.</p><p class="mt-1 text-sm text-emerald-700">We are currently working on {{ $currentStage?->title ?? 'your project' }}.</p></section>
        @endif
        <div class="grid gap-4 sm:grid-cols-3"><section class="rounded-2xl border border-slate-200 bg-white p-5 sm:col-span-2"><p class="text-sm text-slate-500">Current project</p><h2 class="mt-2 text-xl font-semibold">{{ $currentProject->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $currentStage?->title ?? str($currentProject->status)->replace('_',' ')->title() }}</p><div class="mt-5 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width:{{ $currentProject->progress }}%"></div></div><p class="mt-2 text-sm text-slate-500">{{ $currentProject->progress }}% complete</p></section><section class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Target completion</p><p class="mt-2 text-xl font-semibold">{{ $currentProject->target_completion_date?->format('M j, Y') ?? 'To be confirmed' }}</p><a href="{{ route('client.projects.show', $currentProject) }}" class="mt-6 inline-block text-sm font-medium text-indigo-600">View project →</a></section></div>
    @else
        <section class="rounded-2xl border border-slate-200 bg-white p-8 text-center"><h2 class="text-xl font-semibold">Your workspace is ready</h2><p class="mt-2 text-slate-500">Your project will appear here after Ikira creates it.</p></section>
    @endif
</x-layouts.app>

