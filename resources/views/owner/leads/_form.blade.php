@php($lead = $lead ?? null)
<div class="grid gap-5 sm:grid-cols-2">
    <label class="text-sm font-medium">Company name<input class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="company_name" value="{{ old('company_name', $lead?->company_name) }}" required></label>
    <label class="text-sm font-medium">Contact name<input class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="contact_name" value="{{ old('contact_name', $lead?->contact_name) }}" required></label>
    <label class="text-sm font-medium">Email<input type="email" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="email" value="{{ old('email', $lead?->email) }}" required></label>
    <label class="text-sm font-medium">Phone<input class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="phone" value="{{ old('phone', $lead?->phone) }}"></label>
    <label class="text-sm font-medium">Source<input class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="source" value="{{ old('source', $lead?->source) }}" placeholder="Instagram, referral, website"></label>
    <label class="text-sm font-medium">Service<input class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="service" value="{{ old('service', $lead?->service) }}" placeholder="Website, web app, Telegram bot"></label>
    <label class="text-sm font-medium">Estimated budget<input type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="estimated_budget" value="{{ old('estimated_budget', $lead?->estimated_budget) }}"></label>
    <label class="text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@foreach(['new','contacted','discovery','proposal_sent','accepted','declined','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $lead?->status ?? 'new') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
    <label class="text-sm font-medium">Next contact<input type="date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5" name="next_contact_at" value="{{ old('next_contact_at', $lead?->next_contact_at?->format('Y-m-d')) }}"></label>
</div>
<label class="mt-5 block text-sm font-medium">Notes<textarea name="notes" rows="5" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('notes', $lead?->notes) }}</textarea></label>
<button class="mt-6 rounded-xl bg-indigo-600 px-5 py-2.5 font-medium text-white">Save lead</button>

