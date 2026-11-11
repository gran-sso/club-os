<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Services\UserLandingService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginRedirectController extends Controller
{
    /**
     * Keep Filament's /admin/login URL intact for middleware redirects while forwarding
     * browsers directly to the unified Staff Terminal tab and preserving url.intended.
     */
    public function __invoke(Request $request, UserLandingService $landingService): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if ($user) {
            if ($user->is_active === false) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login', ['tab' => 'staff']);
            }

            return redirect()->intended($landingService->resolveHomeUrlForUser($user));
        }

        return redirect()->route('login', ['tab' => 'staff']);
    }
}

