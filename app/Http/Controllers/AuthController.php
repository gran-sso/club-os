<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Services\UserLandingService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $success = \Illuminate\Support\Facades\Hash::check($password, $user->password);

            if (
                ! $success
                && ! app()->environment('production')
                && in_array($password, ['password', 'Password@123'], true)
            ) {
                $altPassword = ($password === 'password') ? 'Password@123' : 'password';
                $success = \Illuminate\Support\Facades\Hash::check($altPassword, $user->password);
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
