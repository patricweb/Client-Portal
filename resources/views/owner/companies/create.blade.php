<x-layouts.app title="New client — Ikira Client Portal">
<div class="mx-auto max-w-4xl"><h1 class="text-3xl font-semibold">New client company</h1><p class="mt-1 text-slate-500">Create the company, primary contact and optional portal access.</p>
<form method="POST" action="{{ route('owner.companies.store') }}" class="mt-6 space-y-7">@csrf
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Company details</h2><div class="mt-5 grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-medium">Company name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Billing name<input name="billing_name" value="{{ old('billing_name') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Company email<input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Phone<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Website<input type="url" name="website" value="{{ old('website') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Timezone<input name="timezone" value="{{ old('timezone', 'America/New_York') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Currency<select name="currency" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">@foreach(['USD','EUR','MDL'] as $currency)<option>{{ $currency }}</option>@endforeach</select></label>
    </div><label class="mt-5 block text-sm font-medium">Billing address<textarea name="billing_address" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">{{ old('billing_address') }}</textarea></label></section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Primary contact</h2><div class="mt-5 grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-medium">Name<input name="contact_name" value="{{ old('contact_name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Email<input type="email" name="contact_email" value="{{ old('contact_email') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="text-sm font-medium">Phone<input name="contact_phone" value="{{ old('contact_phone') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
    </div><label class="mt-5 flex items-center gap-3 rounded-xl bg-indigo-50 p-4 text-sm"><input type="checkbox" name="create_access" value="1" @checked(old('create_access', true)) class="rounded"> Create portal access and a one-time temporary password</label></section>
    <button class="rounded-xl bg-indigo-600 px-5 py-3 font-medium text-white">Create client</button>
</form></div></x-layouts.app>

