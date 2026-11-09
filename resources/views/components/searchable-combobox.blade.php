@props([
    'id',
    'wireModel',
    'wireModelLive' => false,
    'placeholder' => '-- Select an option --',
    'options' => [],
    'searchPlaceholder' => 'Type to search items...',
    'emptyMessage' => 'No matching items found.',
    'class' => '',
])

@php
    $normalizedOptions = collect($options)->map(function ($opt) {
        if (is_array($opt)) {
            return [
                'id' => (string) ($opt['id'] ?? $opt['value'] ?? ''),
                'label' => (string) ($opt['label'] ?? $opt['name'] ?? ''),
                'subtext' => isset($opt['subtext']) ? (string) $opt['subtext'] : null,
            ];
        }
        return [
            'id' => (string) $opt,
            'label' => (string) $opt,
            'subtext' => null,
        ];
    })->values()->toArray();
@endphp

<div x-data="{
    open: false,
    search: '',
    selectedId: '',
    selectedLabel: '',
    options: {{ Js::from($normalizedOptions) }},
    placeholder: '{{ addslashes($placeholder) }}',

    init() {
        this.syncFromSelect();

        @if (!empty($wireModel))
            this.$watch('$wire.{{ $wireModel }}', (newVal) => {
                this.selectedId = newVal ? String(newVal) : '';
                this.updateLabel();
            });
        @endif
    },

    syncFromSelect() {
        if (this.$refs.nativeSelect) {
            this.selectedId = this.$refs.nativeSelect.value ? String(this.$refs.nativeSelect.value) : '';
            this.updateLabel();
        }
    },

    updateLabel() {
        if (!this.selectedId) {
            this.selectedLabel = '';
            return;
        }
        const found = this.options.find(o => String(o.id) === String(this.selectedId));
        this.selectedLabel = found ? found.label : '';
    },

    get filtered() {
        if (!this.search || !this.search.trim()) {
            return this.options;
        }
        const q = this.search.toLowerCase().trim();
        return this.options.filter(o => {
            const l = (o.label || '').toLowerCase();
            const s = (o.subtext || '').toLowerCase();
            const id = (o.id || '').toLowerCase();
            return l.includes(q) || s.includes(q) || id.includes(q);
        });
    },

    toggle() {
        this.open = !this.open;
        if (this.open) {
            this.search = '';
            this.$nextTick(() => {
                if (this.$refs.searchInput) {
                    this.$refs.searchInput.focus();
                }
            });
        }
    },

    selectOption(opt) {
        this.selectedId = opt ? String(opt.id) : '';
        this.selectedLabel = opt ? opt.label : '';
        this.open = false;
        this.search = '';

        if (this.$refs.nativeSelect) {
            this.$refs.nativeSelect.value = this.selectedId;
            this.$refs.nativeSelect.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        @if (!empty($wireModel))
            $wire.set('{{ $wireModel }}', this.selectedId || null);
        @endif
    },

    clear(e) {
        if (e) e.stopPropagation();
        this.selectOption(null);
    }
}"
@click.outside="open = false"
@keydown.escape.window="open = false"
class="relative w-full {{ $class }}">

    <!-- Trigger Button / Box -->
    <div @click="toggle()"
        role="combobox"
        :aria-expanded="open"
        class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus-within:ring-2 focus-within:ring-amber-500 focus-within:border-amber-500 transition-all flex items-center justify-between cursor-pointer select-none shadow-2xs hover:border-slate-400">

        <div class="flex-1 truncate mr-2">
            <template x-if="selectedLabel">
                <span class="text-slate-900 font-bold" x-text="selectedLabel"></span>
            </template>
            <template x-if="!selectedLabel">
                <span class="text-slate-400 font-normal" x-text="placeholder"></span>
            </template>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
            <template x-if="selectedId">
                <button type="button"
                    @click="clear($event)"
                    title="Clear selection"
                    class="p-0.5 text-slate-400 hover:text-rose-600 rounded-md transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </template>

            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200"
                :class="open ? 'rotate-180 text-amber-600' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>

    <!-- Dropdown Panel with Search Textbox -->
    <div x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 translate-y-1 scale-98"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-98"
        style="display: none;"
        class="absolute left-0 right-0 mt-1 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden divide-y divide-slate-100 animate-in">

        <!-- Search Textbox Container -->
        <div class="p-2.5 bg-slate-50 border-b border-slate-100">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text"
                    x-ref="searchInput"
                    x-model="search"
                    @keydown.enter.prevent="if (filtered.length > 0) selectOption(filtered[0])"
                    placeholder="{{ $searchPlaceholder }}"
                    aria-label="{{ $searchPlaceholder }}"
                    class="w-full pl-9 pr-8 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none placeholder-slate-400 text-slate-900 font-medium shadow-2xs">
                
                <button type="button"
                    x-show="search"
                    @click="search = ''; $refs.searchInput.focus()"
                    class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 text-xs">
                    ✕
                </button>
            </div>

            <!-- Item Count Indicator -->
            <div class="flex items-center justify-between px-1 pt-1.5 text-[10px] text-slate-400 font-semibold">
                <span x-text="filtered.length + ' item' + (filtered.length === 1 ? '' : 's') + ' available'"></span>
                <span x-show="search" class="text-amber-600 font-bold">Filtered</span>
            </div>
        </div>

        <!-- Scrollable Option List -->
        <div class="max-h-56 overflow-y-auto p-1.5 space-y-0.5 overscroll-contain">
            <!-- Clear/None option -->
            <div @click="clear()"
                class="px-3 py-2 rounded-xl text-xs cursor-pointer flex items-center justify-between transition-colors text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                <span class="italic text-slate-400" x-text="placeholder"></span>
                <span x-show="!selectedId" class="text-amber-600 text-xs font-bold">✓</span>
            </div>

            <!-- Filtered Options -->
            <template x-for="opt in filtered" :key="opt.id">
                <div @click="selectOption(opt)"
                    class="px-3 py-2 rounded-xl text-xs cursor-pointer flex items-center justify-between transition-colors"
                    :class="String(selectedId) === String(opt.id) ? 'bg-amber-50 text-amber-950 font-bold border-l-4 border-amber-500' : 'text-slate-700 hover:bg-slate-50'">
                    
                    <div class="flex-1 min-w-0 mr-2">
                        <div class="text-xs font-semibold truncate"
                            :class="String(selectedId) === String(opt.id) ? 'text-amber-950' : 'text-slate-900'"
                            x-text="opt.label"></div>
                        <div x-show="opt.subtext"
                            class="text-[10px] text-slate-500 mt-0.5 truncate"
                            x-text="opt.subtext"></div>
                    </div>

                    <svg x-show="String(selectedId) === String(opt.id)"
                        class="w-4 h-4 text-amber-600 shrink-0"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </template>

            <!-- Empty Search State -->
            <div x-show="filtered.length === 0" class="py-6 text-center text-xs text-slate-400">
                <p>{{ $emptyMessage }}</p>
                <p x-show="search" class="text-[11px] text-slate-500 mt-1">No options match "<span x-text="search" class="font-semibold text-slate-700"></span>"</p>
            </div>
        </div>
    </div>

    <!-- Underlying Native HTML Select Element for Livewire sync, accessibility and automated testing -->
    <select id="{{ $id }}"
        wire:model{{ $wireModelLive ? '.live' : '' }}="{{ $wireModel }}"
        x-ref="nativeSelect"
        class="sr-only pointer-events-none"
        tabindex="-1"
        aria-hidden="true">
        <option value="">{{ $placeholder }}</option>
        @foreach ($normalizedOptions as $opt)
            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
        @endforeach
    </select>
</div>

