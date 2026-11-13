<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * If an authenticated staff user has must_change_password = true,
     * they must complete their password rotation before accessing POS or back-office.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->must_change_password) {
            $exemptRoutes = [
                'password.forced-change',
                'password.forced-change.update',
                'password.change',
                'password.change.update',
                'logout',
            ];

            if (! $request->routeIs(...$exemptRoutes)) {
                return redirect()->route('password.forced-change');
            }
        }

        return $next($request);
    }
}

