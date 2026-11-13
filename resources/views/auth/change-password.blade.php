<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Change Password — {{ config('club.name', 'Malabar Club') }}</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="min-h-full flex items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-4 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="w-full max-w-md my-auto">
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 items-center justify-center font-black text-xl text-slate-950 shadow-lg shadow-amber-500/25 mb-3">
                {{ config('club.short_name', 'MC') }}
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white">{{ config('club.name', 'Malabar Club') }}</h1>
            <p class="text-xs text-slate-400 mt-1">Staff Security Credentials &bull; Update Password</p>
        </div>

        <!-- Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md">
            <!-- Account Badge -->
            <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-950 border border-slate-800 mb-5">
                <div>
                    <div class="text-xs font-bold text-white">{{ $user->name }}</div>
                    <div class="text-[11px] text-amber-400 font-mono">Username: {{ $user->username }}</div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300 border border-slate-700">
                    Active Account
                </span>
            </div>

            @if(session('status'))
                <div role="status" class="mb-5 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-5 p-3.5 rounded-2xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-xs flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="current_password" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Current Password <span class="text-amber-400">*</span>
                    </label>
                    <input type="password"
                           id="current_password"
                           name="current_password"
                           required
                           autocomplete="current-password"
                           class="w-full min-h-12 px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:border-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors"
                           placeholder="Enter current password">
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        New Password <span class="text-amber-400">*</span>
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           minlength="8"
                           autocomplete="new-password"
                           class="w-full min-h-12 px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:border-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors"
                           placeholder="Minimum 8 characters">
                    <p class="text-[11px] text-slate-400 mt-1">Must be at least 8 characters long and different from current password.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Confirm New Password <span class="text-amber-400">*</span>
                    </label>
                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           required
                           minlength="8"
                           autocomplete="new-password"
                           class="w-full min-h-12 px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:border-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors"
                           placeholder="Re-enter new password">
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full min-h-12 flex items-center justify-center px-6 py-3.5 rounded-xl font-bold text-sm bg-gradient-to-r from-amber-500 to-yellow-400 text-slate-950 hover:from-amber-400 hover:to-yellow-300 transition-all shadow-md shadow-amber-500/20 cursor-pointer">
                        Update Password
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <a href="{{ url('/') }}" class="hover:text-amber-400 transition-colors">
                    &larr; Back to Dashboard
                </a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="hover:text-slate-200 transition-colors">
                        Sign Out
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>

