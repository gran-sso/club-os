<?php

namespace App\Filament\Pages;

use App\Domain\Integrations\Adapters\TallyPrimeHttpConnector;
use App\Domain\Shared\Models\SystemSetting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class SystemSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Club Master Setup';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'System Settings';

    protected static ?string $title = 'System & Clubhouse Settings';

    protected static ?string $slug = 'settings';

    protected string $view = 'filament.pages.system-settings';

    public string $activeTab = 'identity'; // 'identity', 'billing', 'facilities', 'tally', 'hardware'

    // Tab 1: Club Identity & Invoicing
    public string $clubName = '';
    public string $clubTagline = '';
    public string $clubAddress = '';
    public string $clubPhone = '';
    public string $clubEmail = '';
    public string $clubGstin = '';
    public string $clubPan = '';
    public string $receiptFooterNote = '';

    // Tab 2: Billing & Cashier Policies
    public float $defaultOpeningFloat = 2000.00;
    public float $minWalletRecharge = 500.00;
    public float $maxWalletRecharge = 50000.00;
    public float $lostCardFee = 150.00;
    public bool $allowSplitTender = true;
    public bool $allowZeroStockSales = false;

    // Tab 3: Front Desk & Facilities
    public string $checkInTime = '14:00';
    public string $checkOutTime = '12:00';
    public string $courtOpeningTime = '06:00';
    public string $courtClosingTime = '21:00';
    public int $courtAdvanceBookingDays = 7;
    public float $guestBarEntryFee = 200.00;
    public float $guestBadmintonEntryFee = 100.00;
    public float $guestGymEntryFee = 150.00;

    // Tab 4: Tally Prime & Local Integrations
    public string $tallyDriver = 'simulator';
    public string $tallyHost = '127.0.0.1';
    public int $tallyPort = 9000;
    public string $tallyCompany = 'Malabar Club';
    public ?string $tallyTestResult = null;
    public ?string $tallyTestError = null;

    // Tab 5: Receipt Printing & Hardware
    public string $receiptPaperWidth = '80mm';
    public bool $autoPrintReceipt = false;
    public bool $printWalletBalanceOnReceipt = true;
    public bool $printGstBreakdownOnReceipt = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('settings.manage'));
    }

    public function mount(): void
    {
        // Tab 1
        $this->clubName = (string) SystemSetting::get('club_name', 'Malabar Club, Vadakkencherry');
        $this->clubTagline = (string) SystemSetting::get('club_tagline', 'Members Club & Social Recreation Center');
        $this->clubAddress = (string) SystemSetting::get('club_address', 'Main Road, Vadakkencherry, Palakkad, Kerala - 678683');
        $this->clubPhone = (string) SystemSetting::get('club_phone', '+91 4922 255123');
        $this->clubEmail = (string) SystemSetting::get('club_email', 'office@malabarclub.com');
        $this->clubGstin = (string) SystemSetting::get('club_gstin', '32AABCM1234F1Z5');
        $this->clubPan = (string) SystemSetting::get('club_pan', 'AABCM1234F');
        $this->receiptFooterNote = (string) SystemSetting::get('receipt_footer_note', 'Thank you for visiting Malabar Club. Alcohol consumption is injurious to health.');

        // Tab 2
        $this->defaultOpeningFloat = (float) SystemSetting::get('default_opening_float', 2000.00);
        $this->minWalletRecharge = (float) SystemSetting::get('min_wallet_recharge', 500.00);
        $this->maxWalletRecharge = (float) SystemSetting::get('max_wallet_recharge', 50000.00);
        $this->lostCardFee = (float) SystemSetting::get('lost_card_fee', 150.00);
        $this->allowSplitTender = SystemSetting::getBool('allow_split_tender', true);
        $this->allowZeroStockSales = SystemSetting::getBool('allow_zero_stock_sales', false);

        // Tab 3
        $this->checkInTime = (string) SystemSetting::get('check_in_time', '14:00');
        $this->checkOutTime = (string) SystemSetting::get('check_out_time', '12:00');
        $this->courtOpeningTime = (string) SystemSetting::get('court_opening_time', '06:00');
        $this->courtClosingTime = (string) SystemSetting::get('court_closing_time', '21:00');
        $this->courtAdvanceBookingDays = (int) SystemSetting::get('court_advance_booking_days', 7);
        $this->guestBarEntryFee = (float) SystemSetting::get('guest_bar_entry_fee', 200.00);
        $this->guestBadmintonEntryFee = (float) SystemSetting::get('guest_badminton_entry_fee', 100.00);
        $this->guestGymEntryFee = (float) SystemSetting::get('guest_gym_entry_fee', 150.00);

        // Tab 4
        $this->tallyDriver = (string) SystemSetting::get('tally_driver', config('services.tally.driver', 'simulator'));
        $this->tallyHost = (string) SystemSetting::get('tally_host', config('services.tally.host', '127.0.0.1'));
        $this->tallyPort = (int) SystemSetting::get('tally_port', config('services.tally.port', 9000));
        $this->tallyCompany = (string) SystemSetting::get('tally_company', config('services.tally.company', 'Malabar Club'));

        // Tab 5
        $this->receiptPaperWidth = (string) SystemSetting::get('receipt_paper_width', '80mm');
        $this->autoPrintReceipt = SystemSetting::getBool('auto_print_receipt', false);
        $this->printWalletBalanceOnReceipt = SystemSetting::getBool('print_wallet_balance_on_receipt', true);
        $this->printGstBreakdownOnReceipt = SystemSetting::getBool('print_gst_breakdown_on_receipt', true);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['identity', 'billing', 'facilities', 'tally', 'hardware'])) {
            $this->activeTab = $tab;
        }
    }

    public function saveSettings(): void
    {
        try {
            $this->validate([
                'clubName' => 'required|string|max:150',
                'clubAddress' => 'required|string|max:255',
                'clubGstin' => 'nullable|string|max:20',
                'clubPan' => 'nullable|string|max:15',
                'defaultOpeningFloat' => 'required|numeric|min:0',
                'minWalletRecharge' => 'required|numeric|min:1',
                'maxWalletRecharge' => 'required|numeric|min:1000',
                'lostCardFee' => 'required|numeric|min:0',
                'tallyHost' => 'required|string',
                'tallyPort' => 'required|numeric|min:1|max:65535',
                'tallyCompany' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            $firstErrorField = array_key_first($e->errors());
            $tabByField = [
                'clubName' => 'identity',
                'clubAddress' => 'identity',
                'clubGstin' => 'identity',
                'clubPan' => 'identity',
                'defaultOpeningFloat' => 'billing',
                'minWalletRecharge' => 'billing',
                'maxWalletRecharge' => 'billing',
                'lostCardFee' => 'billing',
                'tallyHost' => 'tally',
                'tallyPort' => 'tally',
                'tallyCompany' => 'tally',
            ];
            if ($firstErrorField && isset($tabByField[$firstErrorField])) {
                $this->activeTab = $tabByField[$firstErrorField];
            }

            Notification::make()
                ->title('Cannot Save Settings')
                ->body(implode(' ', $e->validator->errors()->all()))
                ->danger()
                ->send();

            throw $e;
        }

        // Save Tab 1
        SystemSetting::set('club_name', $this->clubName, 'identity');
        SystemSetting::set('club_tagline', $this->clubTagline, 'identity');
        SystemSetting::set('club_address', $this->clubAddress, 'identity');
        SystemSetting::set('club_phone', $this->clubPhone, 'identity');
        SystemSetting::set('club_email', $this->clubEmail, 'identity');
        SystemSetting::set('club_gstin', strtoupper(trim($this->clubGstin)), 'identity');
        SystemSetting::set('club_pan', strtoupper(trim($this->clubPan)), 'identity');
        SystemSetting::set('receipt_footer_note', $this->receiptFooterNote, 'identity');

        // Save Tab 2
        SystemSetting::set('default_opening_float', $this->defaultOpeningFloat, 'billing');
        SystemSetting::set('min_wallet_recharge', $this->minWalletRecharge, 'billing');
        SystemSetting::set('max_wallet_recharge', $this->maxWalletRecharge, 'billing');
        SystemSetting::set('lost_card_fee', $this->lostCardFee, 'billing');
        SystemSetting::set('allow_split_tender', $this->allowSplitTender ? '1' : '0', 'billing');
        SystemSetting::set('allow_zero_stock_sales', $this->allowZeroStockSales ? '1' : '0', 'billing');

        // Save Tab 3
        SystemSetting::set('check_in_time', $this->checkInTime, 'facilities');
        SystemSetting::set('check_out_time', $this->checkOutTime, 'facilities');
        SystemSetting::set('court_opening_time', $this->courtOpeningTime, 'facilities');
        SystemSetting::set('court_closing_time', $this->courtClosingTime, 'facilities');
        SystemSetting::set('court_advance_booking_days', $this->courtAdvanceBookingDays, 'facilities');
        SystemSetting::set('guest_bar_entry_fee', $this->guestBarEntryFee, 'facilities');
        SystemSetting::set('guest_badminton_entry_fee', $this->guestBadmintonEntryFee, 'facilities');
        SystemSetting::set('guest_gym_entry_fee', $this->guestGymEntryFee, 'facilities');

        // Save Tab 4
        SystemSetting::set('tally_driver', $this->tallyDriver, 'tally');
        SystemSetting::set('tally_host', $this->tallyHost, 'tally');
        SystemSetting::set('tally_port', $this->tallyPort, 'tally');
        SystemSetting::set('tally_company', $this->tallyCompany, 'tally');

        // Save Tab 5
        SystemSetting::set('receipt_paper_width', $this->receiptPaperWidth, 'hardware');
        SystemSetting::set('auto_print_receipt', $this->autoPrintReceipt ? '1' : '0', 'hardware');
        SystemSetting::set('print_wallet_balance_on_receipt', $this->printWalletBalanceOnReceipt ? '1' : '0', 'hardware');
        SystemSetting::set('print_gst_breakdown_on_receipt', $this->printGstBreakdownOnReceipt ? '1' : '0', 'hardware');

        Notification::make()
            ->title('Settings Saved Successfully')
            ->body('Club configuration updated. All billing, receipting, and front-desk modules will immediately reflect these parameters.')
            ->success()
            ->send();
    }

    public function testTallyConnection(): void
    {
        $this->tallyTestResult = null;
        $this->tallyTestError = null;

        $probe = TallyPrimeHttpConnector::testConnection(
            host: $this->tallyHost,
            port: $this->tallyPort,
            timeout: 3
        );

        if ($probe['success']) {
            $this->tallyTestResult = $probe['message'];
            Notification::make()
                ->title('Tally Prime Connected')
                ->body($probe['message'])
                ->success()
                ->send();
        } else {
            $this->tallyTestError = $probe['message'];
            Notification::make()
                ->title('Tally Prime Connection Failed')
                ->body($probe['message'])
                ->danger()
                ->send();
        }
    }

    public function resetToDefaults(): void
    {
        $this->clubName = 'Malabar Club, Vadakkencherry';
        $this->clubTagline = 'Members Club & Social Recreation Center';
        $this->clubAddress = 'Main Road, Vadakkencherry, Palakkad, Kerala - 678683';
        $this->clubPhone = '+91 4922 255123';
        $this->clubEmail = 'office@malabarclub.com';
        $this->clubGstin = '32AABCM1234F1Z5';
        $this->clubPan = 'AABCM1234F';
        $this->receiptFooterNote = 'Thank you for visiting Malabar Club. Alcohol consumption is injurious to health.';

        $this->defaultOpeningFloat = 2000.00;
        $this->minWalletRecharge = 500.00;
        $this->maxWalletRecharge = 50000.00;
        $this->lostCardFee = 150.00;
        $this->allowSplitTender = true;
        $this->allowZeroStockSales = false;

        $this->checkInTime = '14:00';
        $this->checkOutTime = '12:00';
        $this->courtOpeningTime = '06:00';
        $this->courtClosingTime = '21:00';
        $this->courtAdvanceBookingDays = 7;
        $this->guestBarEntryFee = 200.00;
        $this->guestBadmintonEntryFee = 100.00;
        $this->guestGymEntryFee = 150.00;

        $this->receiptPaperWidth = '80mm';
        $this->autoPrintReceipt = false;
        $this->printWalletBalanceOnReceipt = true;
        $this->printGstBreakdownOnReceipt = true;

        $this->saveSettings();
    }
}

