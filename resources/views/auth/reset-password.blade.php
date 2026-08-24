<x-layouts.guest title="Choose password — Ikira Client Portal">
    <h1 class="text-2xl font-semibold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">@csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <label class="block text-sm font-medium">Email<input name="email" type="email" value="{{ old('email', $request->email) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="block text-sm font-medium">New password<input name="password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="block text-sm font-medium">Confirm password<input name="password_confirmation" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white">Save password</button>
    </form>
</x-layouts.guest>

