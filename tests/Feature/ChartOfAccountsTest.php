<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Expense;
use App\Domain\Shared\Enums\AccountType;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->accountant = User::where('email', 'accountant@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
    }

    public function test_core_system_accounts_and_standard_expense_heads_are_seeded(): void
    {
        // 1. Verify Core System Accounts are marked is_system = true
        $cash = Account::where('code', '1010')->firstOrFail();
        $this->assertTrue($cash->is_system);
        $this->assertEquals(AccountType::ASSET, $cash->type);

        $bank = Account::where('code', '1020')->firstOrFail();
        $this->assertTrue($bank->is_system);

        $prepaidLiability = Account::where('code', '2010')->firstOrFail();
        $this->assertTrue($prepaidLiability->is_system);
        $this->assertEquals(AccountType::LIABILITY, $prepaidLiability->type);

        $cogsLiquor = Account::where('code', '5010')->firstOrFail();
        $this->assertTrue($cogsLiquor->is_system);
        $this->assertEquals(AccountType::EXPENSE, $cogsLiquor->type);

        // 2. Verify Discretionary Operational Expense Heads are seeded with is_system = false
        $kseb = Account::where('code', '5110')->firstOrFail();
        $this->assertFalse($kseb->is_system);
        $this->assertEquals('Electricity & Power Charges (KSEB)', $kseb->name);
        $this->assertEquals('Utilities & Power', $kseb->category);

        $diesel = Account::where('code', '5120')->firstOrFail();
        $this->assertFalse($diesel->is_system);
        $this->assertEquals('Generator Diesel & Lubricants', $diesel->name);

        $courtMaintenance = Account::where('code', '5220')->firstOrFail();
        $this->assertFalse($courtMaintenance->is_system);
        $this->assertEquals('Badminton Court & Sports Equipment AMC', $courtMaintenance->name);
    }

    public function test_admin_can_access_chart_of_accounts(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/accounts')->assertSuccessful();
        $this->get('/admin/accounts/create')->assertSuccessful();
    }

    public function test_accountant_can_access_chart_of_accounts(): void
    {
        $this->actingAs($this->accountant);
        $this->get('/admin/accounts')->assertSuccessful();
        $this->get('/admin/accounts/create')->assertSuccessful();
    }

    public function test_cashier_cannot_access_chart_of_accounts(): void
    {
        $this->actingAs($this->cashier);
        $this->get('/admin/accounts')->assertForbidden();
        $this->get('/admin/accounts/create')->assertForbidden();
    }

    public function test_accountant_can_create_new_discretionary_expense_head(): void
    {
        $this->actingAs($this->accountant);

        Livewire::test(CreateAccount::class)
            ->fillForm([
                'code' => '5160',
                'name' => 'Generator Battery & UPS Servicing',
                'type' => AccountType::EXPENSE->value,
                'category' => 'Utilities & Power',
                'description' => 'Periodic replacement of tubular batteries and inverter AMC.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('accounts', [
            'code' => '5160',
            'name' => 'Generator Battery & UPS Servicing',
            'type' => AccountType::EXPENSE->value,
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    public function test_protected_system_accounts_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin);

        $systemAcc = Account::where('code', '1010')->firstOrFail();

        // 1. canDelete policy returns false
        $this->assertFalse(AccountResource::canDelete($systemAcc));

        // 2. Table action cancels deletion
        Livewire::test(ListAccounts::class)
            ->callTableAction(DeleteAction::class, $systemAcc);

        $this->assertDatabaseHas('accounts', ['id' => $systemAcc->id]);
    }

    public function test_accounts_with_historical_records_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin);

        $ksebAcc = Account::where('code', '5110')->firstOrFail();

        // Attach an operational expense voucher to this account
        Expense::create([
            'expense_number' => 'EXP-TEST-9901',
            'account_id' => $ksebAcc->id,
            'payee' => 'Kerala State Electricity Board',
            'amount_paise' => 4500000,
            'payment_method' => 'neft_rtgs',
            'expense_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->assertTrue($ksebAcc->hasHistoricalLines());

        // Attempt deletion via Livewire Table DeleteAction
        Livewire::test(ListAccounts::class)
            ->callTableAction(DeleteAction::class, $ksebAcc);

        // Account must remain in database
        $this->assertDatabaseHas('accounts', ['id' => $ksebAcc->id]);
    }

    public function test_expense_form_has_dropdown_with_custom_expense_heads(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/expenses/create');
        $response->assertSuccessful();
        $response->assertSee('General Ledger Expense Head');

        Livewire::test(CreateExpense::class)
            ->assertFormFieldExists('account_id');
    }

    public function test_chart_of_accounts_global_search(): void
    {
        $this->actingAs($this->admin);

        // Search by Code
        $results = AccountResource::getGlobalSearchResults('5110');
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('KSEB', $results->first()->title);
        $this->assertArrayHasKey('Code', $results->first()->details);
        $this->assertArrayHasKey('Group', $results->first()->details);

        // Search by Name
        $nameResults = AccountResource::getGlobalSearchResults('Diesel');
        $this->assertNotEmpty($nameResults);
        $this->assertStringContainsString('Generator Diesel', $nameResults->first()->title);
    }
}
