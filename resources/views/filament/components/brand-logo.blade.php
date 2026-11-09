<div class="flex items-center gap-3 py-1">
    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 flex items-center justify-center font-extrabold text-slate-950 text-base shadow-sm shadow-amber-500/30">
        {{ config('club.short_name', 'MC') }}
    </div>
    <div class="leading-tight">
        <div class="font-extrabold text-base tracking-tight text-slate-900 flex items-center gap-1.5">
            <span>{{ config('club.name', 'Malabar Club') }}</span>
            <span class="text-[10px] font-semibold font-mono px-1.5 py-0.2 rounded-full bg-amber-50 text-amber-800 border border-amber-200">Admin</span>
        </div>
        <div class="text-[10px] font-medium text-slate-600 tracking-wide">{{ config('club.location', 'Vadakkencherry, Kerala') }}</div>
    </div>
</div>
