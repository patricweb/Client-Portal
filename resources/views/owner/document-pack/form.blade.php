<x-layouts.app title="Document builder - Ikira Portal">
    <div class="mb-7 flex flex-wrap justify-between gap-4"><div><p class="text-sm font-medium text-indigo-600">Business agreements & records</p><h1 class="mt-1 text-3xl font-semibold">{{ $document ? 'Create a revised version' : 'Create an agreement / record' }}</h1><p class="mt-2 max-w-3xl text-slate-500">Set the binding project terms, record an accepted change, or capture delivery acceptance in an exact PDF version.</p></div><a href="{{ route('owner.documents.create') }}" class="text-sm text-indigo-600">Advanced / legacy document</a></div>
    @if($profile->missing())<div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Provider details are incomplete. Drafts are available, but sending is blocked until Settings → Provider profile is completed and confirmed.</div>@endif
    @unless($document)
    <form method="GET" action="{{ route('owner.document-pack.create') }}" class="mb-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="font-semibold">1. Select document and client</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-medium">Document<select name="template" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">@foreach($templates as $templateKey => $template)<option value="{{ $templateKey }}" @selected($key === $templateKey)>{{ $template['title'] }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">Client<select name="company_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"><option value="">Select a client</option>@foreach($companies as $option)<option value="{{ $option->id }}" @selected($company?->id === $option->id)>{{ $option->name }}</option>@endforeach</select></label>
        </div>
        <p class="text-xs text-slate-500">Use a Project Services Agreement before work starts. A Project Change Order is only for changed scope, price or timing. A Delivery Acceptance Record closes the project review. Invoices stay in Billing.</p>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Continue</button>
    </form>
    @endunless
    @if($company)
    <form method="GET" action="{{ $document ? route('owner.document-pack.edit', $document) : route('owner.document-pack.create') }}" class="mb-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
        <input type="hidden" name="template" value="{{ $key }}"><input type="hidden" name="company_id" value="{{ $company->id }}">
        <h2 class="font-semibold">{{ $company->name }} / {{ $definition['title'] }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-medium">Project<select name="project_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5" @disabled($document)><option value="">Select project</option>@foreach($projects as $option)<option value="{{ $option->id }}" @selected($project?->id === $option->id)>{{ $option->name }}</option>@endforeach</select></label>
            @if($definition['parent'])<label class="text-sm font-medium">Accepted Project Services Agreement<select name="parent_document_id" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"><option value="">Link later (draft only)</option>@foreach($parents as $option)<option value="{{ $option->id }}" @selected($parent?->id === $option->id)>{{ $option->document_number ?: $option->title }} / v{{ $option->current_version }}</option>@endforeach</select></label>@endif
        </div>
        <p class="text-xs text-slate-500">Loading a different selection replaces unsaved field edits. Save a draft first if needed.</p>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Load project details</button>
    </form>
    <form method="POST" action="{{ $document ? route('owner.document-pack.update', $document) : route('owner.document-pack.store') }}" class="space-y-6">@csrf @if($document) @method('PUT') @endif
        <input type="hidden" name="template" value="{{ $key }}"><input type="hidden" name="company_id" value="{{ $company->id }}"><input type="hidden" name="project_id" value="{{ $project?->id }}"><input type="hidden" name="parent_document_id" value="{{ $parent?->id }}"><input type="hidden" name="source_hash" value="{{ $prepared['source_hash'] }}"><input type="hidden" name="base_version" value="{{ $document?->current_version }}">
        <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">2. Business details</h2>
            <label class="block text-sm font-medium">Document title<input name="title" required value="{{ old('title', $document?->title ?? $definition['title'].($project ? ' - '.$project->name : '')) }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label>
            <div class="grid gap-4 md:grid-cols-2"><label class="text-sm font-medium">Agreed project price (USD)<input type="number" step="0.01" min="0" name="price" value="{{ old('price', $commercial['price'] ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label><label class="text-sm font-medium">Target delivery date<input type="date" name="target_date" value="{{ old('target_date', $commercial['target_date'] ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label></div>
            <p class="text-xs text-slate-500">Confirm the parties, scope, price, payment terms and dates before sending. A Project Change Order records the new full project total, not only the added amount.</p>
            <p class="text-xs text-slate-500">Provider: {{ $profile->details['legal_name'] }}. Complete every highlighted field; use “None” only when it is accurate. The client confirms the exact PDF version in the portal.</p>
        </section>
        @foreach($prepared['sections'] as $section => $ids)
            @php($editable = collect($ids)->filter(fn ($id) => ! $prepared['fields'][$id]['automatic']))
            @if($editable->isNotEmpty())<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="mb-5 font-semibold">{{ $section }}</h2><div class="grid gap-5 lg:grid-cols-2">
                @foreach($editable as $id) @php($field = $prepared['fields'][$id])
                    <label class="block text-sm font-medium">{{ $field['label'] }}<textarea name="fields[{{ $id }}]" maxlength="{{ $field['max_length'] }}" rows="{{ strlen($field['value']) > 100 ? 4 : 2 }}" class="mt-1 w-full rounded-lg border {{ blank($field['value']) ? 'border-amber-300' : 'border-slate-300' }} p-2.5">{{ $field['value'] }}</textarea><span class="mt-1 block text-xs font-normal text-slate-500">{{ $field['context'] }}</span></label>
                @endforeach
            </div></section>@endif
        @endforeach
        @if($key === 'delivery_confirmation')<section class="rounded-2xl border border-slate-200 bg-white p-6"><label class="block text-sm font-medium">Agreed minor items and completion dates (optional)<textarea name="minor_items" rows="4" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5">{{ old('minor_items', $document?->currentVersionRecord()?->snapshot['minor_items'] ?? '') }}</textarea></label><p class="mt-2 text-xs text-slate-500">Enter only a precise list you agree to finish. Leaving it blank disables “Confirm with minor items”.</p></section>@endif
        <div class="sticky bottom-3 flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">Saving creates a draft, not a signature or a client notification.</p><button class="shrink-0 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-medium text-white">Save draft & preview</button></div>
    </form>
    @endif
</x-layouts.app>
