<x-layouts.app title="Team">
    <div class="mx-auto max-w-6xl space-y-6">
        <div><h1 class="text-2xl font-semibold">Team & permissions</h1><p class="mt-1 text-sm text-slate-500">Invite staff, assign projects, suspend access, and choose notification channels.</p></div>
        @if(session('temporary_credentials'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Copy these one-time credentials now:</strong> {{ session('temporary_credentials.email') }} / <code>{{ session('temporary_credentials.password') }}</code></div>
        @endif
        <form method="POST" action="{{ route('owner.team.store') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-4">@csrf
            <label class="text-sm">Name<input name="name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm">Email<input name="email" type="email" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm">Role<select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">@foreach($roles as $role)<option value="{{ $role->value }}">{{ str($role->value)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
            <div class="flex items-end"><button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-white">Invite member</button></div>
        </form>
        <div class="space-y-4">
            @foreach($members as $member)
                <form method="POST" action="{{ route('owner.team.update', $member) }}" class="rounded-2xl border border-slate-200 bg-white p-5">@csrf @method('PUT')
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-semibold">{{ $member->name }}</h2><p class="text-sm text-slate-500">{{ $member->email }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ str($member->status->value)->title() }}</span></div>
                    @if($member->role === \App\Enums\UserRole::Owner)<p class="text-sm text-slate-500">Owner access cannot be changed here.</p>@else
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="text-sm">Role<select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">@foreach($roles as $role)<option value="{{ $role->value }}" @selected($member->role === $role)>{{ str($role->value)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                        <label class="text-sm">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">@foreach(\App\Enums\AccountStatus::cases() as $status)<option value="{{ $status->value }}" @selected($member->status === $status)>{{ str($status->value)->title() }}</option>@endforeach</select></label>
                        <fieldset class="text-sm"><legend>Notifications</legend><div class="mt-2 flex gap-3">@foreach(['portal','email','telegram'] as $channel)<label><input type="checkbox" name="notify_{{ $channel }}" value="1" @checked(($member->notification_preferences[$channel] ?? ($channel !== 'telegram')))>{{ str($channel)->title() }}</label>@endforeach</div></fieldset>
                    </div>
                    <fieldset class="mt-4"><legend class="text-sm font-medium">Assigned projects</legend><div class="mt-2 flex flex-wrap gap-3">@foreach($projects as $project)<label class="text-sm"><input type="checkbox" name="project_ids[]" value="{{ $project->id }}" @checked($member->assignedProjects->contains($project))> {{ $project->name }}</label>@endforeach</div></fieldset>
                    <button class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Save access</button>
                    @endif
                </form>
            @endforeach
        </div>
    </div>
</x-layouts.app>
