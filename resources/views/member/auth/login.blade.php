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
                <h2 class="text-xl font-extrabold text-white tracking-tight">Malabar Club</h2>
                <p class="text-xs text-slate-400">Sign in to your member portal &amp; digital card</p>
            </div>

            @if(session('status'))
                <div class="p-3.5 bg-amber-500/10 border border-amber-500/30 rounded-2xl text-xs text-amber-300">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('info'))
                <div class="p-3.5 bg-sky-500/10 border border-sky-500/30 rounded-2xl text-xs text-sky-300">
                    {{ session('info') }}
                </div>
            @endif

            <form method="POST" action="{{ route('member.login') }}" class="space-y-4">
                @csrf

                <!-- Membership Number or Mobile Input -->
                <div>
                    <label for="login" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                        Membership Number or Mobile
                    </label>
                    <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus
                        placeholder="e.g. MC-001 or 9895011111"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 focus:border-amber-500 rounded-xl text-sm font-semibold text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition-all">
                    @error('login')
                        <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Password / PIN Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Security PIN / Password
                        </label>
                        <a href="{{ route('member.activate') }}" class="text-[11px] font-medium text-amber-400 hover:text-amber-300 transition-colors">
                            Reset PIN?
                        </a>
                    </div>
                    <input type="password" id="password" name="password" required
                        placeholder="Enter your security PIN or password"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 focus:border-amber-500 rounded-xl text-sm font-semibold text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition-all">
                    @error('password')
                        <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-400">
                        <input type="checkbox" name="remember" class="rounded bg-slate-950 border-slate-700 text-amber-500 focus:ring-amber-500/20">
                        <span>Stay signed in</span>
                    </label>
                    <span class="text-[10px] text-slate-500 font-mono">Malabar Club &bull; 1978</span>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-lg shadow-amber-500/20 active:scale-98 transition-all cursor-pointer">
                    Sign In to Member Portal
                </button>
            </form>

            <!-- First-Time Activation Notice -->
            <div class="pt-4 border-t border-slate-800/80 text-center">
                <p class="text-xs text-slate-400">
                    First time accessing the portal?
                    <a href="{{ route('member.activate') }}" class="font-bold text-amber-400 hover:text-amber-300 transition-colors underline decoration-amber-500/40">
                        Activate your account &amp; set PIN
                    </a>
                </p>
                <div class="mt-3 text-[11px] text-slate-500">
                    Are you staff? <a href="{{ route('login') }}" class="text-slate-400 hover:text-slate-300">Staff Counter Sign In &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.member>

