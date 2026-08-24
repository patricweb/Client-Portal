<x-layouts.app title="New project — Ikira Client Portal"><div class="mx-auto max-w-4xl"><h1 class="text-3xl font-semibold">Create project</h1><p class="mt-1 text-slate-500">A selected workflow is copied into editable project stages.</p>
<form method="POST" action="{{ route('owner.projects.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">@csrf
    <div class="grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-medium">Client<select name="company_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"><option value="">Select company</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((string)old('company_id', request('company')) === (string)$company->id)>{{ $company->name }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Project name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Type<select name="type" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@foreach(['website','landing_page','e_commerce','web_application','telegram_bot','automation','maintenance_only','custom'] as $type)<option value="{{ $type }}">{{ str($type)->replace('_',' ')->title() }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Workflow<select name="workflow_template_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"><option value="">No template</option>@foreach($workflows as $workflow)<option value="{{ $workflow->id }}">{{ $workflow->name }} ({{ $workflow->stages->count() }} stages)</option>@endforeach</select></label>
        <label class="text-sm font-medium">Price<input type="number" name="price" min="0" step="0.01" value="{{ old('price', 0) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Currency<select name="currency" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@foreach(['USD','EUR','MDL'] as $currency)<option>{{ $currency }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@foreach(['draft','awaiting_brief','awaiting_contract','awaiting_payment','scheduled','active','on_hold'] as $status)<option value="{{ $status }}">{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Start date<input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Target completion<input type="date" name="target_completion_date" value="{{ old('target_completion_date') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
    </div>
    <label class="mt-5 block text-sm font-medium">Description<textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('description') }}</textarea></label>
    <label class="mt-5 block text-sm font-medium">Scope of work<textarea name="scope" rows="5" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('scope') }}</textarea></label>
    <label class="mt-5 block text-sm font-medium">Exclusions<textarea name="exclusions" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('exclusions') }}</textarea></label>
    <button class="mt-6 rounded-xl bg-indigo-600 px-5 py-3 font-medium text-white">Create project</button>
</form></div></x-layouts.app>

