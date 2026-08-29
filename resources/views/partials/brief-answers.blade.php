<section class="{{ $class ?? '' }} rounded-2xl border border-slate-200 bg-white p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">Submitted brief</h2>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">{{ str($brief->status)->replace('_', ' ')->title() }}</span>
    </div>
    @if($brief->submitted_at)<p class="mt-1 text-xs text-slate-500">Submitted {{ $brief->submitted_at->format('M j, Y H:i') }}</p>@endif
    <dl class="mt-5 grid gap-4 lg:grid-cols-2">
        @forelse($brief->answers->sortBy(fn ($answer) => $answer->field?->position ?? PHP_INT_MAX) as $answer)
            <div class="rounded-xl border border-slate-200 p-4">
                <dt class="text-sm font-medium">{{ $answer->field?->label ?? 'Question' }}</dt>
                <dd class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">{{ filled($answer->value) ? $answer->value : 'No answer provided.' }}</dd>
            </div>
        @empty
            <p class="text-sm text-slate-500">No answers have been saved yet.</p>
        @endforelse
    </dl>
</section>
