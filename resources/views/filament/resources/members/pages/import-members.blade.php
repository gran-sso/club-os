<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Guidance Banner -->
        <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-sky-50 to-indigo-50 dark:from-sky-950/40 dark:to-indigo-950/30 border border-sky-200 dark:border-sky-800/70 text-xs sm:text-sm text-sky-950 dark:text-sky-100 flex items-start gap-3.5 shadow-2xs">
            <div class="w-9 h-9 rounded-xl bg-sky-600 text-white font-bold flex items-center justify-center shrink-0">
                <x-heroicon-o-information-circle class="w-5 h-5" />
            </div>
            <div class="space-y-1 leading-relaxed">
                <p class="font-bold text-sky-900 dark:text-sky-200">
                    Onboarding Existing Malabar Club Members (No Fee Demand &amp; Reconciled Opening Wallets)
                </p>
                <p class="text-sky-800 dark:text-sky-300 text-xs">
                    Upload your CSV roster exported from Excel. By default, existing active members are marked as having already settled their One-Time Admission Fee and current Annual Dues with <strong>zero current-day counter receipts</strong> generated.
                    If optional <code class="px-1.5 py-0.5 rounded bg-white/80 dark:bg-slate-900 font-mono text-[11px]">opening_balance_rupees</code> values are supplied, the importer creates immutable opening wallet credits and posts one balanced General Ledger journal (<strong>Debit 3010 &rarr; Credit 2010 Prepaid Advances Liability</strong>).
                </p>
            </div>
        </div>

        @if ($uploadError)
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-200 text-xs sm:text-sm font-medium flex items-start gap-2.5">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
                <div>{{ $uploadError }}</div>
            </div>
        @endif

        <!-- Completed Import Batch Summary + Remaining Error Rows -->
        @if ($completedBatchResult)
            @php
                $exec = $completedBatchResult['execution_summary'] ?? [];
                $remErrors = $completedBatchResult['errors'] ?? [];
            @endphp
            <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-emerald-300 dark:border-emerald-800 shadow-xs space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 flex items-center justify-center">
                            <x-heroicon-o-check-circle class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                                Import Committed &mdash; Batch {{ $completedBatchResult['batch_number'] }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Clean member profiles have been added cleanly with zero fee collection receipts created.
                            </p>
                        </div>
                    </div>
                    <a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('index') }}"
                       class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition">
                        View Imported Members
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 text-xs">
                    <div class="p-3.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                        <span class="text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-300 block">Members Imported</span>
                        <span class="text-2xl font-black font-mono text-emerald-900 dark:text-emerald-200">{{ $completedBatchResult['imported_rows'] }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800">
                        <span class="text-[10px] font-bold uppercase text-rose-700 dark:text-rose-300 block">Skipped Error Rows</span>
                        <span class="text-2xl font-black font-mono text-rose-900 dark:text-rose-200">{{ $completedBatchResult['failed_rows'] }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">RFID Cards Linked</span>
                        <span class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $exec['cards_issued_count'] ?? 0 }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">Opening Wallet Liability</span>
                        <span class="text-xl font-black font-mono text-slate-900 dark:text-white">
                            ₹{{ number_format(((int) ($exec['total_opening_balance_paise'] ?? 0)) / 100, 2) }}
                        </span>
                        @if (!empty($exec['journal_number']))
                            <span class="text-[10px] font-mono text-emerald-700 dark:text-emerald-400 block mt-0.5">
                                {{ $exec['journal_number'] }}
                            </span>
                        @endif
                    </div>
                </div>

                @if (!empty($remErrors))
                    <div class="p-4 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-bold text-rose-900 dark:text-rose-200">
                                    Remaining Rows with Errors ({{ count($remErrors) }})
                                </h4>
                                <p class="text-xs text-rose-700 dark:text-rose-300">
                                    These rows were NOT imported. Download the CSV below, fix the highlighted values in Excel, and upload it directly without deleting any column.
                                </p>
                            </div>
                            <button type="button"
                                    wire:click="downloadCompletedErrorsCsv"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shrink-0 transition">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                Download Remaining Error Rows CSV
                            </button>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-900">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-rose-100/70 dark:bg-rose-950/60 text-rose-950 dark:text-rose-200 uppercase text-[10px] font-bold">
                                    <tr>
                                        <th class="px-3 py-2">CSV Row</th>
                                        <th class="px-3 py-2">Member #</th>
                                        <th class="px-3 py-2">Name</th>
                                        <th class="px-3 py-2">Error Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-rose-100 dark:divide-rose-900/60">
                                    @foreach ($remErrors as $err)
                                        <tr>
                                            <td class="px-3 py-2 font-mono font-bold text-slate-700 dark:text-slate-300">#{{ $err['row'] ?? '-' }}</td>
                                            <td class="px-3 py-2 font-mono text-slate-800 dark:text-slate-200">{{ ($err['membership_number'] ?? '') !== '' ? $err['membership_number'] : '—' }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ ($err['name'] ?? '') !== '' ? $err['name'] : '—' }}</td>
                                            <td class="px-3 py-2 text-rose-700 dark:text-rose-300 font-medium">{{ $err['error'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Step 1: CSV Upload & Migration Defaults -->
        @if (!$previewResult)
            <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3.5">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                            Step 1 &mdash; Prepare &amp; Upload Member List (CSV)
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Need the exact format? Click <strong>Download Sample CSV</strong> above, replace the 3 sample rows with your club members, and upload below.
                        </p>
                    </div>
                    <span class="px-2.5 py-1 text-[11px] font-mono font-bold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        Max {{ \App\Domain\Membership\Services\MemberService::MAX_CSV_IMPORT_ROWS }} rows / batch
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">
                            Membership Fee Status for Imported Members
                        </label>
                        <select wire:model.live="feeMode"
                                class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500">
                            <option value="already_paid">Existing Members &mdash; Joining &amp; Current Fees Already Paid (Recommended)</option>
                            <option value="pending">Unpaid Members &mdash; Ask for Payment Later</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            Per-row <code class="font-mono">admission_fee_status</code> and <code class="font-mono">annual_fee_status</code> columns in your CSV always override this default when specified.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">
                            Blank <code class="font-mono">valid_until</code> Handling (Annual Categories like ORD/CORP)
                        </label>
                        <select wire:model.live="blankValidUntilMode"
                                class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500">
                            <option value="fy_end">Set to End of Current Financial Year ({{ \Carbon\Carbon::parse($this->defaultFyEndDate)->format('d M Y') }})</option>
                            <option value="one_year_from_joined">1 Year from Each Member's Joined Date</option>
                            <option value="custom_date">Use Custom Default Expiry Date</option>
                            <option value="require">Require Explicit valid_until Date in CSV (Reject Blank Rows)</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            Permanent categories without recurring annual fees (e.g. Life Members) always stay continuous (<code class="font-mono">null</code>).
                        </p>
                    </div>

                    @if ($blankValidUntilMode === 'custom_date')
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">
                                Custom Default Expiry Date
                            </label>
                            <input type="date"
                                   wire:model="customValidUntil"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white" />
                            @error('customValidUntil')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">
                            Date for Opening Wallet Balances
                        </label>
                        <input type="date"
                               wire:model="openingBalanceDate"
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white" />
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            Used only if rows contain positive <code class="font-mono">opening_balance_rupees</code>; must fall in an open financial period.
                        </p>
                        @error('openingBalanceDate')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-start gap-2.5 pt-2">
                        <input id="queueTallyOutbox"
                               type="checkbox"
                               wire:model="queueTallyOutbox"
                               class="mt-0.5 rounded border-slate-300 dark:border-slate-700 text-amber-600 focus:ring-amber-500" />
                        <label for="queueTallyOutbox" class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                            <span class="font-bold block">Also Send Opening Wallet Balances to Tally</span>
                            Leave unchecked if your Tally company already holds past opening balances from before today.
                        </label>
                    </div>
                </div>

                <!-- File Upload Box -->
                <div class="p-4 sm:p-5 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/40 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="space-y-1">
                            <label for="csvUploadInput" class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                                Select CSV File (.csv or .txt, max 5 MB)
                            </label>
                            <input id="csvUploadInput"
                                   type="file"
                                   wire:model="csvFile"
                                   accept=".csv,.txt"
                                   class="block w-full text-xs text-slate-600 dark:text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500 file:text-slate-950 hover:file:bg-amber-400 cursor-pointer" />
                            @error('csvFile')
                                <p class="text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="button"
                                wire:click="runPreview"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-slate-900 hover:bg-slate-800 dark:bg-amber-500 dark:hover:bg-amber-400 text-white dark:text-slate-950 shadow-xs disabled:opacity-60 transition shrink-0">
                            <x-heroicon-o-document-magnifying-glass class="w-4 h-4" />
                            <span wire:loading.remove wire:target="runPreview,csvFile">Check &amp; Preview File</span>
                            <span wire:loading wire:target="runPreview,csvFile">Checking File...</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Step 2: Dry-Run Preview -->
        @if ($previewResult)
            @php
                $summary = $previewResult['summary'] ?? [];
                $errors = $previewResult['errors'] ?? [];
                $warnings = $previewResult['warnings'] ?? [];
                $validRows = $previewResult['valid_rows'] ?? [];
            @endphp
            <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                            Step 2 &mdash; Preview Before Importing (Batch {{ $previewBatchNumber }})
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Nothing has been saved yet. Review ready rows, warnings, and any skipped rows before confirming.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                                wire:click="resetPreview"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                            Cancel / Upload Another File
                        </button>

                        @if (($previewResult['valid_count'] ?? 0) > 0)
                            <button type="button"
                                    wire:click="confirmImport"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs disabled:opacity-60 transition">
                                <x-heroicon-o-check-badge class="w-4 h-4" />
                                <span wire:loading.remove wire:target="confirmImport">
                                    Import {{ $previewResult['valid_count'] }} Clean Member(s)
                                </span>
                                <span wire:loading wire:target="confirmImport">Importing Batch...</span>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Summary KPI Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase text-slate-500 block">Total Rows</span>
                        <span class="text-xl font-black font-mono text-slate-900 dark:text-white">{{ $previewResult['total_rows'] ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                        <span class="text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-300 block">Clean / Ready</span>
                        <span class="text-xl font-black font-mono text-emerald-800 dark:text-emerald-200">{{ $previewResult['valid_count'] ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800">
                        <span class="text-[10px] font-bold uppercase text-rose-700 dark:text-rose-300 block">Rows with Errors</span>
                        <span class="text-xl font-black font-mono text-rose-800 dark:text-rose-200">{{ $previewResult['failed_count'] ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
                        <span class="text-[10px] font-bold uppercase text-amber-700 dark:text-amber-300 block">Data Warnings</span>
                        <span class="text-xl font-black font-mono text-amber-800 dark:text-amber-200">{{ $previewResult['warning_count'] ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700">
                        <span class="text-[10px] font-bold uppercase text-slate-500 block">RFID Cards</span>
                        <span class="text-xl font-black font-mono text-slate-900 dark:text-white">{{ $summary['cards_to_issue_count'] ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-sky-50/70 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800">
                        <span class="text-[10px] font-bold uppercase text-sky-700 dark:text-sky-300 block">Opening Wallets</span>
                        <span class="text-lg font-black font-mono text-sky-900 dark:text-sky-200">
                            ₹{{ number_format(((int) ($summary['total_opening_balance_paise'] ?? 0)) / 100, 2) }}
                        </span>
                    </div>
                </div>

                <!-- Error Rows Section -->
                @if (!empty($errors))
                    <div class="p-4 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-bold text-rose-900 dark:text-rose-200">
                                    Skipped Rows with Validation Errors ({{ count($errors) }})
                                </h4>
                                <p class="text-xs text-rose-700 dark:text-rose-300">
                                    These rows will be skipped if you import clean rows now, and can be downloaded to fix and re-upload.
                                </p>
                            </div>
                            <button type="button"
                                    wire:click="downloadPreviewErrorsCsv"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shrink-0 transition">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                Download Error Rows CSV
                            </button>
                        </div>

                        <div class="overflow-x-auto max-h-72 rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-900">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-rose-100/70 dark:bg-rose-950/60 text-rose-950 dark:text-rose-200 uppercase text-[10px] font-bold sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2">CSV Row</th>
                                        <th class="px-3 py-2">Member #</th>
                                        <th class="px-3 py-2">Name</th>
                                        <th class="px-3 py-2">Reason</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-rose-100 dark:divide-rose-900/60">
                                    @foreach ($errors as $err)
                                        <tr>
                                            <td class="px-3 py-2 font-mono font-bold text-slate-700 dark:text-slate-300">#{{ $err['row'] ?? '-' }}</td>
                                            <td class="px-3 py-2 font-mono text-slate-800 dark:text-slate-200">{{ ($err['membership_number'] ?? '') !== '' ? $err['membership_number'] : '—' }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ ($err['name'] ?? '') !== '' ? $err['name'] : '—' }}</td>
                                            <td class="px-3 py-2 text-rose-700 dark:text-rose-300 font-medium">{{ $err['error'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- Warnings Section -->
                @if (!empty($warnings))
                    <div class="p-4 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-amber-900 dark:text-amber-200">
                            Non-Blocking Data Hygiene Warnings ({{ count($warnings) }})
                        </h4>
                        <div class="max-h-40 overflow-y-auto space-y-1 text-xs text-amber-900 dark:text-amber-300">
                            @foreach (array_slice($warnings, 0, 50) as $warn)
                                <div class="flex items-start gap-2">
                                    <span class="font-mono font-bold shrink-0">Row #{{ $warn['row'] }} ({{ $warn['membership_number'] }}):</span>
                                    <span>{{ $warn['warning'] }}</span>
                                </div>
                            @endforeach
                            @if (count($warnings) > 50)
                                <p class="italic text-[11px]">Showing first 50 of {{ count($warnings) }} warnings.</p>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Valid Clean Rows Preview -->
                @if (!empty($validRows))
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                Clean Rows Ready for Import ({{ count($validRows) }})
                            </h4>
                            @if (count($validRows) > 100)
                                <span class="text-xs text-slate-500">Showing first 100 of {{ count($validRows) }} clean rows</span>
                            @endif
                        </div>

                        <div class="overflow-x-auto max-h-96 rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 uppercase text-[10px] font-bold sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2">Member #</th>
                                        <th class="px-3 py-2">Name</th>
                                        <th class="px-3 py-2">Mobile</th>
                                        <th class="px-3 py-2">Category</th>
                                        <th class="px-3 py-2">Standing</th>
                                        <th class="px-3 py-2">Valid Until</th>
                                        <th class="px-3 py-2">Admission Fee</th>
                                        <th class="px-3 py-2">Annual Dues</th>
                                        <th class="px-3 py-2 text-right">Opening Wallet</th>
                                        <th class="px-3 py-2">RFID Card</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                                    @foreach (array_slice($validRows, 0, 100) as $vRow)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                            <td class="px-3 py-2 font-mono font-bold text-slate-900 dark:text-white">{{ $vRow['membership_number'] }}</td>
                                            <td class="px-3 py-2 text-slate-800 dark:text-slate-200">{{ trim($vRow['first_name'] . ' ' . $vRow['last_name']) }}</td>
                                            <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-400">{{ $vRow['phone'] !== '' ? $vRow['phone'] : '—' }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono font-bold text-[10px] text-slate-800 dark:text-slate-200">
                                                    {{ $vRow['category_code'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 capitalize text-slate-700 dark:text-slate-300">{{ $vRow['status'] }}</td>
                                            <td class="px-3 py-2 font-mono text-slate-700 dark:text-slate-300">
                                                {{ $vRow['valid_until'] ? \Carbon\Carbon::parse($vRow['valid_until'])->format('d M Y') : 'Continuous / Lifetime' }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $vRow['admission_fee_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($vRow['admission_fee_status'] === 'waived' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                                                    {{ $vRow['admission_fee_status'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $vRow['annual_fee_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($vRow['annual_fee_status'] === 'waived' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                                                    {{ $vRow['annual_fee_status'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 font-mono text-right text-slate-900 dark:text-white">
                                                ₹{{ number_format(((int) ($vRow['opening_balance_paise'] ?? 0)) / 100, 2) }}
                                            </td>
                                            <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-400">
                                                {{ $vRow['rfid_card_number'] ?: '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Import Batch Audit History -->
        <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                    Recent Member Import Batches
                </h3>
                <span class="text-xs text-slate-500">Auditable onboarding batches &amp; downloadable skipped-row logs</span>
            </div>

            @if ($this->recentBatches->isEmpty())
                <p class="text-xs text-slate-500 py-4 text-center">No CSV member import batches have been recorded yet.</p>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 uppercase text-[10px] font-bold">
                            <tr>
                                <th class="px-3 py-2">Batch #</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Operator</th>
                                <th class="px-3 py-2">Total</th>
                                <th class="px-3 py-2">Imported</th>
                                <th class="px-3 py-2">Failed</th>
                                <th class="px-3 py-2">Opening Journal</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Error CSV</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($this->recentBatches as $batch)
                                @php
                                    $bSummary = $batch->preview_data['execution_summary'] ?? [];
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 font-mono font-bold text-slate-900 dark:text-white">{{ $batch->batch_number }}</td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $batch->created_at?->format('d M Y H:i') }}</td>
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $batch->creator?->name ?? 'System' }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $batch->total_rows }}</td>
                                    <td class="px-3 py-2 font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ $batch->imported_rows }}</td>
                                    <td class="px-3 py-2 font-mono font-bold {{ $batch->failed_rows > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500' }}">{{ $batch->failed_rows }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-700 dark:text-slate-300">
                                        @if (!empty($bSummary['journal_number']))
                                            {{ $bSummary['journal_number'] }} (₹{{ number_format(((int) ($bSummary['total_opening_balance_paise'] ?? 0)) / 100, 2) }})
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $batch->status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($batch->status === 'preview' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                                            {{ $batch->status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if (!empty($batch->errors))
                                            <button type="button"
                                                    wire:click="downloadBatchErrorsCsv({{ $batch->id }})"
                                                    class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 hover:underline font-bold">
                                                <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                                                Download ({{ count($batch->errors) }})
                                            </button>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>

