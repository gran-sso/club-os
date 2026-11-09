@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\ChartWidgetComponent;

    $heading = $this->getHeading();
    $description = $this->getDescription();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight() ?? '260px';
    $tableData = $this->getTableData();
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <div
        x-data="{ activeTab: 'graph' }"
        @if ($pollingInterval)
            wire:poll.{{ $pollingInterval }}="updateChartData"
        @endif
        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-5"
    >
        <!-- Card Header with Navigation Tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-lg">
                    📊
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">{{ $heading }}</h3>
                        @if ($tableData['is_period_fallback'])
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                                7-Day Preview
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                Live Today
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-slate-500">{{ $description }} &bull; {{ $tableData['period_label'] }}</p>
                </div>
            </div>

            <!-- Two-Tab Switcher -->
            <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl text-xs font-semibold self-start sm:self-auto border border-slate-200/60 dark:border-slate-700/60">
                <button
                    type="button"
                    @click="activeTab = 'graph'; $nextTick(() => window.dispatchEvent(new Event('resize')))"
                    :class="activeTab === 'graph'
                        ? 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 shadow-xs'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    class="px-3.5 py-1.5 rounded-lg transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
                >
                    <span>📊</span>
                    <span>Graph View</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'table'"
                    :class="activeTab === 'table'
                        ? 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 shadow-xs'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    class="px-3.5 py-1.5 rounded-lg transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
                >
                    <span>📋</span>
                    <span>Tabular View</span>
                </button>
            </div>
        </div>

        <!-- TAB 1: GRAPH VIEW -->
        <div x-show="activeTab === 'graph'" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <!-- Doughnut Canvas -->
                <div class="lg:col-span-5 flex flex-col items-center justify-center p-2 bg-slate-50/50 dark:bg-slate-950/40 rounded-xl border border-slate-100 dark:border-slate-800/60">
                    <div
                        x-load
                        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                        wire:ignore
                        data-chart-type="{{ $type }}"
                        x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                        class="fi-wi-chart-frame fi-wi-chart-canvas-ctn w-full flex items-center justify-center relative"
                        style="min-height: 250px; max-height: 260px;"
                    >
                        <canvas
                            x-ref="canvas"
                            aria-hidden="true"
                            style="width: 100%; max-height: 250px;"
                        ></canvas>
                    </div>
                </div>

                <!-- Department Metrics Grid -->
                <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($tableData['rows'] as $row)
                        <div class="p-3 bg-white dark:bg-slate-950/60 rounded-xl border border-slate-200/80 dark:border-slate-800 border-l-4 shadow-2xs hover:shadow-xs transition-shadow"
                            style="border-left-color: {{ $row['color'] }};">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <span>{{ $row['icon'] }}</span>
                                    <span>{{ $row['name'] }}</span>
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                    {{ $row['share_pct'] }}%
                                </span>
                            </div>

                            <div class="mt-2 flex items-baseline justify-between">
                                <span class="text-base font-extrabold font-mono text-slate-900 dark:text-white tracking-tight">
                                    {{ $row['amount_formatted'] }}
                                </span>
                                <span class="text-[11px] text-slate-500 font-medium">
                                    {{ $row['bill_count'] }} {{ \Illuminate\Support\Str::plural('bill', $row['bill_count']) }}
                                </span>
                            </div>

                            <div class="mt-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                <div class="h-1.5 rounded-full transition-all duration-500"
                                    style="width: {{ max(3, $row['share_pct']) }}%; background-color: {{ $row['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Total Sales Summary Box -->
                    <div class="p-3 bg-gradient-to-br from-amber-500/10 via-emerald-500/10 to-sky-500/10 rounded-xl border border-amber-500/20 dark:border-amber-500/30 flex flex-col justify-between">
                        <div class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            Total Departmental Sales
                        </div>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-lg font-black font-mono text-slate-900 dark:text-white">
                                {{ $tableData['total_formatted'] }}
                            </span>
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                                {{ $tableData['total_bills'] }} Paid Bills
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: TABULAR VIEW -->
        <div x-show="activeTab === 'table'" class="space-y-3" style="display: none;">
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-xs divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950 font-bold uppercase tracking-wider text-[10px] text-slate-500">
                        <tr>
                            <th scope="col" class="py-3 px-4">Department</th>
                            <th scope="col" class="py-3 px-4">Ledger / Settlement Account</th>
                            <th scope="col" class="py-3 px-4 text-center">Paid Bills</th>
                            <th scope="col" class="py-3 px-4 text-right">Revenue (₹)</th>
                            <th scope="col" class="py-3 px-4 text-right">Revenue Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-slate-900">
                        @foreach ($tableData['rows'] as $row)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">{{ $row['icon'] }}</span>
                                        <div>
                                            <div class="font-bold">{{ $row['name'] }}</div>
                                            <div class="text-[10px] font-mono text-slate-400 uppercase tracking-wider">CODE: {{ $row['code'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-400 font-mono text-[11px]">
                                    {{ $row['account'] }}
                                </td>
                                <td class="py-3 px-4 text-center font-mono">
                                    <span class="px-2.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">
                                        {{ $row['bill_count'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white text-sm">
                                    {{ $row['amount_formatted'] }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2.5">
                                        <div class="w-20 bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-1.5 rounded-full"
                                                style="width: {{ max(4, $row['share_pct']) }}%; background-color: {{ $row['color'] }};"></div>
                                        </div>
                                        <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300 w-12 text-right">
                                            {{ $row['share_pct'] }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50/90 dark:bg-slate-950 font-bold border-t-2 border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white">
                        <tr>
                            <td class="py-3 px-4 font-extrabold text-sm">
                                Total Departmental Sales
                            </td>
                            <td class="py-3 px-4 text-slate-500 font-normal text-[11px]">
                                Reconciled with Cash, Card, UPI &amp; Prepaid tenders
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-xs">
                                <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-700 dark:text-amber-400 font-bold">
                                    {{ $tableData['total_bills'] }} bills
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-base text-emerald-600 dark:text-emerald-400 font-extrabold">
                                {{ $tableData['total_formatted'] }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-slate-500">
                                100.0%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>

