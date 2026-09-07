<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::validate($credentials)) {
            return back()
                ->onlyInput('email')
                ->withErrors(['email' => 'The provided credentials are invalid.']);
        }

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user?->isAdmin()) {
            return back()
                ->onlyInput('email')
                ->withErrors(['email' => 'The provided credentials are invalid.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.menu-submissions.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}