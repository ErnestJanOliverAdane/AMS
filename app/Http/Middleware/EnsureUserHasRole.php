<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:super_admin,admin')
 *
 * - Guests go to the login page.
 * - Deactivated accounts are signed out immediately, even mid-session.
 * - Signed-in users whose role is not listed get a 403.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been deactivated. Please contact an administrator.',
            ]);
        }

        $allowed = array_filter(array_map(fn (string $role) => Role::tryFrom($role), $roles));

        abort_unless(in_array($user->role, $allowed, true), 403);

        return $next($request);
    }
}
