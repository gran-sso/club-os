<x-layouts.member title="Sign In — Malabar Club Member Portal">
    <div class="max-w-md mx-auto py-6 sm:py-12">
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
            <!-- Header Brand Emblem -->
            <div class="text-center space-y-2">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 p-0.5 mx-auto shadow-lg shadow-amber-500/20">
                    <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center font-serif text-amber-400 font-extrabold text-2xl tracking-wider">
                        MC
                    </div>
                </div>
                <h2 id="pwa-member-login-heading" class="text-xl font-extrabold text-white tracking-tight">Malabar Club</h2>
                <p class="text-xs text-slate-400">Sign in to your member portal &amp; digital card</p>
            </div>

            @if(session('status'))
                <div role="status" class="p-3.5 bg-amber-500/10 border border-amber-500/30 rounded-2xl text-xs text-amber-300">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('info'))
                <div role="status" class="p-3.5 bg-sky-500/10 border border-sky-500/30 rounded-2xl text-xs text-sky-300">
                    {{ session('info') }}
                </div>
            @endif

            <form method="POST" action="{{ route('member.login.submit') }}" aria-labelledby="pwa-member-login-heading" class="space-y-4">
                @csrf
                <input type="hidden" name="_portal" value="member">

                <!-- Membership Number or Mobile Input -->
                <div>
                    <label for="login" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Membership Number or Mobile <span aria-hidden="true" class="text-amber-400">*</span>
                    </label>
                    <input type="text"
                           id="login"
                           name="login"
                           value="{{ old('login') }}"
                           required
                           autofocus
                           autocomplete="username"
                           autocorrect="off"
                           autocapitalize="none"
                           spellcheck="false"
                           enterkeyhint="next"
                           @error('login') aria-invalid="true" aria-describedby="pwa-login-error" @enderror
                           placeholder="e.g. MC-001 or 9895011111"
                           class="w-full min-h-12 px-4 py-3 bg-slate-950 border border-slate-700 focus:border-amber-500 rounded-xl text-base font-semibold text-white placeholder-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors">
                    @error('login')
                        <span id="pwa-login-error" role="alert" class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Password / PIN Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="text-xs font-bold uppercase tracking-wider text-slate-300">
                            Security PIN / Password <span aria-hidden="true" class="text-amber-400">*</span>
                        </label>
                        <a href="{{ route('member.activate') }}" class="text-xs font-medium text-amber-400 hover:text-amber-300 transition-colors py-1 px-1 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                            Reset PIN?
                        </a>
                    </div>
                    <div class="relative">
                        <input type="password"
                               id="password"
                               name="password"
                               required
                               autocomplete="current-password"
                               enterkeyhint="done"
                               @error('password') aria-invalid="true" aria-describedby="pwa-password-error" @enderror
                               placeholder="Enter your security PIN or password"
                               class="w-full min-h-12 pl-4 pr-14 py-3 bg-slate-950 border border-slate-700 focus:border-amber-500 rounded-xl text-base font-semibold text-white placeholder-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 transition-colors">
                        <button type="button"
                                id="pwa-toggle-password"
                                aria-pressed="false"
                                aria-label="Show password"
                                aria-describedby="pwa-password-warning"
                                class="absolute inset-y-0 right-0 min-w-12 px-3 inline-flex items-center justify-center text-slate-400 hover:text-amber-400 rounded-r-xl cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                            <span data-eye-show class="text-[11px] font-bold uppercase tracking-wide">Show</span>
                            <span data-eye-hide class="hidden text-[11px] font-bold uppercase tracking-wide text-amber-400">Hide</span>
                        </button>
                    </div>
                    <span id="pwa-password-warning" class="sr-only">Warning: this will display your password on the screen.</span>
                    @error('password')
                        <span id="pwa-password-error" role="alert" class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <div class="flex items-center gap-2.5">
                        <input type="checkbox"
                               id="remember"
                               name="remember"
                               value="1"
                               class="w-5 h-5 rounded bg-slate-950 border-slate-700 accent-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 cursor-pointer">
                        <label for="remember" class="cursor-pointer text-slate-300 py-1.5 select-none">Stay signed in</label>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono">Malabar Club &bull; 1978</span>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full min-h-12 py-3.5 px-4 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-lg shadow-amber-500/20 active:scale-98 transition-all cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                    Sign In to Member Portal
                </button>
            </form>

            <!-- First-Time Activation Notice -->
            <div class="pt-4 border-t border-slate-800/80 text-center">
                <p class="text-xs text-slate-400">
                    First time accessing the portal?
                    <a href="{{ route('member.activate') }}" class="font-bold text-amber-400 hover:text-amber-300 transition-colors underline decoration-amber-500/40 inline-block py-1 px-1 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                        Activate your account &amp; set PIN
                    </a>
                </p>
                <div class="mt-3 text-xs text-slate-400">
                    Are you staff? <a href="{{ route('login', ['tab' => 'staff']) }}" class="text-amber-400/90 hover:text-amber-300 py-1 px-1 inline-block rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">Staff &amp; Admin Sign In &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const btn = document.getElementById('pwa-toggle-password');
            const input = document.getElementById('password');
            if (!btn || !input) return;

            btn.addEventListener('click', () => {
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                btn.querySelector('[data-eye-show]')?.classList.toggle('hidden', isHidden);
                btn.querySelector('[data-eye-hide]')?.classList.toggle('hidden', !isHidden);
            });
        })();
    </script>
</x-layouts.member>
