<?php

namespace Tests\Feature;

use App\Domain\Finance\Exceptions\PeriodClosedException;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\BankTransaction;
use App\Domain\Finance\Services\BankingService;
use App\Domain\Finance\Services\PeriodClosingService;
use App\Filament\Resources\BankAccounts\Pages\CreateBankAccount;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BankAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected BankingService $bankingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->bankingService = app(BankingService::class);
    }

    public function test_admin_can_access_bank_accounts_resource(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/bank-accounts')
            ->assertSuccessful();
    }

    public function test_manager_can_access_bank_accounts_resource(): void
    {
        $this->actingAs($this->manager)
            ->get('/admin/bank-accounts')
            ->assertSuccessful();
    }

    public function test_cashier_cannot_access_bank_accounts_resource(): void
    {
        $this->actingAs($this->cashier)
            ->get('/admin/bank-accounts')
            ->assertForbidden();
    }

    public function test_create_bank_account_via_banking_service(): void
    {
        $bankGl = Account::where('code', '1020')->firstOrFail();

        $account = $this->bankingService->createBankAccount([
            'account_name' => 'Canara Bank Special Project A/c',
            'bank_name' => 'Canara Bank',
            'branch_name' => 'Vadakkencherry Town',
            'account_number' => 'CAN-9876543210',
            'account_type' => 'current',
            'ifsc_code' => 'CNRB0001928',
            'upi_id' => 'malabarclub@canara',
            'gl_account_id' => $bankGl->id,
            'opening_balance_paise' => 10000000, // ₹1,00,000.00
            'is_primary' => true,
            'is_active' => true,
            'notes' => 'Club development project fund account',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'account_number' => 'CAN-9876543210',
            'bank_name' => 'Canara Bank',
            'current_balance_paise' => 10000000,
            'is_primary' => true,
        ]);

        $this->assertEquals(100000.00, $account->current_balance);
        $this->assertEquals('CAN-9876543210', $account->account_number);
    }

    public function test_deposit_counter_cash_to_bank_posts_balanced_contra_journal(): void
    {
        $bankAccount = BankAccount::firstOrFail();
        $initialBalance = $bankAccount->current_balance_paise;
        $depositAmountPaise = 7500000; // ₹75,000.00

        $transaction = $this->bankingService->depositCash(
            bankAccount: $bankAccount,
            amountPaise: $depositAmountPaise,
            businessDate: '2026-11-09',
            referenceNumber: 'CHAL-SBI-20261109-001',
            depositedBy: 'Ramesh Menon (Treasurer)',
            description: 'Counter cash deposit for weekend sports & bar collections',
            userId: $this->admin->id
        );

        $this->assertInstanceOf(BankTransaction::class, $transaction);
        $this->assertStringStartsWith('DEP-', $transaction->transaction_number);
        $this->assertEquals('cash_deposit', $transaction->type);
        $this->assertEquals($depositAmountPaise, $transaction->amount_paise);

        // Verify bank balance incremented
        $bankAccount->refresh();
        $this->assertEquals($initialBalance + $depositAmountPaise, $bankAccount->current_balance_paise);

        // Verify balanced General Ledger contra journal
        $this->assertNotNull($transaction->journal_header_id);
        $journal = $transaction->journalHeader;
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals($depositAmountPaise, $journal->totalDebitsPaise());
        $this->assertEquals($depositAmountPaise, $journal->totalCreditsPaise());

        // Debit Bank Account, Credit Cash in Hand (1010)
        $lines = $journal->lines;
        $bankLine = $lines->firstWhere('account_id', $bankAccount->gl_account_id);
        $this->assertNotNull($bankLine);
        $this->assertEquals($depositAmountPaise, $bankLine->debit_paise);
        $this->assertEquals(0, $bankLine->credit_paise);

        $cashAccount = Account::where('code', '1010')->firstOrFail();
        $cashLine = $lines->firstWhere('account_id', $cashAccount->id);
        $this->assertNotNull($cashLine);
        $this->assertEquals(0, $cashLine->debit_paise);
        $this->assertEquals($depositAmountPaise, $cashLine->credit_paise);
    }

    public function test_inter_bank_transfer_moves_funds_and_posts_contra_journal(): void
    {
        $accounts = BankAccount::where('is_active', true)->take(2)->get();
        if ($accounts->count() < 2) {
            $this->markTestSkipped('Need at least 2 bank accounts for inter-bank transfer test.');
        }

        $from = $accounts[0];
        $to = $accounts[1];

        $fromInitial = $from->current_balance_paise;
        $toInitial = $to->current_balance_paise;
        $transferPaise = 2500000; // ₹25,000.00

        $transaction = $this->bankingService->transferFunds(
            fromAccount: $from,
            toAccount: $to,
            amountPaise: $transferPaise,
            businessDate: '2026-11-09',
            referenceNumber: 'NEFT-AXIS-20261109-9821',
            description: 'Liquidity transfer to operating account',
            userId: $this->admin->id
        );

        $this->assertStringStartsWith('TRF-', $transaction->transaction_number);
        $this->assertEquals('inter_bank_transfer', $transaction->type);

        $from->refresh();
        $to->refresh();

        $this->assertEquals($fromInitial - $transferPaise, $from->current_balance_paise);
        $this->assertEquals($toInitial + $transferPaise, $to->current_balance_paise);

        // Verify journal balances
        $this->assertNotNull($transaction->journal_header_id);
        $journal = $transaction->journalHeader;
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals($transferPaise, $journal->totalDebitsPaise());
        $this->assertEquals($transferPaise, $journal->totalCreditsPaise());
    }

    public function test_cash_deposit_blocked_on_closed_financial_period(): void
    {
        $periodService = app(PeriodClosingService::class);
        $periodService->closePeriod(
            periodCode: 'FY2026-M10',
            startDate: '2026-10-01',
            endDate: '2026-10-31',
            notes: 'October audited and locked',
            user: $this->admin
        );

        $bankAccount = BankAccount::firstOrFail();

        $this->expectException(PeriodClosedException::class);

        $this->bankingService->depositCash(
            bankAccount: $bankAccount,
            amountPaise: 1000000,
            businessDate: '2026-10-15', // Falls into closed period
            referenceNumber: 'CHAL-OLD',
            userId: $this->admin->id
        );
    }

    public function test_filament_list_bank_accounts_and_deposit_cash_action(): void
    {
        $bankAccount = BankAccount::firstOrFail();
        $initialBalance = $bankAccount->current_balance_paise;

        Livewire::actingAs($this->admin)
            ->test(ListBankAccounts::class)
            ->assertSuccessful()
            ->assertSee($bankAccount->account_name)
            ->callTableAction('depositCash', $bankAccount, [
                'amount_rupees' => 30000.00,
                'business_date' => '2026-11-09',
                'reference_number' => 'SLIP-TEST-1049',
                'deposited_by' => 'Front Desk Manager',
                'description' => 'Test deposit via Filament UI',
            ])
            ->assertHasNoTableActionErrors();

        $bankAccount->refresh();
        $this->assertEquals($initialBalance + 3000000, $bankAccount->current_balance_paise);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'type' => 'cash_deposit',
            'amount_paise' => 3000000,
            'reference_number' => 'SLIP-TEST-1049',
        ]);
    }

    public function test_filament_create_bank_account_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateBankAccount::class)
            ->fillForm([
                'bank_name' => 'South Indian Bank',
                'branch_name' => 'Vadakkencherry Branch',
                'account_name' => 'SIB Operational Current A/c',
                'account_number' => '0098104820938',
                'account_type' => 'current',
                'ifsc_code' => 'SIBL0000098',
                'opening_balance_paise' => 50000.00,
                'is_primary' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bank_accounts', [
            'account_number' => '0098104820938',
            'bank_name' => 'South Indian Bank',
            'current_balance_paise' => 5000000,
        ]);
    }

    public function test_expense_disbursement_drawn_from_bank_account(): void
    {
        $bankAccount = BankAccount::firstOrFail();
        $initialBalance = $bankAccount->current_balance_paise;
        $expenseAmountPaise = 1250000; // ₹12,500.00

        $expenseService = app(\App\Domain\Finance\Services\ExpenseService::class);
        $expense = $expenseService->recordExpense([
            'payee' => 'Vadakkencherry Water Authority',
            'amount_paise' => $expenseAmountPaise,
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $bankAccount->id,
            'document_reference' => 'UTR-NEFT-20261109-0099',
            'expense_date' => '2026-11-09',
            'notes' => 'Clubhouse monthly water supply charges',
        ], $this->admin);

        $bankAccount->refresh();
        $this->assertEquals($initialBalance - $expenseAmountPaise, $bankAccount->current_balance_paise);
        $this->assertEquals($bankAccount->id, $expense->bank_account_id);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'type' => 'expense_payout',
            'amount_paise' => $expenseAmountPaise,
            'reference_number' => 'UTR-NEFT-20261109-0099',
        ]);
    }

    public function test_contractor_settlement_payout_drawn_from_bank_account(): void
    {
        $bankAccount = BankAccount::firstOrFail();
        $initialBalance = $bankAccount->current_balance_paise;
        $contractor = \App\Domain\Contractor\Models\Contractor::firstOrFail();
        $settlementService = app(\App\Domain\Contractor\Services\ContractorSettlementService::class);

        $batch = $settlementService->createSettlementBatch($contractor, '2026-11-09', $this->admin->id);
        $settlementService->approveSettlementBatch($batch, $this->admin);

        $payoutAmountPaise = 500000; // ₹5,000.00
        $reference = 'NEFT: UTR-SBI-SETTLE-001';

        // Record bank disbursement
        $this->bankingService->recordDisbursement(
            bankAccount: $bankAccount,
            amountPaise: $payoutAmountPaise,
            type: 'contractor_payout',
            description: "Contractor Payout: {$contractor->name} [Batch {$batch->batch_number}]",
            businessDate: '2026-11-09',
            referenceNumber: $reference,
            userId: $this->admin->id
        );

        $bankAccount->refresh();
        $this->assertEquals($initialBalance - $payoutAmountPaise, $bankAccount->current_balance_paise);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'type' => 'contractor_payout',
            'amount_paise' => $payoutAmountPaise,
        ]);
    }
}
