<div data-print-document="filament-modal" class="space-y-4 text-xs text-slate-800">
    <div class="flex items-center justify-between border-b border-slate-200 pb-2.5 print:hidden no-print">
        <div>
            <div class="font-extrabold text-sm text-slate-900">Shift Handover Slip</div>
            <p class="text-[11px] text-slate-500">Official physical cash handover summary for safe deposit.</p>
        </div>
        <button type="button" onclick="window.print()"
            class="px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-amber-400 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer shadow-xs">
            <span>🖨️</span>
            <span>Print 80mm Slip</span>
        </button>
    </div>

    <pre class="m-0 bg-amber-50/40 p-4 rounded-xl border border-dashed border-slate-300 font-mono text-[11px] leading-relaxed whitespace-pre overflow-x-auto shadow-inner text-slate-800 max-h-96 print:max-h-none print:overflow-visible print:border-none print:bg-white print:text-black print:p-0 print:w-fit print:max-w-full print:mx-auto print:text-[9.5pt] print:leading-snug">{{ $slip }}</pre>
</div>

