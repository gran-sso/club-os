<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Malabar Club — Web Setup &amp; Deployment Wizard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen py-10 px-4 sm:px-6">

    <div class="max-w-2xl mx-auto space-y-6">

        <!-- Header -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs flex items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-amber-600 to-yellow-400 p-0.5 shadow-xs shrink-0">
                    <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center font-serif text-amber-400 font-extrabold text-xl">
                        MC
                    </div>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Malabar Club Deployment Wizard</h1>
                    <p class="text-xs text-slate-500">One-click web configuration, database migration &amp; cache optimization</p>
                </div>
            </div>
            <div class="text-right text-xs text-slate-500 font-mono hidden sm:block">
                <span>PHP {{ $phpVersion }}</span>
            </div>
        </div>

        @if(!$authenticated)
            <!-- Authentication Form -->
            <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="space-y-1">
                    <h2 class="text-base font-bold text-slate-900">Enter Setup Security Token</h2>
                    <p class="text-xs text-slate-500">
                        Please provide your deployment security token to access the setup wizard.
                    </p>
                </div>

                <form method="GET" action="{{ route('setup.index') }}" class="space-y-4">
                    <div>
                        <label for="token" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                            Setup Token
                        </label>
                        <input
                            type="password"
                            id="token"
                            name="token"
                            required
                            placeholder="Enter setup token"
                            value="{{ request('token') }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-hidden font-mono"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">
                            Default token configured in codebase: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">{{ $defaultToken }}</code> (or set via <code class="text-slate-700">SETUP_TOKEN</code> in .env).
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-xl transition cursor-pointer"
                    >
                        Authenticate Setup Wizard &rarr;
                    </button>
                </form>
            </div>
        @else
            <!-- Authenticated Setup Dashboard -->

            <!-- System Pre-Flight Status -->
            <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600">
                    System Pre-Flight Health Check
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <!-- Database Status -->
                    <div class="p-3.5 rounded-xl border {{ $dbConnected ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-rose-50/60 border-rose-200 text-rose-900' }}">
                        <div class="flex items-center justify-between font-semibold mb-1">
                            <span>Database Connection</span>
                            <span>{{ $dbConnected ? 'Connected ✓' : 'Failed ✗' }}</span>
                        </div>
                        <p class="text-[11px] {{ $dbConnected ? 'text-emerald-700' : 'text-rose-700' }} truncate">
                            {{ $dbConfig['connection'] }}://{{ $dbConfig['host'] }}:{{ $dbConfig['port'] }}/{{ $dbConfig['database'] }}
                        </p>
                        @if(!$dbConnected && $dbError)
                            <p class="text-[10px] text-rose-600 mt-1 font-mono break-all">{{ $dbError }}</p>
                        @endif
                    </div>

                    <!-- App Encryption Key -->
                    <div class="p-3.5 rounded-xl border {{ $appKeySet ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-amber-50/60 border-amber-200 text-amber-900' }}">
                        <div class="flex items-center justify-between font-semibold mb-1">
                            <span>Application Encryption Key</span>
                            <span>{{ $appKeySet ? 'Configured ✓' : 'Missing !' }}</span>
                        </div>
                        <p class="text-[11px] {{ $appKeySet ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $appKeySet ? 'APP_KEY is set in environment' : 'Will be generated automatically' }}
                        </p>
                    </div>

                    <!-- Storage Symlink -->
                    <div class="p-3.5 rounded-xl border {{ $storageLinked ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-800' }}">
                        <div class="flex items-center justify-between font-semibold mb-1">
                            <span>Storage Symlink</span>
                            <span>{{ $storageLinked ? 'Linked ✓' : 'Pending' }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            {{ $storageLinked ? 'public/storage is active' : 'Will link public/storage on setup' }}
                        </p>
                    </div>

                    <!-- Setup Lock Status -->
                    <div class="p-3.5 rounded-xl border {{ $isLocked ? 'bg-amber-50/60 border-amber-200 text-amber-900' : 'bg-blue-50/60 border-blue-200 text-blue-900' }}">
                        <div class="flex items-center justify-between font-semibold mb-1">
                            <span>Setup Lock</span>
                            <span>{{ $isLocked ? 'Locked 🔒' : 'Unlocked 🔓' }}</span>
                        </div>
                        <p class="text-[11px] {{ $isLocked ? 'text-amber-700' : 'text-blue-700' }}">
                            {{ $isLocked ? 'Locked (storage/installed.lock exists)' : 'Ready to execute setup' }}
                        </p>
                    </div>
                </div>

                @if(!$dbConnected)
                    <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 space-y-1">
                        <strong class="font-bold">Database Connection Warning:</strong>
                        <p>Laravel cannot connect to the database. Please verify your <code class="font-mono bg-rose-100 px-1 rounded">.env</code> file credentials (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD) in Hostinger File Manager.</p>
                    </div>
                @endif
            </div>

            <!-- Setup Execution Options -->
            <form method="POST" action="{{ route('setup.execute') }}" class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-6">
                @csrf
                <input type="hidden" name="token" value="{{ session('setup_token', request('token')) }}">

                <div class="space-y-1">
                    <h2 class="text-base font-bold text-slate-900">Setup Configuration Options</h2>
                    <p class="text-xs text-slate-500">Choose the operations you want to execute on the application.</p>
                </div>

                <!-- Action Toggles -->
                <div class="space-y-3 border-t border-slate-100 pt-4">
                    
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50/60 cursor-pointer transition">
                        <input type="checkbox" name="run_migrations" value="1" checked class="mt-0.5 rounded text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-xs font-semibold text-slate-900 block">Run Database Migrations</span>
                            <span class="text-[11px] text-slate-500 block">Applies all database tables, columns, indexes, and constraints (`php artisan migrate --force`).</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50/60 cursor-pointer transition">
                        <input type="checkbox" name="link_storage" value="1" checked class="mt-0.5 rounded text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-xs font-semibold text-slate-900 block">Connect Storage Symlink</span>
                            <span class="text-[11px] text-slate-500 block">Creates symlink from `storage/app/public` to `public/storage` for member KYC docs and receipts.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50/60 cursor-pointer transition">
                        <input type="checkbox" name="generate_key" value="1" {{ $appKeySet ? '' : 'checked' }} class="mt-0.5 rounded text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-xs font-semibold text-slate-900 block">Ensure Encryption Key (APP_KEY)</span>
                            <span class="text-[11px] text-slate-500 block">Generates application security encryption key if not already configured in `.env`.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50/60 cursor-pointer transition">
                        <input type="checkbox" name="optimize" value="1" checked class="mt-0.5 rounded text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-xs font-semibold text-slate-900 block">Publish Filament Assets &amp; Cache Optimization</span>
                            <span class="text-[11px] text-slate-500 block">Caches routes, config, views, and publishes Filament admin panel assets for maximum speed.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50/60 cursor-pointer transition">
                        <input type="checkbox" name="lock_setup" value="1" checked class="mt-0.5 rounded text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-xs font-semibold text-slate-900 block">Lock Setup Route After Completion</span>
                            <span class="text-[11px] text-slate-500 block">Writes `storage/installed.lock` to prevent unauthorized re-running in production.</span>
                        </div>
                    </label>

                    @if($isLocked)
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-amber-300 bg-amber-50/50 cursor-pointer transition">
                            <input type="checkbox" name="unlock" value="1" class="mt-0.5 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-semibold text-amber-900 block">Force Unlock Setup</span>
                                <span class="text-[11px] text-amber-700 block">Setup is currently locked. Check this box to re-run migrations or update data.</span>
                            </div>
                        </label>
                    @endif
                </div>

                <!-- Seeder Options -->
                <div class="space-y-2 border-t border-slate-100 pt-4">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-700 block">
                        Select Initial Database Seeder
                    </label>

                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-blue-200 bg-blue-50/40 hover:bg-blue-50/70 cursor-pointer transition">
                            <input type="radio" name="seeder_type" value="demo" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
                            <div>
                                <strong class="text-xs text-blue-900 font-semibold block">Demo Fixtures &amp; Test Members (Recommended for Pre-Client Demo)</strong>
                                <span class="text-[11px] text-blue-700 block">
                                    Seeds test members (MC-001 Venu Gopal with ₹5,000 wallet, MC-002 Priya Nair with ₹1,000, MC-003 with ₹0 for zero-credit testing), rooms, courts, dining items, and staff accounts.
                                </span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50/60 cursor-pointer transition">
                            <input type="radio" name="seeder_type" value="production" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                            <div>
                                <strong class="text-xs text-slate-900 font-semibold block">Production Bootstrap (Clean Official Launch)</strong>
                                <span class="text-[11px] text-slate-500 block">
                                    Seeds authoritative 5-digit Chart of Accounts, system permissions, official administrative roles, and default categories with zero mock transactions.
                                </span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50/60 cursor-pointer transition">
                            <input type="radio" name="seeder_type" value="none" class="mt-0.5 text-slate-600 focus:ring-slate-500">
                            <div>
                                <strong class="text-xs text-slate-700 font-semibold block">No Seeder (Schema Only)</strong>
                                <span class="text-[11px] text-slate-500 block">Only creates database tables without inserting any user accounts or categories.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 flex items-center justify-between gap-4">
                    <span class="text-xs text-slate-500">Click to execute all checked operations.</span>
                    <button
                        type="submit"
                        {{ $dbConnected ? '' : 'disabled' }}
                        class="h-10 px-5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold text-xs rounded-xl shadow-xs transition cursor-pointer"
                    >
                        Execute Setup Now &rarr;
                    </button>
                </div>
            </form>
        @endif

        <div class="text-center text-xs text-slate-400">
            Malabar Club &bull; Palakkad, Kerala &bull; Internal Secretariat System
        </div>

    </div>

</body>
</html>

