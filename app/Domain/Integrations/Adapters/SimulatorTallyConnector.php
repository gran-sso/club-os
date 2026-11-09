<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallyXmlSerializer;
use RuntimeException;

class SimulatorTallyConnector implements AccountingConnectorInterface
{
    public const STATUS_LABEL = 'SIMULATOR - TEST ONLY';

    protected bool $isAvailable = true;
    protected ?string $mappingRejectionError = null;
    protected static array $receivedVoucherNumbers = [];

    public function __construct(
        protected TallyXmlSerializer $serializer
    ) {}

    public function setAvailable(bool $available): self
    {
        $this->isAvailable = $available;
        return $this;
    }

    public function setMappingRejectionError(?string $error): self
    {
        $this->mappingRejectionError = $error;
        return $this;
    }

    public function exportPreview(array $journalHeaderIds): array
    {
        $headers = JournalHeader::with(['lines.account'])->whereIn('id', $journalHeaderIds)->get();
        $xml = $this->serializer->serializeVouchers($headers);

        return [
            'status' => 'preview',
            'is_simulator' => true,
            'voucher_count' => $headers->count(),
            'xml_preview' => $xml,
        ];
    }

    public function sendVouchers(array $journalHeaderIds): array
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Development Tally simulator cannot be executed in production environment.');
        }

        // Scenario X01: Tally service is unavailable
        if (!$this->isAvailable) {
            throw new RuntimeException('Connection refused: Tally Prime HTTP interface at 127.0.0.1:9000 is not reachable.');
        }

        // Scenario X03: Test company rejected mapping (e.g., unknown cost centre / ledger)
        if ($this->mappingRejectionError !== null) {
            return [
                'status' => 'rejected',
                'is_simulator' => true,
                'error' => $this->mappingRejectionError,
                'failed_ids' => $journalHeaderIds,
            ];
        }

        $headers = JournalHeader::with(['lines.account'])->whereIn('id', $journalHeaderIds)->get();
        $xml = $this->serializer->serializeVouchers($headers);

        // Record or check deduplication (Scenario X02)
        $newDeliveries = 0;
        foreach ($headers as $header) {
            $isDuplicate = in_array($header->journal_number, self::$receivedVoucherNumbers, true);

            if (!$isDuplicate) {
                self::$receivedVoucherNumbers[] = $header->journal_number;
                $newDeliveries++;
            }

            OutboxMessage::updateOrCreate(
                [
                    'aggregate_type' => 'JournalHeader',
                    'aggregate_id' => $header->id,
                    'event_type' => 'tally.voucher.sync',
                ],
                [
                    'payload' => [
                        'journal_number' => $header->journal_number,
                        'total_debits_paise' => $header->totalDebits(),
                        'xml_snippet' => substr($xml, 0, 500),
                    ],
                    'status' => 'acknowledged',
                    'idempotency_key' => 'tally_sim_' . $header->journal_number,
                    'delivered_at' => now(),
                    'acknowledged_at' => now(),
                ]
            );
        }

        return [
            'status' => 'acknowledged',
            'is_simulator' => true,
            'vouchers_delivered' => $headers->count(),
            'new_deliveries' => $newDeliveries,
            'external_ack_id' => 'TALLY-SIM-' . uniqid(),
            'note' => 'Delivered to local development simulation fixture.',
        ];
    }

    public function getStatus(): array
    {
        $host = \App\Domain\Shared\Models\SystemSetting::get('tally_host', '127.0.0.1');
        $port = \App\Domain\Shared\Models\SystemSetting::get('tally_port', '9000');
        $cleanHost = preg_replace('#^https?://#', '', trim((string) $host));

        return [
            'driver' => 'simulator',
            'label' => self::STATUS_LABEL,
            'endpoint' => "http://{$cleanHost}:{$port}",
            'configured' => true,
            'is_production_ready' => false,
            'connection' => 'simulated_local_fixture',
            'pending_outbox_count' => OutboxMessage::where('status', 'pending')->count(),
        ];
    }
}
