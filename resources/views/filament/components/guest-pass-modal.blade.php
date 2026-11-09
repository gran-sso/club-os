<div class="space-y-5 text-sm text-slate-800">
    <!-- Printable Guest Badge Slip -->
    <div id="printable-guest-pass" class="max-w-xs mx-auto p-6 bg-white border-2 border-slate-900 rounded-3xl shadow-xl text-center space-y-4 print:border-none print:shadow-none print:p-0">
        <!-- Club Header -->
        <div class="border-b-2 border-slate-900 pb-3">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 text-slate-950 font-black flex items-center justify-center text-xl mb-1.5 shadow-md shadow-amber-500/25">
                {{ config('club.short_name', 'MC') }}
            </div>
            <h2 class="font-extrabold text-lg tracking-tight text-slate-950 uppercase">{{ config('club.name', 'Malabar Club') }}</h2>
            <div class="text-[10px] text-slate-600 font-bold uppercase tracking-wider">{{ config('club.location', 'Vadakkencherry, Kerala') }}</div>
            <div class="inline-block mt-2 px-3 py-0.5 rounded-full bg-slate-950 text-white text-[10px] font-extrabold tracking-widest uppercase">
                Official Guest Pass
            </div>
        </div>

        <!-- Pass Code & Barcode Mock -->
        <div class="py-1">
            <span class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest block">Access Pass Identifier</span>
            <div class="font-mono font-extrabold text-lg text-slate-950 mt-0.5">{{ $pass->pass_code }}</div>
            <!-- Mock barcode bars -->
            <div class="flex justify-center items-center gap-0.5 h-8 mt-2 px-4">
                @for ($i = 0; $i < 38; $i++)
                    <div class="bg-slate-900 h-full {{ $i % 4 === 0 ? 'w-1.5' : ($i % 3 === 0 ? 'w-1' : ($i % 2 === 0 ? 'w-0.5' : 'w-1')) }}"></div>
                @endfor
            </div>
        </div>

        <!-- Details Grid -->
        <div class="text-left text-xs bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Guest Name</span>
                <span class="font-extrabold text-slate-900 text-sm block mt-0.5">{{ $guest->person?->first_name }} {{ $guest->person?->last_name }}</span>
            </div>
            @if ($guest->person?->phone)
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Mobile Contact</span>
                    <span class="font-mono text-slate-800 font-semibold">{{ $guest->person->phone }}</span>
                </div>
            @endif
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sponsoring Member</span>
                <span class="font-bold text-slate-800 block mt-0.5">
                    {{ $guest->sponsor?->name ?? $guest->sponsor?->membership_number ?? 'Affiliated Club Member' }}
                </span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Authorized Venue</span>
                <span class="inline-block mt-0.5 px-2 py-0.5 rounded-md bg-amber-100 text-amber-950 font-extrabold uppercase text-[11px] tracking-wide border border-amber-300">
                    {{ str_replace('_', ' ', $pass->facility_code) }}
                </span>
            </div>
            <div class="pt-2 border-t border-slate-200 grid grid-cols-2 gap-2 text-[10px]">
                <div>
                    <span class="font-bold text-slate-400 uppercase block">Valid From</span>
                    <span class="font-mono font-bold text-slate-800">{{ $pass->valid_from->format('d M, H:i') }}</span>
                </div>
                <div>
                    <span class="font-bold text-slate-400 uppercase block">Valid Until</span>
                    <span class="font-mono font-bold text-slate-800">{{ $pass->valid_until->format('d M, H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="text-[9px] text-slate-500 leading-tight border-t border-slate-200 pt-2 font-medium">
            Strictly non-transferable. Present this physical slip alongside valid Government Photo ID at security gate turnstiles.
        </div>
    </div>

    <!-- Print Action -->
    <div class="flex justify-center pt-1 print:hidden no-print">
        <button type="button" onclick="window.print()" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer print:hidden no-print">
            <svg width="16" height="16" class="w-4 h-4 text-amber-400 shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>Print Official Guest Slip</span>
        </button>
    </div>
</div>
