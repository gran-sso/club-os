@php
    /** @var \App\Domain\Shared\Models\AuditLog $log */
    $oldValues = $log->old_values ?? [];
    $newValues = $log->new_values ?? [];
    $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
@endphp

<div class="space-y-4 text-xs">
    <!-- Meta Summary Grid -->
    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl dark:bg-slate-800 dark:border-slate-700">
        <div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Timestamp</div>
            <div class="font-bold text-slate-900 dark:text-white mt-0.5">
                {{ $log->created_at ? $log->created_at->format('d M Y, h:i:s A') : 'N/A' }}
            </div>
            <div class="text-[10px] text-slate-400">
                {{ $log->created_at ? $log->created_at->diffForHumans() : '' }}
            </div>
        </div>

        <div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Staff User</div>
            <div class="font-bold text-slate-900 dark:text-white mt-0.5">
                {{ $log->user_name ?? ($log->user?->name ?? 'System') }}
            </div>
            <div class="text-[10px] text-slate-500 font-medium">
                Role: <span class="uppercase font-semibold">{{ $log->user_role ?? 'System' }}</span>
            </div>
        </div>

        <div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Action &amp; Category</div>
            <div class="font-mono text-slate-900 dark:text-white font-semibold mt-0.5">
                {{ $log->action }}
            </div>
            <div class="text-[10px] text-slate-400 uppercase">
                Category: {{ $log->category }}
            </div>
        </div>

        <div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">IP Address &amp; Device</div>
            <div class="font-mono text-slate-900 dark:text-white mt-0.5">
                {{ $log->ip_address ?? 'Local / CLI' }}
            </div>
            <div class="text-[10px] text-slate-400 truncate max-w-xs" title="{{ $log->user_agent }}">
                {{ $log->user_agent ? Str::limit($log->user_agent, 40) : 'Internal' }}
            </div>
        </div>

        @if ($log->auditable_type)
            <div class="col-span-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Target Entity</div>
                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                </div>
            </div>
        @endif
    </div>

    <!-- Description -->
    <div class="p-3 bg-white border border-slate-200 rounded-xl dark:bg-slate-900 dark:border-slate-700">
        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Action Description</div>
        <div class="text-slate-900 dark:text-white font-medium leading-relaxed">
            {{ $log->description }}
        </div>
    </div>

    <!-- Values Diff Table -->
    @if (!empty($allKeys))
        <div class="space-y-1.5">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Modified Field Values (Audit Snapshot)</div>
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-300">Field / Key</th>
                            @if (!empty($oldValues))
                                <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-300">Previous Value</th>
                            @endif
                            <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-300">New Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900 font-mono text-[11px]">
                        @foreach ($allKeys as $key)
                            @php
                                $old = $oldValues[$key] ?? null;
                                $new = $newValues[$key] ?? null;
                                $isChanged = ($old !== null && $new !== null && $old !== $new);
                            @endphp
                            <tr class="{{ $isChanged ? 'bg-amber-50/50 dark:bg-amber-950/20' : '' }}">
                                <td class="px-3 py-1.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    {{ $key }}
                                </td>
                                @if (!empty($oldValues))
                                    <td class="px-3 py-1.5 text-slate-600 dark:text-slate-400">
                                        @if (is_array($old))
                                            <pre class="text-[10px]">{{ json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        @elseif (is_bool($old))
                                            <span class="font-bold">{{ $old ? 'TRUE' : 'FALSE' }}</span>
                                        @elseif ($old === null)
                                            <span class="text-slate-400 italic">null</span>
                                        @else
                                            {{ (string) $old }}
                                        @endif
                                    </td>
                                @endif
                                <td class="px-3 py-1.5 {{ $isChanged ? 'text-emerald-700 font-bold dark:text-emerald-400' : 'text-slate-800 dark:text-slate-200' }}">
                                    @if (is_array($new))
                                        <pre class="text-[10px]">{{ json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    @elseif (is_bool($new))
                                        <span class="font-bold">{{ $new ? 'TRUE' : 'FALSE' }}</span>
                                    @elseif ($new === null)
                                        <span class="text-slate-400 italic">null</span>
                                    @else
                                        {{ (string) $new }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

