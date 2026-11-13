<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Services\AuditLogService;
use App\Domain\Shared\Services\UserLandingService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    /**
     * Display the mandatory change-password screen for newly provisioned or reset accounts.
     */
    public function showForcedChange(UserLandingService $landingService): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->must_change_password) {
            return redirect($landingService->resolveHomeUrlForUser($user));
        }

        return view('auth.forced-password-change', [
            'user' => $user,
            'isForced' => true,
        ]);
    }

    /**
     * Display the voluntary self-service change password screen for any logged-in staff member.
     */
    public function showSelfServiceChange(): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.change-password', [
            'user' => $user,
            'isForced' => false,
        ]);
    }

    /**
     * Process password change submission.
     */
    public function updatePassword(Request $request, UserLandingService $landingService): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            // Allow dev password fallback for local development if not in production
            $isDevFallback = ! app()->environment('production')
                && in_array($validated['current_password'], ['password', 'Password@123'], true)
                && Hash::check(($validated['current_password'] === 'password' ? 'Password@123' : 'password'), $user->password);

            if (! $isDevFallback) {
                throw ValidationException::withMessages([
                    'current_password' => __('The provided current password does not match our records.'),
                ]);
            }
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('Your new password cannot be the same as your existing temporary password.'),
            ]);
        }

        $wasForced = (bool) $user->must_change_password;

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Invalidate active sessions on other terminals for security
        try {
            Auth::guard('web')->logoutOtherDevices($validated['password']);
        } catch (\Throwable $e) {
            // Session driver might not support logoutOtherDevices in all test environments
        }

        // Log audit trail
        AuditLogService::record(
            category: 'security',
            action: 'user.password_changed',
            description: "Staff member {$user->name} (@{$user->username}) updated their account password" . ($wasForced ? ' (initial forced setup completed)' : ' (self-service profile rotation)'),
            auditable: $user,
            newValues: [
                'username' => $user->username,
                'must_change_password' => false,
                'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            ],
            user: $user,
        );

        return redirect($landingService->resolveHomeUrlForUser($user))
            ->with('status', 'Your password has been securely updated. You can now proceed.');
    }
}
