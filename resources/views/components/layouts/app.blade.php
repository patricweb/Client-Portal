<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Ikira Client Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<div class="min-h-screen lg:grid lg:grid-cols-[250px_1fr]">
    <aside class="bg-slate-950 px-4 py-6 text-slate-200 lg:min-h-screen">
        <a href="{{ auth()->user()->isOwner() ? route('owner.dashboard') : route('client.dashboard') }}" class="mb-8 flex items-center gap-3 px-2 text-lg font-semibold text-white">
            <span class="grid size-9 place-items-center rounded-xl bg-indigo-500">I</span>
            <span>Ikira Portal</span>
        </a>
        <nav class="flex gap-2 overflow-x-auto lg:grid">
            @if(auth()->user()->isOwner())
                @foreach([
                    ['Today', 'owner.dashboard'], ['Leads', 'owner.leads.index'], ['Clients', 'owner.companies.index'], ['Projects', 'owner.projects.index'],
                ] as [$label, $route])
                    <a href="{{ route($route) }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm {{ request()->routeIs($route, str_replace('.index', '.*', $route)) ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">{{ $label }}</a>
                @endforeach
            @else
                <a href="{{ route('client.dashboard') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.dashboard') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Home</a>
                <a href="{{ route('client.projects.index') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.projects.*', 'client.brief.*') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Projects</a>
            @endif
        </nav>
        <div class="mt-8 border-t border-slate-800 pt-5">
            <p class="px-2 text-sm font-medium text-white">{{ auth()->user()->name }}</p>
            <p class="px-2 text-xs text-slate-400">{{ auth()->user()->email }}</p>
            <form action="{{ route('logout') }}" method="POST" class="mt-3">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-800">Sign out</button></form>
        </div>
    </aside>
    <main class="min-w-0 p-5 sm:p-8">
        @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Please review the form.</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        {{ $slot }}
    </main>
</div>
</body>
</html>

