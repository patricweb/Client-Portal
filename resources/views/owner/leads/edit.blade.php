<x-layouts.app title="Edit lead — Ikira Client Portal"><div class="mx-auto max-w-3xl"><h1 class="text-3xl font-semibold">Edit lead</h1><form method="POST" action="{{ route('owner.leads.update', $lead) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">@csrf @method('PUT') @include('owner.leads._form')</form></div></x-layouts.app>

