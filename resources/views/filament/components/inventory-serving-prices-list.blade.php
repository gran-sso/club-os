@php
    /** @var \App\Domain\Inventory\Models\InventoryItem $item */
    $mappings = $item->servingMappings()
        ->with('tariff')
        ->get()
        ->sortBy(fn ($m) => $m->tariff?->rate_paise ?? 0);
    $canManageTariffs = auth()->user()?->hasClubPermission('tariffs.manage') ?? false;
@endphp

<div class="space-y-3">
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-300">
        <strong>Stock Item:</strong> {{ $item->name }} ({{ $item->sku }}) &bull;
        <strong>Available:</strong>
        {{ number_format((float) $item->current_stock_qty, $item->base_unit === 'ml' ? 0 : 2) }} {{ $item->base_unit }} &bull;
        <strong>Avg Cost:</strong> ₹{{ number_format(((int) $item->current_avg_cost_paise) / 100, 2) }}/{{ $item->base_unit }}
    </div>

    @if ($mappings->isEmpty())
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
            <strong>Not linked to any Menu &amp; Price List item yet.</strong>
            Cashiers cannot sell this stock item at the Counter POS until at least one selling price is linked.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800/80">
                    <tr>
                        <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Code</th>
                        <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Menu / Item Name</th>
                        <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Serving</th>
                        <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Stock Deducted</th>
                        <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Price (GST)</th>
                        <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Status</th>
                        @if ($canManageTariffs)
                            <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Edit</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach ($mappings as $mapping)
                        @php
                            $tariff = $mapping->tariff;
                            $qty = (float) $mapping->serving_qty;
                            $decimals = fmod($qty, 1.0) === 0.0 ? 0 : 3;
                        @endphp
                        @if ($tariff)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-600 dark:text-gray-300">
                                    {{ $tariff->code }}
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">
                                    {{ $tariff->name }}
                                </td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                    {{ $mapping->serving_name }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 font-semibold text-emerald-700 dark:text-emerald-400">
                                    -{{ number_format($qty, $decimals) }} {{ $item->base_unit }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-900 dark:text-gray-100">
                                    ₹{{ number_format($tariff->rate_paise / 100, 2) }}
                                    <span class="text-gray-500">({{ $tariff->tax_percent }}%)</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($tariff->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Inactive</span>
                                    @endif
                                </td>
                                @if ($canManageTariffs)
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <a href="{{ \App\Filament\Resources\Tariffs\TariffResource::getUrl('edit', ['record' => $tariff]) }}"
                                           class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            Edit Price
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

