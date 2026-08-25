<x-layouts.app :title="$title">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            <p class="mt-1 text-sm text-slate-500">A chronological record of portal changes and decisions.</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @forelse($activities as $activity)
                <article class="border-b border-slate-100 p-5 last:border-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="font-medium">{{ $activity->description }}</p><p class="mt-1 text-sm text-slate-500">{{ str($activity->event)->replace(['.', '_'], ' ')->title() }} · {{ $activity->actor?->name ?? 'System' }}</p></div>
                        <time class="text-xs text-slate-400" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->diffForHumans() }}</time>
                    </div>
                </article>
            @empty
                <p class="p-6 text-sm text-slate-500">No activity has been recorded yet.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $activities->links() }}</div>
    </div>
</x-layouts.app>
