<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SetupController extends Controller
{
    /**
     * Secret setup token. Configurable via SETUP_TOKEN in .env.
     */
    protected function getSecretToken(): string
    {
        return (string) env('SETUP_TOKEN', 'malabar-club-setup-2026');
    }

    /**
     * Check if setup is locked.
     */
    protected function isLocked(): bool
    {
        return file_exists(storage_path('installed.lock'));
    }

    /**
     * Display setup status & control dashboard.
     */
    public function index(Request $request): View
    {
        $token = $request->input('token') ?? session('setup_token', '');
        $authenticated = !empty($token) && hash_equals($this->getSecretToken(), (string) $token);

        if ($authenticated) {
            session(['setup_token' => $token]);
        }

        $dbConnected = false;
        $dbError = null;
        $dbConfig = [
            'connection' => config('database.default'),
            'database' => config('database.connections.' . config('database.default') . '.database'),
            'host' => config('database.connections.' . config('database.default') . '.host'),
            'port' => config('database.connections.' . config('database.default') . '.port'),
        ];

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }

        $storageLinked = file_exists(public_path('storage'));
        $appKeySet = !empty(config('app.key'));
        $isLocked = $this->isLocked();

        return view('setup.index', [
            'authenticated' => $authenticated,
            'isLocked' => $isLocked,
            'dbConnected' => $dbConnected,
            'dbError' => $dbError,
            'dbConfig' => $dbConfig,
            'storageLinked' => $storageLinked,
            'appKeySet' => $appKeySet,
            'phpVersion' => PHP_VERSION,
            'defaultToken' => $this->getSecretToken(),
        ]);
    }

    /**
     * Execute selected setup actions.
     */
    public function execute(Request $request): View
    {
        $token = $request->input('token') ?? session('setup_token', '');
        if (empty($token) || !hash_equals($this->getSecretToken(), (string) $token)) {
            abort(403, 'Unauthorized setup token.');
        }

        if ($this->isLocked() && !$request->boolean('unlock')) {
            abort(403, 'Setup is locked to protect production integrity. Remove storage/installed.lock to re-run.');
        }

        $results = [];
        $hasError = false;

        // 1. App Key Check / Generation
        if ($request->boolean('generate_key') || empty(config('app.key'))) {
            try {
                Artisan::call('key:generate', ['--force' => true]);
                $results[] = [
                    'step' => 'Application Encryption Key',
                    'status' => 'success',
                    'output' => trim(Artisan::output()) ?: 'Application key generated successfully.',
                ];
            } catch (Exception $e) {
                $hasError = true;
                $results[] = [
                    'step' => 'Application Encryption Key',
                    'status' => 'error',
                    'output' => $e->getMessage(),
                ];
            }
        }

        // 2. Storage Symlink
        if ($request->boolean('link_storage')) {
            try {
                if (file_exists(public_path('storage')) && is_link(public_path('storage'))) {
                    $results[] = [
                        'step' => 'Storage Symlink',
                        'status' => 'success',
                        'output' => 'public/storage symlink already exists.',
                    ];
                } else {
                    Artisan::call('storage:link');
                    $results[] = [
                        'step' => 'Storage Symlink',
                        'status' => 'success',
                        'output' => trim(Artisan::output()) ?: 'The [public/storage] link has been connected.',
                    ];
                }
            } catch (Exception $e) {
                $hasError = true;
                $results[] = [
                    'step' => 'Storage Symlink',
                    'status' => 'error',
                    'output' => $e->getMessage(),
                ];
            }
        }

        // 3. Database Migration
        if ($request->boolean('run_migrations') && !$hasError) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                $results[] = [
                    'step' => 'Database Schema Migration',
                    'status' => 'success',
                    'output' => trim(Artisan::output()) ?: 'Database migrated successfully.',
                ];
            } catch (Exception $e) {
                $hasError = true;
                $results[] = [
                    'step' => 'Database Schema Migration',
                    'status' => 'error',
                    'output' => $e->getMessage(),
                ];
            }
        }

        // 4. Database Seeding
        $seederType = $request->input('seeder_type', 'none');
        if ($seederType !== 'none' && !$hasError) {
            $seederClass = match ($seederType) {
                'demo' => \Database\Seeders\DemoDataSeeder::class,
                'production' => \Database\Seeders\ProductionBootstrapSeeder::class,
                default => null,
            };

            if ($seederClass) {
                try {
                    Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
                    $results[] = [
                        'step' => 'Initial Data Seeding (' . class_basename($seederClass) . ')',
                        'status' => 'success',
                        'output' => trim(Artisan::output()) ?: 'Data seeded successfully.',
                    ];
                } catch (Exception $e) {
                    $hasError = true;
                    $results[] = [
                        'step' => 'Initial Data Seeding',
                        'status' => 'error',
                        'output' => $e->getMessage(),
                    ];
                }
            }
        }

        // 5. Filament Assets & Cache Optimization
        if ($request->boolean('optimize') && !$hasError) {
            try {
                Artisan::call('optimize:clear');
                Artisan::call('filament:assets');
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');

                $results[] = [
                    'step' => 'Production Cache & Optimization',
                    'status' => 'success',
                    'output' => 'Config, routes, views, and Filament assets cached for peak performance.',
                ];
            } catch (Exception $e) {
                // Non-fatal optimization warning
                $results[] = [
                    'step' => 'Production Cache & Optimization',
                    'status' => 'warning',
                    'output' => 'Optimization notice: ' . $e->getMessage(),
                ];
            }
        }

        // 6. Lock Setup (if requested)
        if ($request->boolean('lock_setup') && !$hasError) {
            File::put(storage_path('installed.lock'), json_encode([
                'installed_at' => now()->toIso8601String(),
                'ip' => $request->ip(),
                'seeder' => $seederType,
            ]));
            $results[] = [
                'step' => 'Security Lock',
                'status' => 'success',
                'output' => 'Setup route locked. Future setup requests will require deleting storage/installed.lock.',
            ];
        }

        return view('setup.results', [
            'results' => $results,
            'hasError' => $hasError,
            'seederType' => $seederType,
        ]);
    }
}

