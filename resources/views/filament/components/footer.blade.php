<footer class="mt-auto py-4 px-4 sm:px-6 lg:px-8 text-center text-xs text-slate-500 border-t border-slate-200/80 bg-white/60 backdrop-blur-xs print:hidden no-print">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px]">
        <span>&copy; {{ date('Y') }} {{ config('club.name', 'Malabar Club') }}, {{ config('club.location', 'Vadakkencherry, Kerala') }}. All rights reserved.</span>
        <div class="flex items-center gap-3">
            <span class="font-mono text-slate-400">Club Management OS</span>
            <span class="text-slate-300">•</span>
            <span class="inline-flex items-center gap-1 text-slate-500">
                <span>Powered by</span>
                <a href="https://www.skysysonline.com/" target="_blank" rel="noopener noreferrer" class="font-bold text-amber-600 hover:text-amber-700 hover:underline transition-colors">Skysys</a>
            </span>
        </div>
    </div>
</footer>
