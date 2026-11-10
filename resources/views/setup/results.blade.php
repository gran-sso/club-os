<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Execution Results — Malabar Club</title>
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
                <div class="w-12 h-12 rounded-xl {{ $hasError ? 'bg-rose-600' : 'bg-emerald-600' }} flex items-center justify-center font-bold text-white text-2xl shadow-xs shrink-0">
                    {{ $hasError ? '!' : '✓' }}
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                        {{ $hasError ? 'Setup Completed with Warnings/Errors' : 'Setup Completed Successfully!' }}
                    </h1>
                    <p class="text-xs text-slate-500">
                        {{ $hasError ? 'Review diagnostic logs below.' : 'Your application is now configured, migrated, and ready to use.' }}
                    </p>
                </div>
            </div>
            <a href="{{ route('setup.index') }}" class="text-xs text-blue-600 hover:underline">
                &larr; Back to Wizard
            </a>
        </div>

        <!-- Execution Steps Results -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600">
                Execution Diagnostic Log
            </h2>

            <div class="space-y-3">
                @foreach($results as $res)
                    @php
                        $isSuccess = ($res['status'] === 'success');
                        $isWarning = ($res['status'] === 'warning');
                        $isError = ($res['status'] === 'error');
                    @endphp
                    <div class="p-4 rounded-xl border {{ $isSuccess ? 'bg-emerald-50/40 border-emerald-200' : ($isWarning ? 'bg-amber-50/40 border-amber-200' : 'bg-rose-50/40 border-rose-200') }}">
                        <div class="flex items-center justify-between text-xs font-bold mb-1.5 {{ $isSuccess ? 'text-emerald-900' : ($isWarning ? 'text-amber-900' : 'text-rose-900') }}">
                            <span>{{ $res['step'] }}</span>
                            <span>{{ $isSuccess ? 'SUCCESS ✓' : ($isWarning ? 'WARNING ⚠️' : 'FAILED ✗') }}</span>
                        </div>
                        <pre class="text-[11px] font-mono leading-relaxed p-2.5 bg-white/80 rounded-lg border border-slate-200/60 text-slate-700 whitespace-pre-wrap overflow-x-auto">{{ $res['output'] }}</pre>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Quick Access & Login Credentials Card -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600">
                Application Access &amp; Default Credentials
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                    <span class="font-bold text-slate-900 block">Administrator Portal (/admin)</span>
                    <p class="text-slate-600">Email: <strong class="text-slate-900">admin@malabarclub.local</strong></p>
                    <p class="text-slate-600">Password: <strong class="text-slate-900">Password@123</strong></p>
                </div>

                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                    <span class="font-bold text-slate-900 block">Counter POS Billing (/pos)</span>
                    <p class="text-slate-600">Email: <strong class="text-slate-900">cashier1@malabarclub.local</strong></p>
                    <p class="text-slate-600">Password: <strong class="text-slate-900">Password@123</strong></p>
                </div>

                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                    <span class="font-bold text-slate-900 block">Member Self-Service (/member/login)</span>
                    <p class="text-slate-600">Member #: <strong class="text-slate-900">MC-001</strong> (or 9895011111)</p>
                    <p class="text-slate-600">PIN: <strong class="text-slate-900">Member@123</strong></p>
                </div>

                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                    <span class="font-bold text-slate-900 block">Front Desk Reception</span>
                    <p class="text-slate-600">Email: <strong class="text-slate-900">reception@malabarclub.local</strong></p>
                    <p class="text-slate-600">Password: <strong class="text-slate-900">Password@123</strong></p>
                </div>
            </div>

            <!-- Direct Action Buttons -->
            <div class="pt-2 flex flex-wrap gap-3">
                <a
                    href="{{ url('/admin') }}"
                    class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs text-center rounded-xl transition"
                >
                    Open Admin Back-Office &rarr;
                </a>
                <a
                    href="{{ url('/pos') }}"
                    class="flex-1 py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-medium text-xs text-center rounded-xl transition"
                >
                    Open Counter POS &rarr;
                </a>
                <a
                    href="{{ url('/member/login') }}"
                    class="flex-1 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs text-center rounded-xl transition"
                >
                    Open Member Portal &rarr;
                </a>
            </div>
        </div>

    </div>

</body>
</html>

