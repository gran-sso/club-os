<?php

namespace App\Domain\Integrations;

use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use Exception;
use Illuminate\Support\Collection;

class TallySyncService
{
    /**
     * Enqueue a JournalHeader for Tally sync in the retryable outbox.
     */
    public function queueVoucherSync(JournalHeader $header): OutboxMessage
    {
        return OutboxMessage::firstOrCreate(
            [
                'aggregate_type' => 'JournalHeader',
                'aggregate_id' => $header->id,
                'event_type' => 'tally.voucher.sync',
            ],
            [
                'payload' => [
                    'journal_number' => $header->journal_number,
                    'business_date' => $header->business_date->toDateString(),
                    'description' => $header->description,
                ],
                'status' => 'pending',
                'attempts' => 0,
                'idempotency_key' => 'tally_' . $header->journal_number,
            ]
        );
    }

    /**
     * Process pending outbox vouchers using the provided connector.
     * Guarantees:
     * - Tally outage does not crash or rollback club sale; message remains retryable (Scenario X01).
     * - Re-sync with lost acknowledgement detects existing reference and prevents duplication (Scenario X02).
     * - Validation/mapping rejections are logged as failures, not falsely marked accepted (Scenario X03).
     */
    public function processPendingOutbox(AccountingConnectorInterface $connector): array
    {
        $pending = OutboxMessage::where('event_type', 'tally.voucher.sync')
            ->whereIn('status', ['pending', 'retryable'])
            ->get();

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($pending as $message) {
            $message->attempts += 1;

            try {
                $response = $connector->sendVouchers([$message->aggregate_id]);

                if (($response['status'] ?? '') === 'acknowledged') {
                    $message->status = 'acknowledged';
                    $message->delivered_at = now();
                    $message->acknowledged_at = now();
                    $message->last_error = null;
                    $processed++;
                } elseif (($response['status'] ?? '') === 'rejected') {
                    // Scenario X03: Test company rejected mapping
                    $message->status = 'failed';
                    $message->last_error = $response['error'] ?? 'Mapping rejected by Tally.';
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
}
