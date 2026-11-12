<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Top Banner: Environment & Safety Notice -->
        <div class="p-4 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-300 rounded-2xl text-amber-950 text-xs flex items-start gap-3.5 shadow-2xs">
            <div class="w-8 h-8 rounded-xl bg-amber-500 text-slate-950 font-bold flex items-center justify-center shrink-0 shadow-2xs">
                ⚠️
            </div>
            <div class="leading-relaxed">
                <span class="font-bold text-amber-900 uppercase tracking-wide block">Hardware &amp; Accounting Integration Safety Notice</span>
                External hardware adapters (Access Control turnstiles, biometric gates, and Tally Prime XML bridge) are designed with failsafe isolation.
                You can configure the on-premise Tally LAN IP and test communication directly from this panel without touching server files.
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 1. Tally Prime Outbox & Sync Status -->
            <div class="p-6 bg-white border border-slate-200/90 rounded-2xl shadow-xs space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-700 font-bold">
                            📑
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 tracking-tight">Tally Prime XML Outbox Bridge</h3>
                            <span class="text-[11px] text-slate-500">Asynchronous Accounting Voucher Sync</span>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-xs font-mono font-bold rounded-full {{ ($tallyStatus['driver'] ?? '') === 'tally_http' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-amber-100 text-amber-900 border border-amber-300' }}">
                        {{ $tallyStatus['label'] ?? 'SIMULATOR' }}
                    </span>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Outbox-backed journal synchronization guarantees that point-of-sale transactions and member balances post uninterrupted on the club server even if Tally Prime is offline. Transactions are deduplicated idempotently by journal sequence.
                </p>

                <!-- Queue Stats -->
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-500 tracking-wider">Pending Outbox</span>
                        <span class="text-2xl font-extrabold font-mono text-amber-600">{{ $outboxStats['pending'] ?? 0 }}</span>
                    </div>
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-500 tracking-wider">Delivered / Ack</span>
                        <span class="text-2xl font-extrabold font-mono text-emerald-600">{{ $outboxStats['acknowledged'] ?? 0 }}</span>
                    </div>
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-500 tracking-wider">Rejected / Errors</span>
                        <span class="text-2xl font-extrabold font-mono text-rose-600">{{ $outboxStats['failed'] ?? 0 }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button wire:click="dispatchOutbox" type="button"
                        class="flex-1 py-2.5 px-4 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-bold text-xs rounded-xl shadow-xs shadow-amber-500/20 active:scale-98 transition-all cursor-pointer flex items-center justify-center gap-2">
                        <svg width="16" height="16" class="w-4 h-4 text-slate-950 shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span>Dispatch Pending Outbox Messages to Tally</span>
                    </button>

                    @if (($outboxStats['failed'] ?? 0) > 0)
                        <button wire:click="requeueFailedOutbox" type="button"
                            class="px-3.5 py-2.5 bg-rose-50 hover:bg-rose-100 border border-rose-300 text-rose-800 font-bold text-xs rounded-xl transition cursor-pointer">
                            Retry Failed ({{ $outboxStats['failed'] }})
                        </button>
                    @endif
                </div>

                @if (!empty($recentTallyErrors))
                    <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs space-y-1.5">
                        <div class="font-bold text-rose-900">Recent Rejected Vouchers</div>
                        @foreach ($recentTallyErrors as $err)
                            <div class="text-[11px] text-rose-800 font-mono">
                                <strong>{{ $err['journal_number'] }}</strong> ({{ $err['updated_at'] }}): {{ $err['error'] }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Manual XML Download & Hostinger Pull-Bridge Section -->
                <div class="p-4 bg-emerald-50/40 rounded-2xl border border-emerald-200/80 space-y-3.5">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-200/70 pb-2.5">
                        <div>
                            <div class="font-bold text-xs text-slate-900">Offline / Cloud-Safe Tally XML Export (Hostinger Ready)</div>
                            <p class="text-[11px] text-slate-600">Download XML files directly and import via TallyPrime &rarr; Import &rarr; Masters / Vouchers without opening office firewall ports.</p>
                        </div>
                        <button wire:click="downloadMastersXml" type="button"
                            class="px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 font-bold text-[11px] rounded-lg shadow-2xs transition cursor-pointer shrink-0">
                            Download Masters XML (Ledgers)
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-end">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">From Date</label>
                            <input type="date" wire:model="exportFromDate"
                                class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">To Date</label>
                            <input type="date" wire:model="exportToDate"
                                class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="downloadDateRangeVouchersXml" type="button"
                                class="w-full px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition cursor-pointer">
                                Download Range XML
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="markExportedSynced" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span>Mark exported pending vouchers as Synced / Ack</span>
                        </label>
                        <button wire:click="downloadPendingVouchersXml" type="button"
                            class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-2xs transition cursor-pointer">
                            Download Pending Vouchers XML ({{ $outboxStats['pending'] ?? 0 }})
                        </button>
                    </div>
                </div>

                <!-- Authenticated Office PC Pull-Bridge (Hostinger -> LAN Tally) -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/90 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-xs text-slate-900">Office PC Authenticated Pull-Sync API</div>
                            <p class="text-[11px] text-slate-600">Allows the Windows PC running TallyPrime to securely pull pending XML over HTTPS and push to local <code class="font-mono">http://127.0.0.1:{{ $tallyPort }}</code>.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="generateBridgeToken" type="button"
                                class="px-3 py-1.5 bg-white hover:bg-slate-100 border border-slate-300 text-slate-800 font-bold text-[11px] rounded-lg transition cursor-pointer">
                                {{ $hasBridgeToken ? 'Rotate Bridge Token' : 'Generate Bridge Token' }}
                            </button>
                            @if ($hasBridgeToken)
                                <button wire:click="revokeBridgeToken" type="button"
                                    class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 border border-rose-300 text-rose-800 font-bold text-[11px] rounded-lg transition cursor-pointer">
                                    Revoke
                                </button>
                            @endif
                        </div>
                    </div>

                    @if ($generatedBridgeToken)
                        <div class="p-3 bg-slate-900 text-emerald-300 rounded-xl text-[11px] font-mono space-y-2 overflow-x-auto">
                            <div class="text-amber-300 font-sans font-bold text-xs">One-Time Bridge Token (store in your Windows sync task env):</div>
                            <div class="select-all break-all">{{ $generatedBridgeToken }}</div>
                            <div class="text-slate-300 pt-1 border-t border-slate-700 text-[10px]">
                                Pull endpoint: <span class="text-white">GET {{ url('/api/integrations/tally/pending') }}</span> &bull;
                                Ack endpoint: <span class="text-white">POST {{ url('/api/integrations/tally/ack') }}</span> (Header: <span class="text-white">Authorization: Bearer &lt;token&gt;</span>)
                            </div>
                        </div>
                    @elseif ($hasBridgeToken)
                        <div class="text-[11px] text-emerald-800 font-medium">
                            Bridge token is active (SHA-256 hashed on server). Rotate token only if you need to re-provision the Tally PC script.
                        </div>
                    @endif
                </div>

                <!-- Tally Configuration Card -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/90 space-y-4 pt-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2.5">
                        <div class="flex items-center gap-1.5 font-bold text-xs text-slate-900">
                            <span>⚙️</span>
                            <span>Tally Prime LAN Network Settings</span>
                        </div>
                        <span class="text-[10px] font-mono font-bold text-slate-500">
                            Target: {{ $tallyStatus['endpoint'] ?? 'http://127.0.0.1:9000' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Tally Computer LAN IP / Hostname
                            </label>
                            <input type="text" wire:model="tallyHost" placeholder="e.g. 192.168.1.50 or 127.0.0.1"
                                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-mono font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <p class="text-[10px] text-slate-500 mt-1">IP address of the PC running Tally on the club network (or tunnel).</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Port
                            </label>
                            <input type="number" wire:model="tallyPort" placeholder="9000"
                                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-mono font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <p class="text-[10px] text-slate-500 mt-1">Default: 9000</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Target Company Name
                            </label>
                            <input type="text" wire:model="tallyCompanyName" placeholder="e.g. Malabar Club"
                                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Integration Driver Mode
                            </label>
                            <select wire:model="tallyDriver"
                                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                <option value="simulator">Development Simulator (Safe Mock)</option>
                                <option value="live">Live Tally Prime HTTP (Connect to LAN)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <label class="inline-flex items-start gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="tallyIncludeCostCentres" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span>
                                <strong class="block text-slate-900">Include Cost Centres in XML</strong>
                                <span class="text-[10px] text-slate-500">Attaches department cost centres on Revenue/Expense ledgers (requires Tally F11 Cost Centres enabled).</span>
                            </span>
                        </label>
                        <label class="inline-flex items-start gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="tallyAutoSync" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span>
                                <strong class="block text-slate-900">Auto-Push Every 15 Minutes (Scheduler)</strong>
                                <span class="text-[10px] text-slate-500">Enable only when server can reach Tally directly over LAN/VPN. Leave off for Hostinger + XML/Pull mode.</span>
                            </span>
                        </label>
                    </div>

                    <div class="flex items-center gap-2.5 pt-1">
                        <button wire:click="saveTallySettings" type="button"
                            class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5">
                            <span>💾</span>
                            <span>Save Configuration</span>
                        </button>
                        <button wire:click="testTallyConnection" type="button"
                            class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 font-bold text-xs rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5">
                            <span>🔌</span>
                            <span>Test Tally Connection</span>
                        </button>
                    </div>

                    <!-- Connection Test Feedback -->
                    @if ($tallyTestResult !== null)
                        <div class="p-3.5 rounded-xl border {{ ($tallyTestResult['success'] ?? false) ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-rose-50 border-rose-300 text-rose-900' }} text-xs space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>{{ ($tallyTestResult['success'] ?? false) ? '✅' : '⚠️' }}</span>
                                <span>{{ ($tallyTestResult['success'] ?? false) ? 'Tally Connection Succeeded' : 'Tally Connection Failed' }}</span>
                            </div>
                            <p class="text-[11px] leading-relaxed">
                                {{ $tallyTestResult['message'] }}
                            </p>
                            @if (!($tallyTestResult['success'] ?? false))
                                <div class="text-[10px] text-rose-800 pt-1 border-t border-rose-200/80">
                                    💡 <strong>Setup Checklist in Tally Prime:</strong> Press <code>F1 (Help)</code> &rarr; <code>Settings</code> &rarr; <code>Connectivity</code> &rarr; Set <code>TallyPrime acts as: Both/Server</code> and <code>Port: {{ $tallyPort }}</code>.
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- 2. Access Control Hardware Simulator -->
            <div class="p-6 bg-white border border-slate-200/90 rounded-2xl shadow-xs space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-700 font-bold">
                            🚪
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 tracking-tight">Turnstiles &amp; Biometric Gate Access</h3>
                            <span class="text-[11px] text-slate-500">RFID Smartcard &amp; Guest Pass Reader</span>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-xs font-mono font-bold rounded-full bg-blue-100 text-blue-900 border border-blue-300">
                        {{ $accessStatus['label'] ?? 'SIMULATOR' }}
                    </span>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Gate reader simulator verifies active club membership, card standing, and paid guest pass authorization in real time. Fails closed automatically if deployed in a production environment.
                </p>

                <!-- Interactive Access Simulator Form -->
                <div class="space-y-3.5 pt-1">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Card RFID Token or Guest Pass Code
                        </label>
                        <input type="text" wire:model="simCardNumber" placeholder="e.g. CARD-VENU-001 or GP-..."
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Target Door / Turnstile Location
                        </label>
                        <select wire:model="simFacilityCode"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-colors">
                            <option value="bar">Bar (Age Verification &amp; Sponsoring Check)</option>
                            <option value="card_room">Card Room (Guest Pass Required)</option>
                            <option value="gym">Gym / Health Club (Active Sports Plan)</option>
                            <option value="badminton">Badminton Court</option>
                            <option value="swimming_pool">Swimming Pool</option>
                        </select>
                    </div>
                    <button wire:click="testAccessEntry" type="button"
                        class="w-full py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs active:scale-98 transition-all cursor-pointer flex items-center justify-center gap-2">
                        <svg width="16" height="16" class="w-4 h-4 text-amber-400 shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        <span>Simulate Physical Reader Swipe / Scan</span>
                    </button>
                </div>

                <!-- Simulation Response Display -->
                @if($testResult !== null)
                    <div class="p-4 rounded-xl border {{ ($testResult['granted'] ?? false) ? 'bg-emerald-50 border-emerald-300' : 'bg-rose-50 border-rose-300' }}">
                        <div class="flex items-start gap-3">
                            <div class="text-xl">
                                {{ ($testResult['granted'] ?? false) ? '✅' : '🚫' }}
                            </div>
                            <div class="space-y-1">
                                <div class="font-bold text-xs {{ ($testResult['granted'] ?? false) ? 'text-emerald-900' : 'text-rose-900' }}">
                                    Gate Response: {{ ($testResult['granted'] ?? false) ? 'ACCESS GRANTED (Barrier Unlocked)' : 'ACCESS DENIED (Barrier Locked)' }}
                                </div>
                                <div class="text-[11px] {{ ($testResult['granted'] ?? false) ? 'text-emerald-800' : 'text-rose-800' }}">
                                    Reason: {{ $testResult['reason'] ?? 'Verification completed.' }}
                                </div>
                                @if(isset($testResult['person_name']))
                                    <div class="text-[10px] text-slate-600 font-medium">
                                        Identified Person: <strong class="text-slate-900">{{ $testResult['person_name'] }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
