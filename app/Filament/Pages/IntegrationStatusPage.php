<?php

namespace App\Filament\Pages;

use App\Domain\Billing\Enums\TenderType;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Integrations\Adapters\SimulatorAccessControlProvider;
use App\Domain\Integrations\Adapters\SimulatorTallyConnector;
use App\Domain\Integrations\Adapters\TallyPrimeHttpConnector;
use App\Domain\Integrations\Adapters\ThermalReceiptPrinter;
use App\Domain\Integrations\Contracts\AccessControlProviderInterface;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallySyncService;
use App\Domain\Shared\Models\SystemSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

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

    // Tally Configuration Form State
    public string $tallyHost = '127.0.0.1';
    public string $tallyPort = '9000';
    public string $tallyCompanyName = 'Malabar Club';
    public string $tallyDriver = 'simulator';
    public ?array $tallyTestResult = null;

    // Hardware Simulation State
    public string $simCardNumber = '';
    public string $simFacilityCode = 'bar';
    public ?array $testResult = null;
    public ?string $printerPreview = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('integrations.manage')));
    }

    public function mount(): void
    {
        $this->tallyHost = (string) SystemSetting::get('tally_host', env('TALLY_HOST', '127.0.0.1'));
        $this->tallyPort = (string) SystemSetting::get('tally_port', env('TALLY_PORT', '9000'));
        $this->tallyCompanyName = (string) SystemSetting::get('tally_company_name', env('TALLY_COMPANY_NAME', 'Malabar Club'));
        $this->tallyDriver = (string) SystemSetting::get('tally_driver', app()->environment('production') ? 'live' : 'simulator');

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

        $this->outboxStats = [
            'pending' => OutboxMessage::where('status', 'pending')->count(),
            'acknowledged' => OutboxMessage::where('status', 'acknowledged')->count(),
            'failed' => OutboxMessage::where('status', 'failed')->count(),
        ];
    }

    public function saveTallySettings(): void
    {
        $this->validate([
            'tallyHost' => 'required|string|max:150',
            'tallyPort' => 'required|numeric|min:1|max:65535',
            'tallyCompanyName' => 'required|string|max:150',
            'tallyDriver' => 'required|in:simulator,live',
        ]);

        $cleanHost = preg_replace('#^https?://#', '', trim($this->tallyHost));
        $port = (int) $this->tallyPort;

        SystemSetting::set('tally_host', $cleanHost, 'tally');
        SystemSetting::set('tally_port', $port, 'tally');
        SystemSetting::set('tally_url', "http://{$cleanHost}:{$port}", 'tally');
        SystemSetting::set('tally_company_name', trim($this->tallyCompanyName), 'tally');
        SystemSetting::set('tally_driver', $this->tallyDriver, 'tally');

        Notification::make()
            ->title('Tally Configuration Saved')
            ->body("Tally LAN target saved as http://{$cleanHost}:{$port} ({$this->tallyCompanyName}). Adapter set to: " . ($this->tallyDriver === 'live' ? 'Live Tally Prime HTTP' : 'Development Simulator'))
            ->success()
            ->send();

        $this->refreshStatuses();
    }

    public function testTallyConnection(): void
    {
        $this->validate([
            'tallyHost' => 'required|string|max:150',
            'tallyPort' => 'required|numeric|min:1|max:65535',
        ]);

        $this->tallyTestResult = TallyPrimeHttpConnector::testConnection(
            $this->tallyHost,
            (int) $this->tallyPort,
            timeout: 3
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
        $tallyConnector = app(AccountingConnectorInterface::class);
        $syncService = app(TallySyncService::class);
        $syncService->processPendingOutbox($tallyConnector);
        $this->refreshStatuses();
    }

    public function testAccessEntry(): void
    {
        if (empty(trim($this->simCardNumber))) {
            return;
        }

        $accessProvider = app(AccessControlProviderInterface::class);
        $this->testResult = $accessProvider->authorizeEntry(trim($this->simCardNumber), $this->simFacilityCode);
    }

    public function generateSampleReceipt(): void
    {
        $printer = app(ReceiptPrinterInterface::class);
        $dummyReceipt = new PaymentReceipt([
            'receipt_number' => 'RCT-TEST-0001',
            'amount_paise' => 250000,
            'tender_type' => TenderType::UPI,
        ]);
        $dummyReceipt->created_at = now();
        $this->printerPreview = $printer->render80mmReceipt(null, $dummyReceipt);
    }
}
