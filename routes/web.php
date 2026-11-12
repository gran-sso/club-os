<?php

use App\Domain\Shared\Services\UserLandingService;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Member\MemberAuthController;
use App\Livewire\CounterPos;
use App\Livewire\Member\MemberDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function (UserLandingService $landingService) {
    if (($user = auth('web')->user()) && $user->is_active !== false) {
        return redirect($landingService->resolveHomeUrlForUser($user));
    }

    if ($member = auth('member')->user()) {
        return redirect($landingService->resolveHomeUrlForMember($member));
    }

    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth:web'])->group(function () {
    Route::get('/pos', CounterPos::class)->name('pos');
    Route::get('/documents/members/{member}/{filename}', [\App\Http\Controllers\DocumentController::class, 'showMemberDocument'])
        ->name('members.documents.show');
    Route::get('/documents/dependents/{dependent}/photo', [\App\Http\Controllers\DocumentController::class, 'showDependentPhoto'])
        ->name('dependents.photo.show');
});

// Member Portal Routes
Route::prefix('member')->name('member.')->group(function () {
    Route::get('/login', [MemberAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [MemberAuthController::class, 'login'])->name('login.submit');
    Route::get('/activate', [MemberAuthController::class, 'showActivate'])->name('activate');
    Route::post('/activate', [MemberAuthController::class, 'activate'])->name('activate.submit');
    Route::post('/logout', [MemberAuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth:member'])->group(function () {
        Route::get('/', function () {
            return redirect()->route('member.dashboard');
        });
        Route::get('/dashboard', MemberDashboard::class)->name('dashboard');
        Route::get('/photo', [\App\Http\Controllers\DocumentController::class, 'showPortalMemberPhoto'])
            ->name('photo');
        Route::get('/dependents/{dependent}/photo', [\App\Http\Controllers\DocumentController::class, 'showPortalDependentPhoto'])
            ->name('dependents.photo');
    });
});

// PWA Assets
Route::get('/manifest.json', function () {
    return response(file_get_contents(public_path('manifest.json')), 200, [
        'Content-Type' => 'application/manifest+json',
    ]);
});

Route::get('/manifest-member.json', function () {
    return response(file_get_contents(public_path('manifest-member.json')), 200, [
        'Content-Type' => 'application/manifest+json',
    ]);
});

Route::get('/sw.js', function () {
    return response(file_get_contents(public_path('sw.js')), 200, [
        'Content-Type' => 'application/javascript',
    ]);
});

Route::get('/offline.html', function () {
    return response(file_get_contents(public_path('offline.html')), 200, [
        'Content-Type' => 'text/html',
    ]);
});

Route::get('/icons/{icon}', function ($icon) {
    $path = public_path('icons/' . basename($icon));
    abort_unless(file_exists($path), 404);
    return response(file_get_contents($path), 200, ['Content-Type' => 'image/svg+xml']);
});

// Web Setup & Deployment Wizard
Route::get('/setup-club', [\App\Http\Controllers\SetupController::class, 'index'])->name('setup.index');
Route::post('/setup-club', [\App\Http\Controllers\SetupController::class, 'execute'])->name('setup.execute');


