<?php

namespace App\Domain\Integrations;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Tariff\Models\Department;
use Exception;
use Illuminate\Support\Facades\DB;

class TallySyncService
{
    public const PRIMARY_EVENT_TYPE = 'finance.journal.posted';

    public const SYNC_EVENT_TYPES = [
        'finance.journal.posted',
        'tally.voucher.sync',
    ];

    public function __construct(
        protected ?TallyXmlSerializer $serializer = null
    ) {
        $this->serializer = $this->serializer ?? app(TallyXmlSerializer::class);
    }

    /**
     * Enqueue a JournalHeader for Tally sync in the retryable outbox without duplicating
     * an existing outbox entry created by JournalService::post().
     */
    public function queueVoucherSync(JournalHeader $header): OutboxMessage
    {
        $existing = OutboxMessage::where('aggregate_type', 'JournalHeader')
            ->where('aggregate_id', $header->id)
            ->whereIn('event_type', self::SYNC_EVENT_TYPES)
            ->first();

        if ($existing) {
            return $existing;
        }

        return OutboxMessage::create([
            'aggregate_type' => 'JournalHeader',
            'aggregate_id' => $header->id,
            'event_type' => self::PRIMARY_EVENT_TYPE,
            'payload' => [
                'journal_number' => $header->journal_number,
                'business_date' => $header->business_date->toDateString(),
                'description' => $header->description,
            ],
            'status' => 'pending',
            'attempts' => 0,
            'idempotency_key' => 'tally_' . $header->journal_number,
        ]);
    }

    /**
     * Process pending/retryable Tally journal outbox messages via the active connector.
     * Guarantees:
     * - Tally outage does not crash or rollback club sale; message remains retryable (Scenario X01).
     * - Re-sync with lost acknowledgement detects existing reference and prevents duplication (Scenario X02).
     * - Validation/mapping rejections are logged as failures, not falsely marked accepted (Scenario X03).
     */
    public function processPendingOutbox(AccountingConnectorInterface $connector, int $limit = 200): array
    {
        $pending = OutboxMessage::whereIn('event_type', self::SYNC_EVENT_TYPES)
            ->whereIn('status', ['pending', 'retryable'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($pending as $message) {
            $message->attempts += 1;

            try {
                if (! JournalHeader::where('id', $message->aggregate_id)->exists()) {
                    $message->status = 'failed';
                    $message->last_error = "JournalHeader #{$message->aggregate_id} no longer exists.";
                    $failed++;
                    $errors[] = $message->last_error;
                    $message->save();
                    continue;
                }

                $response = $connector->sendVouchers([$message->aggregate_id]);

                if (($response['status'] ?? '') === 'acknowledged') {
                    $message->status = 'acknowledged';
                    $message->delivered_at = $message->delivered_at ?? now();
                    $message->acknowledged_at = now();
                    $message->last_error = null;
                    $processed++;
                } elseif (($response['status'] ?? '') === 'rejected') {
                    // Scenario X03: Target company rejected ledger/cost-centre mapping
                    $message->status = 'failed';
                    $message->last_error = $response['error'] ?? 'Mapping rejected by Tally.';
                    $failed++;
                    $errors[] = $message->last_error;
                } else {
                    $message->status = 'retryable';
                    $message->last_error = $response['error'] ?? 'Unexpected response from Tally connector.';
                    $failed++;
                    $errors[] = $message->last_error;
                }
            } catch (Exception $e) {
                // Scenario X01: Connector / Network unavailable
                $message->status = 'pending';
                $message->last_error = 'Tally service unavailable: ' . $e->getMessage();
                $failed++;
                $errors[] = $message->last_error;
            }

            $message->save();
        }

        return [
            'total_pending' => $pending->count(),
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Tally-specific outbox counts (excludes unrelated WhatsApp/SMS or gateway webhook events).
     *
     * @return array{pending: int, acknowledged: int, failed: int}
     */
    public function getOutboxStats(): array
    {
        return [
            'pending' => OutboxMessage::whereIn('event_type', self::SYNC_EVENT_TYPES)
                ->whereIn('status', ['pending', 'retryable'])
                ->count(),
            'acknowledged' => OutboxMessage::whereIn('event_type', self::SYNC_EVENT_TYPES)
                ->where('status', 'acknowledged')
                ->count(),
            'failed' => OutboxMessage::whereIn('event_type', self::SYNC_EVENT_TYPES)
                ->where('status', 'failed')
                ->count(),
        ];
    }

    /**
     * Re-queue previously failed Tally vouchers (e.g., after creating missing ledgers in Tally).
     */
    public function requeueFailedOutbox(): int
    {
        return OutboxMessage::whereIn('event_type', self::SYNC_EVENT_TYPES)
            ->where('status', 'failed')
            ->update([
                'status' => 'retryable',
                'last_error' => null,
            ]);
    }

    /**
     * Build a downloadable TallyPrime Vouchers XML file for manual import on Hostinger/remote setups.
     *
     * @return array{xml: string, count: int, journal_ids: array<int>, filename: string}
     */
    public function buildVouchersXmlExport(
        ?string $fromDate = null,
        ?string $toDate = null,
        bool $pendingOnly = false,
        ?bool $includeCostCentres = null,
    ): array {
        $query = JournalHeader::with(['lines.account'])
            ->orderBy('business_date')
            ->orderBy('id');

        if ($pendingOnly) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('outbox_messages')
                    ->whereColumn('outbox_messages.aggregate_id', 'journal_headers.id')
                    ->where('outbox_messages.aggregate_type', 'JournalHeader')
                    ->whereIn('outbox_messages.event_type', self::SYNC_EVENT_TYPES)
                    ->whereIn('outbox_messages.status', ['pending', 'retryable']);
            });
        }

        if (filled($fromDate)) {
            $query->whereDate('business_date', '>=', $fromDate);
        }

        if (filled($toDate)) {
            $query->whereDate('business_date', '<=', $toDate);
        }

        $headers = $query->get();
        $options = $includeCostCentres !== null ? ['include_cost_centres' => $includeCostCentres] : [];
        $xml = $this->serializer->serializeVouchers($headers, $options);

        $suffix = $pendingOnly ? 'pending' : trim(($fromDate ?: 'all') . '_to_' . ($toDate ?: 'latest'), '_');

        return [
            'xml' => $xml,
            'count' => $headers->count(),
            'journal_ids' => $headers->pluck('id')->all(),
            'filename' => 'malabar_club_tally_vouchers_' . $suffix . '_' . now()->format('Ymd_His') . '.xml',
        ];
    }

    /**
     * Mark pending/retryable outbox rows for manually imported vouchers as acknowledged.
     *
     * @param array<int> $journalIds
     */
    public function markExportedAsAcknowledged(array $journalIds): int
    {
        if (empty($journalIds)) {
            return 0;
        }

        return OutboxMessage::where('aggregate_type', 'JournalHeader')
            ->whereIn('aggregate_id', $journalIds)
            ->whereIn('event_type', self::SYNC_EVENT_TYPES)
            ->whereIn('status', ['pending', 'retryable', 'failed'])
            ->update([
                'status' => 'acknowledged',
                'delivered_at' => now(),
                'acknowledged_at' => now(),
                'last_error' => null,
            ]);
    }

    /**
     * Build a downloadable TallyPrime All Masters XML file (Chart of Accounts Ledgers + Cost Centres).
     *
     * @return array{xml: string, account_count: int, filename: string}
     */
    public function buildMastersXmlExport(?bool $includeCostCentres = null): array
    {
        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        $departments = Department::orderBy('code')->get();
        $options = $includeCostCentres !== null ? ['include_cost_centres' => $includeCostCentres] : [];

        $xml = $this->serializer->serializeMasters($accounts, $departments, $options);

        return [
            'xml' => $xml,
            'account_count' => $accounts->count(),
            'filename' => 'malabar_club_tally_masters_' . now()->format('Ymd') . '.xml',
        ];
    }

    /**
     * Generate and hash an API token for an office Tally PC pull-bridge agent.
     * Only the SHA-256 digest is persisted; the plaintext token is returned once.
     */
    public function generateBridgeToken(): string
    {
        $plain = 'mc_tally_' . bin2hex(random_bytes(24));

        SystemSetting::set('tally_bridge_token_hash', hash('sha256', $plain), 'tally');
        SystemSetting::set('tally_bridge_token_created_at', now()->toIso8601String(), 'tally');

        return $plain;
    }

    public function hasBridgeToken(): bool
    {
        $hash = SystemSetting::get('tally_bridge_token_hash');

        return is_string($hash) && trim($hash) !== '';
    }

    public function revokeBridgeToken(): void
    {
        SystemSetting::set('tally_bridge_token_hash', '', 'tally');
    }

    public function verifyBridgeToken(?string $plainToken): bool
    {
        if (! is_string($plainToken) || trim($plainToken) === '') {
            return false;
        }

        $storedHash = SystemSetting::get('tally_bridge_token_hash');
        if (! is_string($storedHash) || trim($storedHash) === '') {
            return false;
        }

        return hash_equals($storedHash, hash('sha256', trim($plainToken)));
    }

    /**
     * Pull the next pending batch as Tally XML for the authenticated local Tally PC bridge agent.
     *
     * @return array{has_pending: bool, outbox_ids: array<int>, voucher_count: int, journal_numbers: array<string>, company_name: string, xml: string}
     */
    public function pullPendingBatch(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $pending = OutboxMessage::whereIn('event_type', self::SYNC_EVENT_TYPES)
            ->whereIn('status', ['pending', 'retryable'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $headersById = JournalHeader::with(['lines.account'])
            ->whereIn('id', $pending->pluck('aggregate_id')->all())
            ->get()
            ->keyBy('id');

        $validOutboxIds = [];
        $validHeaders = collect();

        foreach ($pending as $message) {
            $header = $headersById->get($message->aggregate_id);
            if (! $header) {
                $message->update([
                    'status' => 'failed',
                    'attempts' => $message->attempts + 1,
                    'last_error' => "JournalHeader #{$message->aggregate_id} no longer exists.",
                ]);
                continue;
            }

            $validOutboxIds[] = $message->id;
            $validHeaders->push($header);
        }

        if ($validHeaders->isEmpty()) {
            return [
                'has_pending' => false,
                'outbox_ids' => [],
                'voucher_count' => 0,
                'journal_numbers' => [],
                'company_name' => $this->serializer->resolveTargetCompany(),
                'xml' => '',
            ];
        }

        return [
            'has_pending' => true,
            'outbox_ids' => $validOutboxIds,
            'voucher_count' => $validHeaders->count(),
            'journal_numbers' => $validHeaders->pluck('journal_number')->all(),
            'company_name' => $this->serializer->resolveTargetCompany(),
            'xml' => $this->serializer->serializeVouchers($validHeaders),
        ];
    }

    /**
     * Record the acknowledgement or error outcome reported by a pull-bridge agent.
     *
     * @param array<int> $outboxIds
     * @return array{updated: int, status: string}
     */
    public function acknowledgePullBatch(
        array $outboxIds,
        string $resultStatus,
        ?string $errorMessage = null,
    ): array {
        if (empty($outboxIds)) {
            return ['updated' => 0, 'status' => $resultStatus];
        }

        $messages = OutboxMessage::whereIn('id', $outboxIds)
            ->whereIn('event_type', self::SYNC_EVENT_TYPES)
            ->get();

        $updated = 0;

        foreach ($messages as $message) {
            $message->attempts += 1;

            if ($resultStatus === 'acknowledged') {
                $message->status = 'acknowledged';
                $message->delivered_at = $message->delivered_at ?? now();
                $message->acknowledged_at = now();
                $message->last_error = null;
            } elseif ($resultStatus === 'rejected') {
                $message->status = 'failed';
                $message->last_error = $errorMessage ?: 'TallyPrime rejected voucher batch.';
            } else {
                $message->status = 'retryable';
                $message->last_error = $errorMessage ?: 'Local TallyPrime connection error.';
            }

            $message->save();
            $updated++;
        }

        return ['updated' => $updated, 'status' => $resultStatus];
    }
}
