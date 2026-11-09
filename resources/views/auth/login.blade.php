<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Sign In — Malabar Club</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Outfit', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full flex items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-4 antialiased selection:bg-amber-500 selection:text-slate-950">
    <div class="w-full max-w-md my-auto">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 items-center justify-center font-black text-2xl text-slate-950 shadow-lg shadow-amber-500/25 mb-3.5">
                {{ config('club.short_name', 'MC') }}
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ config('club.name', 'Malabar Club') }}</h1>
            <p class="text-xs text-slate-400 mt-1">{{ config('club.location', 'Vadakkencherry, Kerala') }} • Staff &amp; Counter Terminal</p>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-7 sm:p-8 shadow-2xl backdrop-blur-md">
            @if ($errors->any())
                <div class="mb-5 p-4 rounded-2xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-xs flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Staff Official Email
                    </label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 text-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 focus:outline-none transition-all shadow-2xs"
                        placeholder="staff@malabarclub.local">
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Password
                    </label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 text-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 focus:outline-none transition-all shadow-2xs"
                        placeholder="••••••••••••">
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center text-slate-400 select-none cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-amber-500 focus:ring-amber-500/30">
                        <span class="ml-2">Remember this terminal session</span>
                    </label>
                </div>

                <button type="submit"
                    class="w-full py-3.5 px-5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-md shadow-amber-500/20 active:scale-98 transition-all cursor-pointer">
                    Sign In to Terminal
                </button>
            </form>

            @if(!app()->environment('production'))
            <!-- Quick Demo Accounts (Development & Staging Only) -->
            <div class="mt-6 pt-5 border-t border-slate-800">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3 text-center">Development Demo Access</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button type="button" onclick="setLogin('cashier1@malabarclub.local')"
                        class="p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-all text-left cursor-pointer">
                        <div class="font-bold text-amber-400">Cashier 1</div>
                        <div class="text-[10px] text-slate-400">Suresh Pillai</div>
                    </button>
                    <button type="button" onclick="setLogin('cashier2@malabarclub.local')"
                        class="p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-all text-left cursor-pointer">
                        <div class="font-bold text-amber-400">Cashier 2</div>
                        <div class="text-[10px] text-slate-400">Deepa Nair</div>
                    </button>
                    <button type="button" onclick="setLogin('manager@malabarclub.local')"
                        class="p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-all text-left cursor-pointer">
                        <div class="font-bold text-amber-400">Manager</div>
                        <div class="text-[10px] text-slate-400">V. Jayaprakash</div>
                    </button>
                    <button type="button" onclick="setLogin('admin@malabarclub.local')"
                        class="p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-all text-left cursor-pointer">
                        <div class="font-bold text-amber-400">Admin</div>
                        <div class="text-[10px] text-slate-400">System Admin</div>
                    </button>
                </div>
            </div>

            <script>
                function setLogin(email) {
                    document.getElementById('email').value = email;
                    document.getElementById('password').value = 'Password@123';
                }
            </script>
            @endif
        </div>

        <div class="text-center mt-6 text-[11px] text-slate-400 font-mono">
            Secure Member Ledger &bull; Strict No-Credit Policy Enforced
        </div>
    </div>
</body>
</html>
