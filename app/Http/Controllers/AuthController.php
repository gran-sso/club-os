<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Services\AuditLogService;
use App\Domain\Shared\Services\UserLandingService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request, UserLandingService $landingService): View|RedirectResponse
    {
        $requestedTab = $request->query('tab');
        $oldPortal = $request->old('_portal');

        /** @var User|null $staffUser */
        $staffUser = Auth::guard('web')->user();
        $memberUser = Auth::guard('member')->user();

        if ($requestedTab === 'member' && $memberUser) {
            return redirect($landingService->resolveHomeUrlForMember($memberUser));
        }

        if ($requestedTab === 'staff' && $staffUser && $staffUser->is_active !== false) {
            return redirect($landingService->resolveHomeUrlForUser($staffUser));
        }

        if (! in_array($requestedTab, ['staff', 'member'], true)) {
            if ($staffUser && $staffUser->is_active !== false) {
                return redirect($landingService->resolveHomeUrlForUser($staffUser));
            }

            if ($memberUser) {
                return redirect($landingService->resolveHomeUrlForMember($memberUser));
            }
        }

        $initialTab = in_array($requestedTab, ['staff', 'member'], true)
            ? $requestedTab
            : (in_array($oldPortal, ['staff', 'member'], true) ? $oldPortal : 'staff');

        $forceInitialTab = in_array($requestedTab, ['staff', 'member'], true)
            || in_array($oldPortal, ['staff', 'member'], true);

        return view('auth.login', [
            'initialTab' => $initialTab,
            'forceInitialTab' => $forceInitialTab,
        ]);
    }

    public function login(Request $request, UserLandingService $landingService): RedirectResponse
    {
        $loginInput = trim((string) $request->input('login', $request->input('username', $request->input('email'))));
        $password = (string) $request->input('password');
        $errorKey = $request->has('email') && ! $request->has('login') && ! $request->has('username') ? 'email' : 'login';

        if ($loginInput === '' || $password === '') {
            throw ValidationException::withMessages([
                $errorKey => __('Please enter your staff username and password.'),
            ]);
        }

        // Rate Limiting: 5 attempts per username/IP pair before 5-minute lockout
        $throttleKey = 'login:staff:' . Str::lower($loginInput) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            app(AuditLogService::class)->log(
                category: 'security',
                action: 'auth.throttled',
                description: "Rate limit triggered for staff login '{$loginInput}' from IP {$request->ip()}",
                oldValues: ['identifier' => $loginInput, 'ip' => $request->ip()]
            );

            throw ValidationException::withMessages([
                $errorKey => __("Too many failed login attempts. Please wait {$seconds} seconds before trying again."),
            ]);
        }

        /** @var User|null $user */
        $user = User::query()
            ->where(function ($query) use ($loginInput) {
                $query->where('username', $loginInput)
                    ->orWhere('employee_id', $loginInput);

                if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
                    $query->orWhere('email', $loginInput);
                }
            })
            ->first();

        $success = false;

        if ($user) {
            $success = Hash::check($password, $user->password);

            // Strictly restricted to local development fixtures; never active in production
            if (
                ! $success
                && ! app()->environment('production')
                && in_array($password, ['password', 'Password@123'], true)
            ) {
                $altPassword = ($password === 'password') ? 'Password@123' : 'password';
                $success = Hash::check($altPassword, $user->password);
            }
        }

        if ($success && $user) {
            if ($user->is_active === false) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    $errorKey => __('Your staff account has been deactivated. Please contact an administrator.'),
                ]);
            }

            // Clear rate limiting counter upon verified authentication
            RateLimiter::clear($throttleKey);

            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            if ($request->session()->get('url.intended') === route('filament.admin.auth.login')) {
                $request->session()->forget('url.intended');
            }

            if ($user->must_change_password) {
                return redirect()->route('password.forced-change')
                    ->with('status', 'Welcome! For operational security, you must set a new private password before accessing the system.');
            }

            return redirect()->intended($landingService->resolveHomeUrlForUser($user));
        }

        // Increment rate limiter counter and log security audit event
        RateLimiter::hit($throttleKey, 300);
        app(AuditLogService::class)->logFailedLogin($loginInput, 'staff', $request->ip());

        throw ValidationException::withMessages([
            $errorKey => __('The provided credentials do not match our records.'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
