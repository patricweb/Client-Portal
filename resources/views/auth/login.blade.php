<x-layouts.guest title="Sign in — Ikira Client Portal">
    <h1 class="text-2xl font-semibold">Welcome back</h1>
    <p class="mt-1 text-sm text-slate-500">Sign in to access your workspace.</p>
    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">@csrf
        <label class="block text-sm font-medium">Email<input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <label class="block text-sm font-medium">Password<input name="password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label>
        <div class="text-right"><a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-600">Forgot password?</a></div>
        <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="rounded"> Remember me</label>
        <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-700">Sign in</button>
    </form>
</x-layouts.guest>
