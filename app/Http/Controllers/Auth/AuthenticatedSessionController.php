<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The provided credentials do not match our records.']);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! in_array($user->status, [AccountStatus::Active, AccountStatus::Invited], true)) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'This account is not active.']);
        }

        $user->update(['last_login_at' => now()]);

        return redirect()->intended($user->isOwner() ? route('owner.dashboard') : route('client.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
