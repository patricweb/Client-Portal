<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Ikira Client Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-950 px-5 py-10 text-slate-100 antialiased">
    <main class="w-full max-w-md rounded-2xl border border-slate-800 bg-white p-7 sm:p-9">
        <div class="mb-8 flex items-center gap-3 border-b border-slate-800 pb-6"><span class="brand-mark grid size-10 place-items-center rounded-lg border border-slate-700 bg-slate-900 text-sm font-semibold text-white">IK</span><div><p class="font-semibold tracking-tight">Ikira Client Portal</p><p class="text-sm text-slate-500">Projects, progress and support</p></div></div>
        @if($errors->any())<div class="mb-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        @if(session('status'))<div class="mb-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
</body>
</html>
