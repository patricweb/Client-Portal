<x-layouts.guest title="Change password — Ikira Client Portal">
    <h1 class="text-2xl font-semibold">Create your password</h1>
    <p class="mt-1 text-sm text-slate-500">For security, replace the temporary password before continuing.</p>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">@csrf @method('PUT')
        <label class="block text-sm font-medium">Temporary password<input name="current_password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="block text-sm font-medium">New password<input name="password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="block text-sm font-medium">Confirm new password<input name="password_confirmation" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white">Save password</button>
    </form>
</x-layouts.guest>

