<x-layouts.guest title="Reset password — Ikira Client Portal">
    <h1 class="text-2xl font-semibold">Reset your password</h1>
    <p class="mt-1 text-sm text-slate-500">Enter your portal email and we will send you a secure reset link.</p>
    @if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">@csrf
        <label class="block text-sm font-medium">Email<input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white">Send reset link</button>
        <a href="{{ route('login') }}" class="block text-center text-sm text-slate-500">Back to sign in</a>
    </form>
</x-layouts.guest>

