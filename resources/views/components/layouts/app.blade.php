<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Ikira Client Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<div class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    <aside class="border-b border-slate-800 bg-slate-950 px-4 py-5 text-slate-200 lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:border-b-0 lg:border-r lg:px-5 lg:py-7">
        <a href="{{ auth()->user()->isStaff() ? route('owner.dashboard') : route('client.dashboard') }}" class="mb-7 flex items-center gap-3 px-2 text-base font-semibold tracking-tight text-white">
            <span class="brand-mark grid size-9 place-items-center rounded-lg border border-slate-700 bg-slate-900 text-sm">IK</span>
            <span><span class="block">Ikira</span><span class="block text-xs font-normal text-slate-500">Client Portal</span></span>
        </a>
        <nav class="flex gap-2 overflow-x-auto lg:grid lg:min-h-0 lg:flex-1 lg:content-start lg:overflow-x-hidden lg:overflow-y-auto lg:pr-1">
            @if(auth()->user()->isOwner())<a href="{{ route('owner.settings.provider.edit') }}" class="whitespace-nowrap rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('owner.settings.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Provider settings</a>@endif
            @if(auth()->user()->isStaff())
                @foreach([
                    ['Today', 'owner.dashboard', null], ['Leads', 'owner.leads.index', 'manage_leads'], ['Clients', 'owner.companies.index', 'manage_clients'], ['Projects', 'owner.projects.index', 'manage_projects'], ['Work Items', 'owner.work-items.index', 'manage_work_items'], ['Confirmations', 'owner.documents.index', 'manage_documents'], ['Invoices', 'owner.invoices.index', 'manage_billing'], ['Care & Support', 'owner.care-plans.index', 'manage_care'], ['Requests', 'owner.requests.index', 'manage_requests'], ['Activity', 'owner.activity.index', 'view_activity'],
                ] as [$label, $route, $permission])
                    @continue($permission && ! auth()->user()->hasPermission($permission))
                    <a href="{{ route($route) }}" class="whitespace-nowrap rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs($route, str_replace('.index', '.*', $route)) ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">{{ $label }}</a>
                @endforeach
                @if(auth()->user()->hasPermission('manage_team'))<a href="{{ route('owner.team.index') }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm {{ request()->routeIs('owner.team.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Team</a>@endif
            @else
                <a href="{{ route('client.dashboard') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.dashboard') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Home</a>
                <a href="{{ route('client.projects.index') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.projects.*', 'client.brief.*') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Projects</a>
                <a href="{{ route('client.documents.index') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.documents.*') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Confirmations</a>
                <a href="{{ route('client.billing.index') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.billing.*') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Billing</a>
                <a href="{{ route('client.care-plans.index') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.care-plans.*') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Care & Support</a>
                <a href="{{ route('client.requests.index') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.requests.*') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Requests</a>
                <a href="{{ route('client.activity.index') }}" class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('client.activity.*') ? 'bg-indigo-500 text-white' : 'text-slate-300' }}">Updates</a>
            @endif
        </nav>
        <div class="mt-7 border-t border-slate-800 pt-5 lg:mt-auto">
            <a href="{{ route('notifications.index') }}" class="mb-4 flex items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-slate-800"><span>Notifications</span>@if(auth()->user()->unreadNotifications()->count())<span class="rounded-full bg-indigo-500 px-2 py-0.5 text-xs text-white">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif</a>
            <p class="px-2 text-sm font-medium text-white">{{ auth()->user()->name }}</p>
            <p class="px-2 text-xs text-slate-400">{{ auth()->user()->email }}</p>
            <form action="{{ route('logout') }}" method="POST" class="mt-3">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-800">Sign out</button></form>
        </div>
    </aside>
    <main class="min-w-0 p-5 sm:p-8 lg:p-10">
        <div class="mx-auto w-full max-w-[1600px]">
            @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Please review the form.</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
