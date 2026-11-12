<?php

namespace App\Filament\Pages;

use App\Domain\Billing\Enums\TenderType;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Integrations\Adapters\TallyPrimeHttpConnector;
use App\Domain\Integrations\Contracts\AccessControlProviderInterface;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallySyncService;
use App\Domain\Shared\Models\SystemSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IntegrationStatusPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Audit Ledger';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Integrations & Hardware';

    protected static ?string $title = 'Integrations & External Hardware Status';

    protected static ?string $slug = 'integration-status';

    protected string $view = 'filament.pages.integration-status';

    public array $tallyStatus = [];
    public array $accessStatus = [];
    public array $printerStatus = [];
    public array $outboxStats = [];
    public array $recentTallyErrors = [];

    // Tally Configuration Form State
    public string $tallyHost = '127.0.0.1';
    public string $tallyPort = '9000';
    public string $tallyCompanyName = 'Malabar Club';
    public string $tallyDriver = 'simulator';
    public bool $tallyIncludeCostCentres = false;
    public bool $tallyAutoSync = false;
    public ?array $tallyTestResult = null;
    public ?array $lastDispatchReport = null;

    // Cloud / Manual XML Export State
    public string $exportFromDate = '';
    public string $exportToDate = '';
    public bool $markExportedSynced = false;

    // Office Tally PC Pull-Bridge Token State
    public bool $hasBridgeToken = false;
    public ?string $generatedBridgeToken = null;

    // Hardware Simulation State
    public string $simCardNumber = '';
    public string $simFacilityCode = 'bar';
    public ?array $testResult = null;
    public ?string $printerPreview = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['integrations.view', 'integrations.manage']));
    }

    public function mount(): void
    {
        $this->tallyHost = (string) SystemSetting::get('tally_host', env('TALLY_HOST', '127.0.0.1'));
        $this->tallyPort = (string) SystemSetting::get('tally_port', env('TALLY_PORT', '9000'));
        $this->tallyCompanyName = (string) (
            SystemSetting::get('tally_company_name')
            ?: SystemSetting::get('tally_company', env('TALLY_COMPANY_NAME', 'Malabar Club'))
        );
        $this->tallyDriver = (string) SystemSetting::get('tally_driver', app()->environment('production') ? 'live' : 'simulator');
        $this->tallyIncludeCostCentres = SystemSetting::getBool('tally_include_cost_centres', false);
        $this->tallyAutoSync = SystemSetting::getBool('tally_auto_sync', false);

        $this->exportFromDate = now()->startOfMonth()->toDateString();
        $this->exportToDate = now()->toDateString();

        $this->refreshStatuses();
    }

    public function refreshStatuses(): void
    {
        $tallyConnector = app(AccountingConnectorInterface::class);
        $this->tallyStatus = $tallyConnector->getStatus();

        $accessProvider = app(AccessControlProviderInterface::class);
        $this->accessStatus = $accessProvider->getStatus();

        $printer = app(ReceiptPrinterInterface::class);
        $this->printerStatus = $printer->getStatus();

        $syncService = app(TallySyncService::class);
        $this->outboxStats = $syncService->getOutboxStats();
        $this->hasBridgeToken = $syncService->hasBridgeToken();

        $this->recentTallyErrors = OutboxMessage::whereIn('event_type', TallySyncService::SYNC_EVENT_TYPES)
            ->where('status', 'failed')
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'payload', 'last_error', 'updated_at'])
            ->map(fn (OutboxMessage $msg) => [
                'id' => $msg->id,
                'journal_number' => $msg->payload['journal_number'] ?? ('#' . $msg->id),
                'error' => $msg->last_error,
                'updated_at' => $msg->updated_at?->format('d M H:i'),
            ])
            ->all();
    }

    public function saveTallySettings(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
        $this->validate([
            'tallyHost' => 'required|string|max:150',
            'tallyPort' => 'required|numeric|min:1|max:65535',
            'tallyCompanyName' => 'required|string|max:150',
            'tallyDriver' => 'required|in:simulator,live',
        ]);

        $cleanHost = preg_replace('#^https?://#', '', trim($this->tallyHost));
        $cleanHost = explode(':', $cleanHost)[0];
        $port = (int) $this->tallyPort;
        $companyName = trim($this->tallyCompanyName);

        SystemSetting::set('tally_host', $cleanHost, 'tally');
        SystemSetting::set('tally_port', $port, 'tally');
        SystemSetting::set('tally_url', "http://{$cleanHost}:{$port}", 'tally');
        SystemSetting::set('tally_company_name', $companyName, 'tally');
        SystemSetting::set('tally_company', $companyName, 'tally');
        SystemSetting::set('tally_driver', $this->tallyDriver, 'tally');
        SystemSetting::set('tally_include_cost_centres', $this->tallyIncludeCostCentres ? '1' : '0', 'tally');
        SystemSetting::set('tally_auto_sync', $this->tallyAutoSync ? '1' : '0', 'tally');

        Notification::make()
            ->title('Tally Configuration Saved')
            ->body("Tally LAN target saved as http://{$cleanHost}:{$port} ({$companyName}). Adapter set to: " . ($this->tallyDriver === 'live' ? 'Live Tally Prime HTTP' : 'Development Simulator'))
            ->success()
            ->send();

        $this->refreshStatuses();
    }

    public function testTallyConnection(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
        $this->validate([
            'tallyHost' => 'required|string|max:150',
            'tallyPort' => 'required|numeric|min:1|max:65535',
        ]);

        $this->tallyTestResult = TallyPrimeHttpConnector::testConnection(
            $this->tallyHost,
            (int) $this->tallyPort,
            timeout: 3,
            companyName: $this->tallyCompanyName
        );

        if ($this->tallyTestResult['success']) {
            Notification::make()
                ->title('Tally Connection Successful')
                ->body($this->tallyTestResult['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Tally Connection Failed')
                ->body($this->tallyTestResult['message'])
                ->danger()
                ->send();
        }
    }

    public function dispatchOutbox(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
        $tallyConnector = app(AccountingConnectorInterface::class);
        $syncService = app(TallySyncService::class);
        $report = $syncService->processPendingOutbox($tallyConnector);
        $this->lastDispatchReport = $report;
        $this->refreshStatuses();

        if ($report['total_pending'] === 0) {
            Notification::make()
                ->title('No Pending Tally Vouchers')
                ->body('All queued journal vouchers are already synced.')
                ->info()
                ->send();

            return;
        }

        if ($report['failed'] === 0) {
            Notification::make()
                ->title('Tally Outbox Dispatched')
                ->body("Delivered and acknowledged {$report['processed']} voucher(s).")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Tally Outbox Partially Failed')
                ->body("Acknowledged: {$report['processed']}, Failed/Retryable: {$report['failed']}. Check the error summary below.")
                ->warning()
                ->send();
        }
    }

    public function requeueFailedOutbox(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
        $count = app(TallySyncService::class)->requeueFailedOutbox();
        $this->refreshStatuses();

        Notification::make()
            ->title('Failed Vouchers Re-queued')
            ->body("{$count} failed voucher(s) moved back to pending/retryable status.")
            ->info()
            ->send();
    }

    public function downloadMastersXml(): StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasAnyClubPermission(['integrations.view', 'integrations.manage']), 403);

        $export = app(TallySyncService::class)->buildMastersXmlExport($this->tallyIncludeCostCentres);

        return response()->streamDownload(function () use ($export) {
            echo $export['xml'];
        }, $export['filename'], [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function downloadPendingVouchersXml(): ?StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasAnyClubPermission(['integrations.view', 'integrations.manage']), 403);

        $syncService = app(TallySyncService::class);
        $export = $syncService->buildVouchersXmlExport(
            pendingOnly: true,
            includeCostCentres: $this->tallyIncludeCostCentres
        );

        if ($export['count'] === 0) {
            Notification::make()
                ->title('No Pending Tally Vouchers')
                ->body('There are currently zero pending journal vouchers to export.')
                ->info()
                ->send();

            return null;
        }

        if ($this->markExportedSynced) {
            abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
            $syncService->markExportedAsAcknowledged($export['journal_ids']);
            $this->refreshStatuses();
        }

        return response()->streamDownload(function () use ($export) {
            echo $export['xml'];
        }, $export['filename'], [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function downloadDateRangeVouchersXml(): ?StreamedResponse
    {
        abort_unless((bool) auth()->user()?->hasAnyClubPermission(['integrations.view', 'integrations.manage']), 403);

        $this->validate([
            'exportFromDate' => 'required|date',
            'exportToDate' => 'required|date|after_or_equal:exportFromDate',
        ]);

        $syncService = app(TallySyncService::class);
        $export = $syncService->buildVouchersXmlExport(
            fromDate: $this->exportFromDate,
            toDate: $this->exportToDate,
            pendingOnly: false,
            includeCostCentres: $this->tallyIncludeCostCentres
        );

        if ($export['count'] === 0) {
            Notification::make()
                ->title('No Journals Found')
                ->body("No journal vouchers found between {$this->exportFromDate} and {$this->exportToDate}.")
                ->warning()
                ->send();

            return null;
        }

        if ($this->markExportedSynced) {
            abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
            $syncService->markExportedAsAcknowledged($export['journal_ids']);
            $this->refreshStatuses();
        }

        return response()->streamDownload(function () use ($export) {
            echo $export['xml'];
        }, $export['filename'], [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function generateBridgeToken(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);

        $this->generatedBridgeToken = app(TallySyncService::class)->generateBridgeToken();
        $this->refreshStatuses();

        Notification::make()
            ->title('Tally Pull-Bridge Token Generated')
            ->body('Copy this token now — it will only be displayed once for security.')
            ->success()
            ->send();
    }

    public function revokeBridgeToken(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);

        app(TallySyncService::class)->revokeBridgeToken();
        $this->generatedBridgeToken = null;
        $this->refreshStatuses();

        Notification::make()
            ->title('Tally Pull-Bridge Token Revoked')
            ->body('Any office PC agent using the previous token can no longer pull vouchers.')
            ->warning()
            ->send();
    }

    public function testAccessEntry(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
        if (empty(trim($this->simCardNumber))) {
            return;
        }

        $accessProvider = app(AccessControlProviderInterface::class);
        $this->testResult = $accessProvider->authorizeEntry(trim($this->simCardNumber), $this->simFacilityCode);
    }

    public function generateSampleReceipt(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('integrations.manage'), 403);
        $printer = app(ReceiptPrinterInterface::class);
        $dummyReceipt = new PaymentReceipt([
            'receipt_number' => 'RCT-TEST-0001',
            'amount_paise' => 250000,
            'tender_type' => TenderType::DIGITAL,
        ]);
        $dummyReceipt->created_at = now();
        $this->printerPreview = $printer->render80mmReceipt(null, $dummyReceipt);
    }
}
