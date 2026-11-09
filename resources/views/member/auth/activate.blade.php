<x-layouts.member title="Activate Account & Set PIN — Malabar Club">
    <div class="max-w-md mx-auto py-6 sm:py-12">
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
            <div class="text-center space-y-2">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-2xl mx-auto text-amber-400">
                    🔐
                </div>
                <h2 class="text-xl font-extrabold text-white tracking-tight">Activate Member Access</h2>
                <p class="text-xs text-slate-400">Verify your registered mobile number and set your private security PIN</p>
            </div>

            <form method="POST" action="{{ route('member.activate') }}" class="space-y-4">
                @csrf

                <!-- Membership Number -->
                <div>
                    <label for="membership_number" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                        Membership Number
                    </label>
                    <input type="text" id="membership_number" name="membership_number" value="{{ old('membership_number', $prefillNum) }}" required autofocus
                        placeholder="e.g. MC-001"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 focus:border-amber-500 rounded-xl text-sm font-semibold text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition-all font-mono">
                    @error('membership_number')
                        <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Registered Mobile Phone -->
                <div>
                    <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                        Registered Mobile Number
                    </label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required
                        placeholder="e.g. 9895011111 (10-digit mobile)"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 focus:border-amber-500 rounded-xl text-sm font-semibold text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition-all">
                    <span class="text-[10px] text-slate-500 mt-1 block">Must match the phone number registered with Malabar Club records.</span>
                    @error('phone')
                        <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- New PIN -->
                <div>
                    <label for="pin" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                        Create Security PIN / Password
                    </label>
                    <input type="password" id="pin" name="pin" required
                        placeholder="Minimum 4 characters or digits"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 focus:border-amber-500 rounded-xl text-sm font-semibold text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition-all">
                    @error('pin')
                        <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Confirm PIN -->
                <div>
                    <label for="pin_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                        Confirm Security PIN
                    </label>
                    <input type="password" id="pin_confirmation" name="pin_confirmation" required
                        placeholder="Re-enter security PIN"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 focus:border-amber-500 rounded-xl text-sm font-semibold text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition-all">
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-lg shadow-amber-500/20 active:scale-98 transition-all cursor-pointer">
                        Set PIN &amp; Open Portal
                    </button>
                </div>
            </form>

            <div class="pt-4 border-t border-slate-800/80 text-center">
                <a href="{{ route('member.login') }}" class="text-xs text-slate-400 hover:text-white transition-colors">
                    &larr; Return to Sign In
                </a>
            </div>
        </div>
    </div>
</x-layouts.member>

