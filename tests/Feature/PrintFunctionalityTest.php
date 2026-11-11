<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Models\GuestPass;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\Counter;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Inventory\Models\PurchaseReceipt;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\PassStatus;
use App\Filament\Pages\FinancialReportsPage;
use App\Filament\Pages\HelpPage;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Livewire\CounterPos;
use App\Livewire\Member\MemberDashboard;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrintFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Member $member;
    protected Counter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
    }

    public function test_compiled_css_isolates_open_modals_and_fixes_blank_filament_print(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $this->assertArrayHasKey('resources/css/app.css', $manifest);

        $cssFile = public_path('build/' . $manifest['resources/css/app.css']['file']);
        $this->assertFileExists($cssFile);

        $css = file_get_contents($cssFile);

        // Bug #1 regression check: closed .fi-modal-window inside .fi-layout must never hide .fi-layout
        $this->assertStringNotContainsString('body:has(.fi-modal-window) .fi-layout', $css);

        // Clean modal & print isolation selectors in production CSS
        $this->assertStringContainsString('.fi-modal:not(.fi-modal-open)', $css);
        $this->assertStringContainsString('.fi-modal.fi-modal-open', $css);
        $this->assertStringContainsString('[data-print-document=modal]', $css);
        $this->assertStringContainsString('#printable-pos-receipt', $css);
        $this->assertStringContainsString('[data-help-print-mode=desk-reference]', $css);
        $this->assertStringContainsString('.print-report-table', $css);
        $this->assertStringContainsString('.fi-header', $css);
        $this->assertStringContainsString('color-scheme:light', str_replace(': ', ':', $css));
    }

    public function test_pwa_mobile_nav_has_single_class_attribute(): void
    {
        $blade = file_get_contents(resource_path('views/filament/components/pwa-mobile-nav.blade.php'));
        $this->assertSame(0, substr_count($blade, '}" class="relative">'));
        $this->assertStringContainsString('<div class="relative print:hidden no-print"', $blade);
    }

    public function test_filament_modal_partials_mark_printable_documents_and_safe_contrasts(): void
    {
        $person = Person::create([
            'first_name' => 'Arjun',
            'last_name' => 'Menon',
            'phone' => '+91 98470 55555',
        ]);

        $guest = Guest::create([
            'person_id' => $person->id,
            'sponsor_type' => Member::class,
            'sponsor_id' => $this->member->id,
            'visit_date' => now()->toDateString(),
            'status' => 'checked_in',
        ]);

        $pass = GuestPass::create([
            'guest_id' => $guest->id,
            'pass_code' => 'GP-TEST-001',
            'facility_code' => 'card_room',
            'status' => PassStatus::ACTIVE,
            'valid_from' => now(),
            'valid_until' => now()->addHours(12),
            'entry_count' => 0,
            'max_entries' => 1,
        ]);

        $guestHtml = view('filament.components.guest-pass-modal', [
            'guest' => $guest->load(['person', 'sponsor']),
            'pass' => $pass,
        ])->render();
        $this->assertStringContainsString('data-print-document="filament-modal"', $guestHtml);
        $this->assertStringContainsString('id="printable-guest-pass"', $guestHtml);
        $this->assertStringContainsString('print:border-2', $guestHtml);
        $this->assertStringContainsString('print:text-slate-950', $guestHtml);

        $bill = Bill::forceCreate([
            'bill_number' => 'BILL-PRINT-001',
            'shift_id' => null,
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'department_code' => 'bar',
            'status' => 'paid',
            'gross_amount_paise' => 50000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 9000,
            'net_amount_paise' => 59000,
            'paid_amount_paise' => 59000,
            'idempotency_key' => 'IDEM-PRINT-001',
            'created_by_user_id' => $this->admin->id,
        ]);

        $billHtml = view('filament.components.bill-detail-modal', [
            'bill' => $bill->load(['lines', 'receipts', 'customer']),
        ])->render();
        $this->assertStringContainsString('data-print-document="filament-modal"', $billHtml);
        $this->assertStringContainsString('print:overflow-visible', $billHtml);
        $this->assertStringContainsString('BILL-PRINT-001', $billHtml);

        $journal = JournalHeader::first() ?? new JournalHeader([
            'journal_number' => 'JRN-PRINT-001',
            'business_date' => now()->toDateString(),
            'source_type' => 'manual',
            'description' => 'Print verification journal entry',
        ]);
        $journalHtml = view('filament.components.journal-voucher-modal', [
            'journal' => $journal,
            'lines' => $journal->exists ? $journal->lines()->with('account')->get() : collect(),
        ])->render();
        $this->assertStringContainsString('data-print-document="filament-modal"', $journalHtml);
        $this->assertStringContainsString('print:overflow-visible', $journalHtml);

        $receipt = new PurchaseReceipt([
            'receipt_number' => 'GRN-PRINT-001',
            'invoice_number' => 'SUP-9001',
            'invoice_date' => now()->toDateString(),
            'total_gross_paise' => 100000,
            'total_discount_paise' => 0,
            'total_tax_paise' => 0,
            'total_net_paise' => 100000,
            'status' => 'posted',
        ]);
        $grnHtml = view('filament.components.purchase-receipt-modal', [
            'receipt' => $receipt,
            'lines' => collect(),
        ])->render();
        $this->assertStringContainsString('data-print-document="filament-modal"', $grnHtml);
        $this->assertStringContainsString('print:text-slate-950', $grnHtml);
        $this->assertStringContainsString('print:overflow-visible', $grnHtml);

        $expense = new Expense([
            'expense_number' => 'PV-PRINT-001',
            'payee' => 'KSEB Palakkad',
            'amount_paise' => 250000,
            'payment_method' => 'bank_transfer',
            'status' => 'approved',
            'expense_date' => now()->toDateString(),
        ]);
        $expenseHtml = view('filament.components.expense-voucher-modal', [
            'expense' => $expense,
        ])->render();
        $this->assertStringContainsString('data-print-document="filament-modal"', $expenseHtml);
        $this->assertStringContainsString('print:text-slate-950', $expenseHtml);

        $statementHtml = view('filament.components.member-statement-modal', [
            'member' => $this->member->load('activeCard'),
            'balanceRupees' => 1500.00,
            'entries' => collect(),
        ])->render();
        $this->assertStringContainsString('data-print-document="filament-modal"', $statementHtml);
        $this->assertStringContainsString('print:overflow-visible', $statementHtml);

        $bankAccount = BankAccount::first() ?? new BankAccount([
            'account_name' => 'Primary Club Current Account',
            'bank_name' => 'Federal Bank',
            'branch_name' => 'Vadakkencherry',
            'account_number' => '100020003000',
            'ifsc_code' => 'FDRL0001000',
            'current_balance_paise' => 500000,
            'is_primary' => true,
        ]);
        $bankHtml = view('filament.components.bank-passbook-modal', [
            'account' => $bankAccount,
        ])->render();
        $this->assertStringContainsString('data-print-document="filament-modal"', $bankHtml);
        $this->assertStringContainsString('print:bg-white print:text-slate-900', $bankHtml);
    }

    public function test_counter_pos_receipt_modal_isolates_thermal_receipt_and_hides_workspace(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->set('rechargeAmount', 1000.00)
            ->set('rechargeTender', 'cash')
            ->call('rechargeWallet')
            ->assertSet('showReceiptModal', true)
            ->assertSeeHtml('<pre id="printable-pos-receipt" data-print-document="modal"')
            ->assertSeeHtml('print:w-fit')
            ->assertSeeHtml('grid grid-cols-1 lg:grid-cols-12 gap-5 items-start print:hidden');
    }

    public function test_member_portal_wallet_statement_and_invoice_modal_render_print_light_theme(): void
    {
        $walletService = app(WalletService::class);
        $walletAccount = $this->member->wallet;
        if (! $walletAccount) {
            $walletAccount = \App\Domain\Prepaid\Models\WalletAccount::create([
                'walletable_type' => Member::class,
                'walletable_id' => $this->member->id,
                'balance_paise' => 0,
                'reserved_paise' => 0,
                'status' => 'active',
            ]);
        }

        if ($walletAccount->balance_paise < 50000) {
            $walletService->recharge(
                $walletAccount,
                50000,
                \App\Domain\Shared\Enums\TenderType::CASH,
                'PRINT-TEST-RECHARGE-' . uniqid(),
                null,
                null,
                $this->admin->id
            );
        }

        $bill = Bill::forceCreate([
            'bill_number' => 'BILL-MEM-PRINT-01',
            'shift_id' => null,
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'department_code' => 'restaurant',
            'status' => 'paid',
            'gross_amount_paise' => 30000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 1500,
            'net_amount_paise' => 31500,
            'paid_amount_paise' => 31500,
            'idempotency_key' => 'IDEM-MEM-PRINT-01',
            'created_by_user_id' => $this->admin->id,
        ]);

        BillLine::create([
            'bill_id' => $bill->id,
            'department_code' => 'restaurant',
            'item_type' => 'menu',
            'item_id' => 1,
            'item_name' => 'Malabar Chicken Biryani',
            'quantity' => 1,
            'unit_price_paise' => 30000,
            'gross_amount_paise' => 30000,
            'discount_amount_paise' => 0,
            'tax_percent' => 5.00,
            'tax_amount_paise' => 1500,
            'line_total_paise' => 31500,
        ]);

        // Layout uses light print overrides
        $this->actingAs($this->member, 'member')
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeHtml('print:bg-white print:text-slate-900');

        // Wallet tab hides dashboard chrome and prints official statement letterhead
        Livewire::actingAs($this->member, 'member')
            ->test(MemberDashboard::class)
            ->call('setTab', 'wallet')
            ->assertSeeHtml('id="printable-member-wallet-statement"')
            ->assertSee('Official Member Prepaid Wallet Statement')
            ->assertSeeHtml('print:bg-white print:text-slate-900')
            ->assertSeeHtml('print:text-emerald-800')
            ->assertSeeHtml('print:hidden');

        // Itemized bill modal isolates #printable-member-invoice cleanly
        Livewire::actingAs($this->member, 'member')
            ->test(MemberDashboard::class)
            ->call('setTab', 'bills')
            ->call('openBillModal', $bill->id)
            ->assertSet('showBillModal', true)
            ->assertSeeHtml('id="printable-member-invoice" data-print-document="modal"')
            ->assertSeeHtml('print:max-h-none print:overflow-visible')
            ->assertSee('Malabar Chicken Biryani');
    }

    public function test_view_member_statement_invoice_modal_and_intro_letter_print_isolation(): void
    {
        $bill = Bill::forceCreate([
            'bill_number' => 'BILL-VM-PRINT-01',
            'shift_id' => null,
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'department_code' => 'bar',
            'status' => 'paid',
            'gross_amount_paise' => 40000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 7200,
            'net_amount_paise' => 47200,
            'paid_amount_paise' => 47200,
            'idempotency_key' => 'IDEM-VM-PRINT-01',
            'created_by_user_id' => $this->admin->id,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->getKey()]);

        $component->call('printStatement')
            ->assertSet('activeTab', 'wallet')
            ->assertSee('Official Member Wallet & Account Statement')
            ->assertSeeHtml('print:overflow-visible')
            ->assertSeeHtml('print:min-w-0');

        $component->call('openInvoiceModal', $bill->id)
            ->assertSet('showInvoiceModal', true)
            ->assertSeeHtml('id="printable-admin-invoice" data-print-document="modal"')
            ->call('closeInvoiceModal');

        $component
            ->set('letterDestinationClub', 'Bombay Gymkhana')
            ->set('letterVisitStart', '2026-12-01')
            ->set('letterVisitEnd', '2026-12-05')
            ->call('generateIntroLetter')
            ->assertSet('showLetterViewModal', true)
            ->assertSeeHtml('id="printable-intro-letter" data-print-document="modal"')
            ->assertSeeHtml('break-inside-avoid');
    }

    public function test_financial_reports_and_help_page_print_markup(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FinancialReportsPage::class)
            ->assertSeeHtml('print:grid-cols-4')
            ->assertSeeHtml('print:overflow-visible')
            ->assertSeeHtml('print-report-table')
            ->call('setActiveTab', 'income_expenditure')
            ->assertSeeHtml('print:bg-none print:bg-white print:text-slate-900')
            ->assertSeeHtml('print:grid-cols-2');

        Livewire::actingAs($this->admin)
            ->test(HelpPage::class)
            ->assertSeeHtml(':data-help-print-mode="printMode"')
            ->assertSeeHtml('@afterprint.window="printMode = \'procedure\'"')
            ->assertSeeHtml('help-procedure-view')
            ->assertSeeHtml('help-desk-reference-view')
            ->assertSeeHtml('print:overflow-visible');
    }
}
