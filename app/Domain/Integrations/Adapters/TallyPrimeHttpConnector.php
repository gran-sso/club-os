<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallySyncService;
use App\Domain\Integrations\TallyXmlSerializer;
use App\Domain\Shared\Models\SystemSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TallyPrimeHttpConnector implements AccountingConnectorInterface
{
    public const STATUS_LABEL = 'Tally Prime XML over HTTP (Live Adapter)';

    public function __construct(
        protected TallyXmlSerializer $serializer,
        protected string $endpointUrl = 'http://127.0.0.1:9000',
        protected int $timeoutSeconds = 5
    ) {
        $this->endpointUrl = config('services.tally.url', $this->endpointUrl);
    }

    public function getEndpointUrl(): string
    {
        $host = SystemSetting::get('tally_host');
        $port = SystemSetting::get('tally_port', '9000');
        if (! empty($host)) {
            $cleanHost = preg_replace('#^https?://#', '', trim((string) $host));
            $cleanHost = explode(':', $cleanHost)[0];

            return "http://{$cleanHost}:{$port}";
        }

        $dbUrl = SystemSetting::get('tally_url');
        if (! empty($dbUrl)) {
            return rtrim((string) $dbUrl, '/');
        }

        return rtrim((string) config('services.tally.url', $this->endpointUrl), '/');
    }

    public static function testConnection(
        string $host,
        int|string $port = 9000,
        int $timeout = 3,
        ?string $companyName = null
    ): array {
        $cleanHost = preg_replace('#^https?://#', '', trim($host));
        $cleanHost = explode(':', $cleanHost)[0];
        $port = (int) $port;

        $targetUrl = "http://{$cleanHost}:{$port}";

        $probeXml = <<<'XML'
        <ENVELOPE>
            <HEADER>
                <TALLYREQUEST>Export Data</TALLYREQUEST>
            </HEADER>
            <BODY>
                <EXPORTDATA>
                    <REQUESTDESC>
                        <STATICVARIABLES>
                            <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
                        </STATICVARIABLES>
                        <REPORTNAME>List of Companies</REPORTNAME>
                    </REQUESTDESC>
                </EXPORTDATA>
            </BODY>
        </ENVELOPE>
        XML;

        try {
            $response = Http::timeout($timeout)
                ->withBody($probeXml, 'text/xml;charset=utf-8')
                ->post($targetUrl);

            $body = $response->body();
            if ($response->successful() || str_contains($body, '<RESPONSE>') || str_contains($body, '<ENVELOPE>')) {
                $companyNote = '';
                $companyMatched = null;
                if (filled($companyName) && preg_match_all('/<COMPANY[^>]*NAME="([^"]+)"|<COMPANYNAME[^>]*>(.*?)<\/COMPANYNAME>/si', $body, $matches)) {
                    $loaded = array_values(array_filter(array_map(
                        fn ($a, $b) => trim(html_entity_decode($a !== '' ? $a : $b, ENT_XML1 | ENT_QUOTES, 'UTF-8')),
                        $matches[1],
                        $matches[2]
                    )));
                    if (! empty($loaded)) {
                        $companyMatched = in_array(trim($companyName), $loaded, true);
                        $companyNote = $companyMatched
                            ? " Target company '{$companyName}' is currently loaded."
                            : " Note: Target company '{$companyName}' was not listed among open companies (" . implode(', ', array_slice($loaded, 0, 5)) . ').';
                    }
                }

                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'company_matched' => $companyMatched,
                    'message' => "Successfully connected to Tally Prime at {$targetUrl}.{$companyNote}",
                    'endpoint' => $targetUrl,
                ];
            }

            return [
                'success' => false,
                'status_code' => $response->status(),
                'message' => "Reached {$targetUrl}, but Tally Prime returned unexpected HTTP status {$response->status()}.",
                'endpoint' => $targetUrl,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => "Connection failed to {$targetUrl}. Ensure Tally Prime is running and Server mode is enabled on Port {$port}. Error: " . $e->getMessage(),
                'endpoint' => $targetUrl,
            ];
        }
    }

    public function exportPreview(array $journalHeaderIds): array
    {
        $headers = JournalHeader::with(['lines.account'])->whereIn('id', $journalHeaderIds)->get();
        $xml = $this->serializer->serializeVouchers($headers);
        $endpoint = $this->getEndpointUrl();

        return [
            'status' => 'preview',
            'is_simulator' => false,
            'endpoint' => $endpoint,
            'voucher_count' => $headers->count(),
            'xml_preview' => $xml,
        ];
    }

    public function sendVouchers(array $journalHeaderIds): array
    {
        $headers = JournalHeader::with(['lines.account'])->whereIn('id', $journalHeaderIds)->get();
        if ($headers->isEmpty()) {
            return [
                'status' => 'empty',
                'vouchers_delivered' => 0,
            ];
        }

        $xml = $this->serializer->serializeVouchers($headers);
        $endpoint = $this->getEndpointUrl();

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withBody($xml, 'text/xml;charset=utf-8')
                ->post($endpoint);
        } catch (Exception $e) {
            // Scenario X01: Tally service is offline / connection refused
            throw new RuntimeException("Tally Prime HTTP connection failed at {$endpoint}: " . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Tally Prime returned HTTP error status {$response->status()}: " . $response->body());
        }

        $body = $response->body();
        $errorMessages = self::extractTallyErrors($body);

        $createdCount = 0;
        if (preg_match('/<CREATED>(\d+)<\/CREATED>/i', $body, $cMatches)) {
            $createdCount = (int) $cMatches[1];
        }

        $alteredCount = 0;
        if (preg_match('/<ALTERED>(\d+)<\/ALTERED>/i', $body, $aMatches)) {
            $alteredCount = (int) $aMatches[1];
        }

        $errorsCount = 0;
        if (preg_match('/<ERRORS>(\d+)<\/ERRORS>/i', $body, $eMatches)) {
            $errorsCount = (int) $eMatches[1];
        }

        // Scenario X03: Target company rejected mapping (e.g., unknown ledger or cost centre)
        if (! empty($errorMessages) || ($errorsCount > 0 && ($createdCount + $alteredCount) === 0)) {
            return [
                'status' => 'rejected',
                'is_simulator' => false,
                'error' => ! empty($errorMessages)
                    ? implode(' | ', $errorMessages)
                    : 'Tally Prime import rejected with ledger/tax mapping errors.',
                'failed_ids' => $journalHeaderIds,
                'raw_response' => substr($body, 0, 500),
            ];
        }

        // Scenario X02: Deduplication and delivery confirmation
        foreach ($headers as $header) {
            $msg = OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $header->id)
                ->whereIn('event_type', TallySyncService::SYNC_EVENT_TYPES)
                ->first();

            if ($msg) {
                $msg->update([
                    'status' => 'acknowledged',
                    'delivered_at' => $msg->delivered_at ?? now(),
                    'acknowledged_at' => now(),
                    'last_error' => null,
                ]);
            } else {
                OutboxMessage::create([
                    'aggregate_type' => 'JournalHeader',
                    'aggregate_id' => $header->id,
                    'event_type' => TallySyncService::PRIMARY_EVENT_TYPE,
                    'payload' => [
                        'journal_number' => $header->journal_number,
                        'total_debits_paise' => $header->totalDebits(),
                        'endpoint' => $endpoint,
                    ],
                    'status' => 'acknowledged',
                    'idempotency_key' => 'tally_http_' . $header->journal_number,
                    'delivered_at' => now(),
                    'acknowledged_at' => now(),
                ]);
            }
        }

        return [
            'status' => 'acknowledged',
            'is_simulator' => false,
            'vouchers_delivered' => $headers->count(),
            'tally_created_count' => $createdCount,
            'tally_altered_count' => $alteredCount,
            'endpoint' => $endpoint,
        ];
    }

    /**
     * Extract all <LINEERROR> / <EXCEPTION> / <ERROR> messages returned in Tally XML responses.
     *
     * @return array<string>
     */
    public static function extractTallyErrors(string $xmlBody): array
    {
        $collected = [];

        if (preg_match_all('/<(?:LINEERROR|EXCEPTION)>(.*?)<\/(?:LINEERROR|EXCEPTION)>/si', $xmlBody, $matches)) {
            foreach ($matches[1] as $raw) {
                $clean = trim(strip_tags(html_entity_decode($raw, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
                if ($clean !== '') {
                    $collected[] = $clean;
                }
            }
        }

        return array_values(array_unique($collected));
    }

    public function getStatus(): array
    {
        return [
            'driver' => 'tally_http',
            'label' => self::STATUS_LABEL,
            'endpoint' => $this->getEndpointUrl(),
            'configured' => true,
            'is_production_ready' => true,
            'pending_outbox_count' => OutboxMessage::whereIn('event_type', TallySyncService::SYNC_EVENT_TYPES)
                ->whereIn('status', ['pending', 'retryable'])
                ->count(),
        ];
    }
}
