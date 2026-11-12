<div class="overflow-x-auto rounded-2xl border border-slate-200/90 shadow-2xs">
    <table class="w-full text-left text-xs text-slate-700">
        <thead class="bg-slate-50 font-bold text-slate-600 uppercase tracking-wider text-[11px] border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Family Dependent Name</th>
                <th class="px-4 py-3">Relationship</th>
                <th class="px-4 py-3">Contact Phone</th>
                <th class="px-4 py-3 text-center">Smartcard Spending Allowed</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse($dependents as $dep)
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="px-4 py-3 font-semibold text-slate-900">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 font-bold flex items-center justify-center text-[10px]">
                                {{ substr($dep->person?->first_name ?? 'D', 0, 1) }}
                            </div>
                            <span class="text-sm font-bold text-slate-900">{{ $dep->person?->full_name ?? '—' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs font-semibold text-slate-700 capitalize">
                        {{ $dep->relationship }}
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-600">
                        {{ $dep->person?->phone ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($dep->can_spend_wallet)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Authorized to Debit
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> No Wallet Access (Authorization Required)
                            </span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-slate-400 text-xs italic">
                        No family dependents currently registered for this primary member.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
