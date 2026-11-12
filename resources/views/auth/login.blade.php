@php
    $initialTab = in_array($initialTab ?? null, ['staff', 'member'], true) ? $initialTab : 'staff';
    $forceInitialTab = (bool) ($forceInitialTab ?? false);
    $errorPortal = old('_portal');
    if (! in_array($errorPortal, ['staff', 'member'], true)) {
        $errorPortal = ($errors->has('login') && ! $errors->has('email')) ? 'member' : 'staff';
    }
    $staffHasError = $errors->any() && $errorPortal === 'staff';
    $memberHasError = $errors->any() && $errorPortal === 'member';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — {{ config('club.name', 'Malabar Club') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Outfit', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* Selected tab indicator uses both tint and a structural underline so state
           does not depend on color alone (WCAG 1.4.1 / forced-colors). */
        [role="tab"] {
            position: relative;
        }

        [role="tab"][aria-selected="true"] {
            color: #fbbf24;
            background-color: rgb(30 41 59 / 0.9);
        }

        [role="tab"][aria-selected="true"]::after {
            content: "";
            position: absolute;
            inset: auto 0.875rem 0.25rem;
            border-block-end: 3px solid currentColor;
            border-radius: 9999px;
        }

        [role="tab"][aria-selected="false"] {
            color: #94a3b8;
            background-color: transparent;
        }

        [role="tab"][aria-selected="false"]:hover {
            color: #e2e8f0;
        }

        .login-input {
            font-size: max(1rem, 1em);
        }
    </style>
</head>
<body class="min-h-full flex items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-4 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="w-full max-w-md my-auto">
        <!-- Brand Header -->
        <div class="text-center mb-7">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 items-center justify-center font-black text-2xl text-slate-950 shadow-lg shadow-amber-500/25 mb-3.5">
                {{ config('club.short_name', 'MC') }}
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ config('club.name', 'Malabar Club') }}</h1>
            <p class="text-xs text-slate-400 mt-1">{{ config('club.location', 'Vadakkencherry, Kerala') }} &bull; Club Access Terminal</p>
        </div>

        <!-- Unified Login Card -->
        <div id="login-portal-tabs"
             data-initial-tab="{{ $initialTab }}"
             data-force-tab="{{ $forceInitialTab ? '1' : '0' }}"
             class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md">

            @if(session('status'))
                <div role="status" class="mb-5 p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('info'))
                <div role="status" class="mb-5 p-3.5 rounded-2xl bg-sky-500/10 border border-sky-500/30 text-xs text-sky-300">
                    {{ session('info') }}
                </div>
            @endif

            <!-- Segmented Portal Switcher -->
            <div role="tablist" aria-label="Sign in portal"
                 class="grid grid-cols-2 gap-1.5 p-1.5 rounded-2xl bg-slate-950 border border-slate-800 mb-6">
                <button type="button"
                        role="tab"
                        id="tab-staff"
                        data-tab-target="staff"
                        aria-selected="{{ $initialTab === 'staff' ? 'true' : 'false' }}"
                        tabindex="{{ $initialTab === 'staff' ? '0' : '-1' }}"
                        class="portal-tab min-h-12 px-4 py-2.5 rounded-xl text-sm font-bold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                    Staff Terminal
                </button>
                <button type="button"
                        role="tab"
                        id="tab-member"
                        data-tab-target="member"
                        aria-selected="{{ $initialTab === 'member' ? 'true' : 'false' }}"
                        tabindex="{{ $initialTab === 'member' ? '0' : '-1' }}"
                        class="portal-tab min-h-12 px-4 py-2.5 rounded-xl text-sm font-bold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                    Member Portal
                </button>
            </div>

            <!-- Panels container uses block layout (no flex gap) so hidden="until-found" collapses completely -->
            <div>
                <!-- Staff Panel -->
                <div id="panel-staff"
                     role="tabpanel"
                     aria-labelledby="tab-staff"
                     @if($initialTab !== 'staff') hidden="until-found" @endif>
                    <div class="space-y-4">
                        @if ($staffHasError)
                            <div id="staff-form-error" role="alert" aria-live="polite"
                                 class="p-4 rounded-2xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-xs flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>{{ $errors->first() }}</span>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" aria-labelledby="staff-form-heading" class="space-y-4" data-login-form>
                            @csrf
                            <input type="hidden" name="_portal" value="staff">
                            <h2 id="staff-form-heading" class="sr-only">Staff Terminal Sign In</h2>

                            <div>
                                <label for="staff-email" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                    Staff Official Email <span aria-hidden="true" class="text-amber-400">*</span>
                                </label>
                                <input type="email"
                                       id="staff-email"
                                       name="email"
                                       value="{{ old('email') }}"
                                       required
                                       autocomplete="username"
                                       autocorrect="off"
                                       autocapitalize="none"
                                       spellcheck="false"
                                       enterkeyhint="next"
                                       @if($staffHasError) aria-invalid="true" aria-describedby="staff-form-error" @endif
                                       class="login-input w-full min-h-12 px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:border-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors shadow-2xs"
                                       placeholder="staff@malabarclub.local">
                            </div>

                            <div>
                                <label for="staff-password" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                    Password <span aria-hidden="true" class="text-amber-400">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password"
                                           id="staff-password"
                                           name="password"
                                           required
                                           autocomplete="current-password"
                                           enterkeyhint="done"
                                           class="login-input w-full min-h-12 pl-4 pr-14 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:border-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors shadow-2xs"
                                           placeholder="••••••••••••">
                                    <button type="button"
                                            data-toggle-password="staff-password"
                                            aria-pressed="false"
                                            aria-label="Show password"
                                            aria-describedby="staff-password-warning"
                                            class="absolute inset-y-0 right-0 min-w-12 px-3 inline-flex items-center justify-center text-slate-400 hover:text-amber-400 rounded-r-xl cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <span data-eye-show class="text-[11px] font-bold uppercase tracking-wide">Show</span>
                                        <span data-eye-hide class="hidden text-[11px] font-bold uppercase tracking-wide text-amber-400">Hide</span>
                                    </button>
                                </div>
                                <span id="staff-password-warning" class="sr-only">Warning: this will display your password on the screen.</span>
                            </div>

                            <div class="flex items-center justify-between text-xs pt-1">
                                <div class="flex items-center gap-2.5">
                                    <input type="checkbox"
                                           id="staff-remember"
                                           name="remember"
                                           value="1"
                                           class="w-5 h-5 rounded bg-slate-950 border-slate-700 accent-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 cursor-pointer">
                                    <label for="staff-remember" class="text-slate-300 select-none cursor-pointer py-1.5">
                                        Remember this terminal session
                                    </label>
                                </div>
                            </div>

                            <button type="submit"
                                    class="w-full min-h-12 py-3.5 px-5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-md shadow-amber-500/20 active:scale-98 transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                Sign In to Terminal
                            </button>

                            <p class="text-[11px] text-slate-400 text-center">
                                Routes automatically to Admin Console, Guest Rooms (101–105), or Counter POS based on your role.
                            </p>
                        </form>

                        @if(!app()->environment('production'))
                            <!-- Quick Demo Accounts (Development & Staging Only) -->
                            <div class="mt-5 pt-5 border-t border-slate-800">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3 text-center">Development Staff Quick Fill</p>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <button type="button" data-demo-staff="admin@malabarclub.local"
                                        class="min-h-12 p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-colors text-left cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <div class="font-bold text-amber-400">Admin</div>
                                        <div class="text-[10px] text-slate-400">Admin Console</div>
                                    </button>
                                    <button type="button" data-demo-staff="manager@malabarclub.local"
                                        class="min-h-12 p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-colors text-left cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <div class="font-bold text-amber-400">Manager</div>
                                        <div class="text-[10px] text-slate-400">V. Jayaprakash</div>
                                    </button>
                                    <button type="button" data-demo-staff="reception@malabarclub.local"
                                        class="min-h-12 p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-colors text-left cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <div class="font-bold text-amber-400">Reception</div>
                                        <div class="text-[10px] text-slate-400">Guest Rooms (101–105)</div>
                                    </button>
                                    <button type="button" data-demo-staff="cashier1@malabarclub.local"
                                        class="min-h-12 p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-colors text-left cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <div class="font-bold text-amber-400">Cashier 1</div>
                                        <div class="text-[10px] text-slate-400">Counter POS</div>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Member Panel -->
                <div id="panel-member"
                     role="tabpanel"
                     aria-labelledby="tab-member"
                     @if($initialTab !== 'member') hidden="until-found" @endif>
                    <div class="space-y-4">
                        @if ($memberHasError)
                            <div id="member-form-error" role="alert" aria-live="polite"
                                 class="p-4 rounded-2xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-xs flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>{{ $errors->first() }}</span>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('member.login.submit') }}" aria-labelledby="member-form-heading" class="space-y-4" data-login-form>
                            @csrf
                            <input type="hidden" name="_portal" value="member">
                            <h2 id="member-form-heading" class="sr-only">Member Portal Sign In</h2>

                            <div>
                                <label for="member-login" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                                    Membership Number or Mobile <span aria-hidden="true" class="text-amber-400">*</span>
                                </label>
                                <input type="text"
                                       id="member-login"
                                       name="login"
                                       value="{{ old('login') }}"
                                       required
                                       autocomplete="username"
                                       autocorrect="off"
                                       autocapitalize="none"
                                       spellcheck="false"
                                       enterkeyhint="next"
                                       @if($memberHasError && $errors->has('login')) aria-invalid="true" aria-describedby="member-form-error" @endif
                                       class="login-input w-full min-h-12 px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:border-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors shadow-2xs"
                                       placeholder="e.g. MC-001 or 9895011111">
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="member-password" class="text-xs font-bold uppercase tracking-wider text-slate-300">
                                        Security PIN / Password <span aria-hidden="true" class="text-amber-400">*</span>
                                    </label>
                                    <a href="{{ route('member.activate') }}" class="text-xs font-medium text-amber-400 hover:text-amber-300 transition-colors py-1 px-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 rounded">
                                        Reset PIN?
                                    </a>
                                </div>
                                <div class="relative">
                                    <input type="password"
                                           id="member-password"
                                           name="password"
                                           required
                                           autocomplete="current-password"
                                           enterkeyhint="done"
                                           @if($memberHasError && $errors->has('password')) aria-invalid="true" aria-describedby="member-form-error" @endif
                                           class="login-input w-full min-h-12 pl-4 pr-14 py-3 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:border-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors shadow-2xs"
                                           placeholder="Enter your security PIN or password">
                                    <button type="button"
                                            data-toggle-password="member-password"
                                            aria-pressed="false"
                                            aria-label="Show password"
                                            aria-describedby="member-password-warning"
                                            class="absolute inset-y-0 right-0 min-w-12 px-3 inline-flex items-center justify-center text-slate-400 hover:text-amber-400 rounded-r-xl cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <span data-eye-show class="text-[11px] font-bold uppercase tracking-wide">Show</span>
                                        <span data-eye-hide class="hidden text-[11px] font-bold uppercase tracking-wide text-amber-400">Hide</span>
                                    </button>
                                </div>
                                <span id="member-password-warning" class="sr-only">Warning: this will display your password on the screen.</span>
                            </div>

                            <div class="flex items-center justify-between text-xs pt-1">
                                <div class="flex items-center gap-2.5">
                                    <input type="checkbox"
                                           id="member-remember"
                                           name="remember"
                                           value="1"
                                           class="w-5 h-5 rounded bg-slate-950 border-slate-700 accent-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 cursor-pointer">
                                    <label for="member-remember" class="text-slate-300 select-none cursor-pointer py-1.5">
                                        Stay signed in
                                    </label>
                                </div>
                                <span class="text-[11px] text-slate-400 font-mono">Est. 1978</span>
                            </div>

                            <button type="submit"
                                    class="w-full min-h-12 py-3.5 px-5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-md shadow-amber-500/20 active:scale-98 transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                Sign In to Member Portal
                            </button>
                        </form>

                        <div class="pt-4 border-t border-slate-800/80 text-center space-y-2">
                            <p class="text-xs text-slate-400">
                                First time accessing the portal?
                                <a href="{{ route('member.activate') }}" class="font-bold text-amber-400 hover:text-amber-300 transition-colors underline decoration-amber-500/40 inline-block py-1 px-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 rounded">
                                    Activate your account &amp; set PIN
                                </a>
                            </p>
                        </div>

                        @if(!app()->environment('production'))
                            <div class="pt-3 border-t border-slate-800">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2.5 text-center">Development Member Quick Fill</p>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <button type="button" data-demo-member="MC-001"
                                        class="min-h-12 p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-colors text-left cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <div class="font-bold text-amber-400">MC-001</div>
                                        <div class="text-[10px] text-slate-400">Active Member</div>
                                    </button>
                                    <button type="button" data-demo-member="MC-002"
                                        class="min-h-12 p-2.5 rounded-xl bg-slate-950 hover:bg-slate-800/80 hover:border-amber-500/50 text-slate-300 border border-slate-800 transition-colors text-left cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <div class="font-bold text-amber-400">MC-002</div>
                                        <div class="text-[10px] text-slate-400">Active Member</div>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-6 text-[11px] text-slate-400 font-mono">
            Secure Member Ledger &bull; Strict No-Credit Policy Enforced
        </div>
    </main>

    <script>
        (() => {
            const container = document.getElementById('login-portal-tabs');
            if (!container) return;

            const STORAGE_KEY = 'malabar_login_tab';
            const tabs = Array.from(container.querySelectorAll('[role="tab"]'));
            const panels = {
                staff: document.getElementById('panel-staff'),
                member: document.getElementById('panel-member'),
            };

            function readStoredTab() {
                try {
                    const value = window.localStorage.getItem(STORAGE_KEY);
                    return value === 'member' || value === 'staff' ? value : null;
                } catch (_) {
                    return null;
                }
            }

            function writeStoredTab(tabName) {
                try {
                    window.localStorage.setItem(STORAGE_KEY, tabName);
                } catch (_) {}
            }

            function selectTab(tabName, { focusTab = false, persist = true } = {}) {
                if (tabName !== 'staff' && tabName !== 'member') {
                    tabName = 'staff';
                }

                for (const tab of tabs) {
                    const isSelected = tab.dataset.tabTarget === tabName;
                    tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    tab.tabIndex = isSelected ? 0 : -1;
                    if (isSelected && focusTab) {
                        tab.focus();
                    }
                }

                for (const [key, panel] of Object.entries(panels)) {
                    if (!panel) continue;
                    panel.hidden = key === tabName ? false : 'until-found';
                }

                if (persist) {
                    writeStoredTab(tabName);
                }
            }

            const forceTab = container.dataset.forceTab === '1';
            const serverTab = container.dataset.initialTab || 'staff';
            const initialTab = forceTab ? serverTab : (readStoredTab() || serverTab);
            selectTab(initialTab, { focusTab: false, persist: forceTab });

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => {
                    selectTab(tab.dataset.tabTarget, { focusTab: false, persist: true });
                });

                tab.addEventListener('keydown', (event) => {
                    let targetIndex = null;
                    if (event.key === 'ArrowRight') {
                        targetIndex = (index + 1) % tabs.length;
                    } else if (event.key === 'ArrowLeft') {
                        targetIndex = (index - 1 + tabs.length) % tabs.length;
                    } else if (event.key === 'Home') {
                        targetIndex = 0;
                    } else if (event.key === 'End') {
                        targetIndex = tabs.length - 1;
                    }

                    if (targetIndex !== null) {
                        event.preventDefault();
                        selectTab(tabs[targetIndex].dataset.tabTarget, { focusTab: true, persist: true });
                    }
                });
            });

            for (const [key, panel] of Object.entries(panels)) {
                if (!panel) continue;
                panel.addEventListener('beforematch', () => {
                    selectTab(key, { focusTab: false, persist: true });
                });
            }

            container.querySelectorAll('[data-toggle-password]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const input = document.getElementById(btn.dataset.togglePassword);
                    if (!input) return;

                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                    btn.querySelector('[data-eye-show]')?.classList.toggle('hidden', isHidden);
                    btn.querySelector('[data-eye-hide]')?.classList.toggle('hidden', !isHidden);
                });
            });

            container.querySelectorAll('[data-demo-staff]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const emailInput = document.getElementById('staff-email');
                    const passwordInput = document.getElementById('staff-password');
                    if (emailInput) emailInput.value = btn.dataset.demoStaff || '';
                    if (passwordInput) passwordInput.value = 'Password@123';
                    emailInput?.focus();
                });
            });

            container.querySelectorAll('[data-demo-member]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const loginInput = document.getElementById('member-login');
                    const passwordInput = document.getElementById('member-password');
                    if (loginInput) loginInput.value = btn.dataset.demoMember || '';
                    if (passwordInput) passwordInput.value = 'Member@123';
                    loginInput?.focus();
                });
            });

            container.querySelectorAll('[data-login-form]').forEach((form) => {
                form.addEventListener('submit', () => {
                    if (!form.checkValidity()) return;
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-75', 'cursor-wait');
                    }
                });
            });
        })();
    </script>
</body>
</html>
