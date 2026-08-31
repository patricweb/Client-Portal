<x-layouts.app :title="'Edit '.$company->name.' - Ikira Client Portal'">
<div class="mx-auto max-w-4xl"><div class="mb-7"><p class="text-sm font-medium text-indigo-600">Client company</p><h1 class="mt-1 text-3xl font-semibold">Legal and authorized-contact details</h1><p class="mt-2 text-slate-500">New agreement versions use these values. Existing issued PDFs remain unchanged.</p></div>
<form method="POST" action="{{ route('owner.companies.update', $company) }}" class="space-y-7">@csrf @method('PUT')
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Business identity</h2><div class="mt-5 grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-medium">Display name<input name="name" value="{{ old('name', $company->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Legal company name<input name="billing_name" value="{{ old('billing_name', $company->billing_name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Jurisdiction<input name="jurisdiction" value="{{ old('jurisdiction', $company->jurisdiction) }}" required placeholder="For example: Delaware, United States" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Company email<input type="email" name="email" value="{{ old('email', $company->email) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Phone<input name="phone" value="{{ old('phone', $company->phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Website<input type="url" name="website" value="{{ old('website', $company->website) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Timezone<input name="timezone" value="{{ old('timezone', $company->timezone) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Currency<select name="currency" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@foreach(['USD','EUR','MDL'] as $currency)<option @selected(old('currency', $company->currency) === $currency)>{{ $currency }}</option>@endforeach</select></label>
    </div><label class="mt-5 block text-sm font-medium">Business address<textarea name="billing_address" rows="3" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('billing_address', $company->billing_address) }}</textarea></label></section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Authorized representative</h2><p class="mt-1 text-sm text-slate-500">This person must be authorized to accept project terms for the client business.</p><div class="mt-5 grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-medium">Name<input name="contact_name" value="{{ old('contact_name', $contact->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Email<input type="email" name="contact_email" value="{{ old('contact_email', $contact->email) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Job title / authority<input name="contact_job_title" value="{{ old('contact_job_title', $contact->job_title) }}" required placeholder="For example: Owner or CEO" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Phone<input name="contact_phone" value="{{ old('contact_phone', $contact->phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
    </div></section>
    <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white">Save client details</button>
</form></div>
</x-layouts.app>
