<x-layouts.app title="New lead — Ikira Client Portal"><div class="mx-auto max-w-3xl"><h1 class="text-3xl font-semibold">New lead</h1><form method="POST" action="{{ route('owner.leads.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">@csrf @include('owner.leads._form')</form></div></x-layouts.app>

