<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Finance\Exceptions\PeriodClosedException;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Services\FinancialReportService;
use App\Domain\Finance\Services\PeriodClosingService;
use App\Domain\Integrations\Models\ImportBatch;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Prepaid\Models\WalletTransaction;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\ImportMembers;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class MemberBulkImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cashier;

    protected MemberService $memberService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->memberService = app(MemberService::class);
    }

    public function test_generated_import_template_validates_cleanly_on_dry_run_preview(): void
    {
        $templateCsv = $this->memberService->generateImportTemplateCsv();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $templateCsv);

        $preview = $this->memberService->previewCsvImport($templateCsv);

        $this->assertEquals(3, $preview['total_rows']);
        $this->assertEquals(3, $preview['valid_count']);
        $this->assertEquals(0, $preview['failed_count']);
        $this->assertEquals(1750000, $preview['summary']['total_opening_balance_paise']);
        $this->assertEquals(1, $preview['summary']['cards_to_issue_count']);
    }

    public function test_bulk_import_existing_paid_members_creates_no_fee_receipts_and_reconciles_opening_wallets(): void
    {
        $fyEnd = MemberService::defaultAnnualValidUntilDate();
        $csv = "\xEF\xBB\xBF" . implode("\r\n", [
            'membership_number,first_name,last_name,phone,email,category_code,status,joined_at,valid_until,admission_fee_status,annual_fee_status,historical_receipt_ref,opening_balance_rupees,rfid_card_number',
            'MC-MIG-101,Suresh,Nair,+919847011223,suresh@example.com,ORD,active,15/06/2021,2027-03-31,paid,paid,REG-2021-045,5000.00,CARD-BULK-101',
            'MC-MIG-102,Lakshmi,Menon,,lakshmi@example.com,LIFE,active,01/04/2018,,paid,paid,LIFE-089,12500.50,',
            'MC-MIG-103,Rajesh,Kurup,+919447033445,,CORP,active,2023-01-10,,,,,0.00,',
        ]);

        $preview = $this->memberService->previewCsvImport($csv);

        $this->assertEquals(3, $preview['total_rows']);
        $this->assertEquals(3, $preview['valid_count']);
        $this->assertEquals(0, $preview['failed_count']);
        $this->assertEquals(1, $preview['warning_count']); // Missing phone on MC-MIG-102
        $this->assertEquals(1750050, $preview['summary']['total_opening_balance_paise']);

        $initialReceiptsCount = PaymentReceipt::count();
        $initialOutboxCount = OutboxMessage::count();

        $batch = $this->memberService->executeImport($preview['valid_rows'], $this->admin->id, [
            'fee_mode' => 'already_paid',
            'opening_balance_date' => now()->toDateString(),
            'queue_tally_outbox' => false,
        ]);

        $this->assertEquals('completed', $batch->status);
        $this->assertEquals(3, $batch->imported_rows);

        // Zero PaymentReceipts created for historical fees or opening wallet transfers
        $this->assertEquals($initialReceiptsCount, PaymentReceipt::count());
        // Tally outbox skipped when queue_tally_outbox = false
        $this->assertEquals($initialOutboxCount, OutboxMessage::count());

        // Ordinary member verification
        $ord = Member::where('membership_number', 'MC-MIG-101')->firstOrFail();
        $this->assertEquals('paid', $ord->admission_fee_status);
        $this->assertEquals(5000000, $ord->admission_fee_paise);
        $this->assertEquals('REG-2021-045', $ord->admission_fee_receipt_number);
        $this->assertEquals('paid', $ord->fee_status);
        $this->assertEquals('2027-03-31', $ord->valid_until?->toDateString());
        $this->assertEquals(500000, $ord->wallet->balance_paise);
        $this->assertNotNull(Card::where('card_number', 'CARD-BULK-101')->where('cardable_id', $ord->id)->where('status', 'active')->first());

        // Subsequent renewal on ViewMember must NOT ask for One-Time Admission Fee
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $ord->id])
            ->assertDontSee('Collect Fee Now')
            ->call('openRenewModal')
            ->assertSet('collectAdmissionFee', false);

        // Life member verification (continuous valid_until null, not marked pending!)
        $life = Member::where('membership_number', 'MC-MIG-102')->firstOrFail();
        $this->assertNull($life->valid_until);
        $this->assertEquals('paid', $life->admission_fee_status);
        $this->assertEquals('paid', $life->fee_status);
        $this->assertEquals(1250050, $life->wallet->balance_paise);

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $life->id])
            ->assertSee('Good Standing')
            ->assertSee('Continuous / Lifetime')
            ->assertDontSee('Collect Fee Now');

        // Corporate member blank valid_until defaulted to FY end
        $corp = Member::where('membership_number', 'MC-MIG-103')->firstOrFail();
        $this->assertEquals($fyEnd, $corp->valid_until?->toDateString());
        $this->assertEquals('paid', $corp->admission_fee_status);
        $this->assertEquals('paid', $corp->fee_status);

        // Opening wallet transaction + balanced opening journal (Debit 3010 / Credit 2010)
        $tx = WalletTransaction::where('reference_type', 'import_batch')
            ->where('reference_id', $batch->id)
            ->where('wallet_account_id', $life->wallet->id)
            ->firstOrFail();
        $this->assertEquals('opening_balance', $tx->type);
        $this->assertEquals(1250050, $tx->amount_paise);

        $journal = JournalHeader::where('source_type', 'opening_balance')
            ->where('source_id', $batch->id)
            ->with('lines.account')
            ->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(1750050, $journal->totalDebits());

        $recon = app(FinancialReportService::class)->getSubledgerReconciliation();
        $this->assertTrue($recon['prepaid_wallet']['reconciled']);
        $this->assertTrue($recon['journal_integrity']['balanced']);
    }

    public function test_livewire_import_commits_clean_rows_and_exports_remaining_error_rows_for_roundtrip_fix(): void
    {
        $csv = implode("\n", [
            'membership_number,first_name,last_name,phone,email,category_code,joined_at,valid_until,opening_balance_rupees',
            'MC-CLEAN-01,Vikram,Gopal,+919847001122,vikram@example.com,ORD,2024-05-01,2027-03-31,1500.00',
            'MC-001,Duplicate,Existing,+919847001123,dup@example.com,ORD,2024-05-01,2027-03-31,0',
            'MC-BADCAT-02,Meera,Nair,+919847001124,meera@example.com,GOLD,2024-05-01,2027-03-31,0',
            'MC-NEG-03,Rohan,Varma,+919847001125,rohan@example.com,ORD,2024-05-01,2027-03-31,-250.00',
            'MC-CLEAN-02,Anitha,Pillai,+919847001126,anitha@example.com,LIFE,2022-01-01,,0',
        ]);

        $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

        $component = Livewire::actingAs($this->admin)
            ->test(ImportMembers::class)
            ->set('csvFile', $file)
            ->call('runPreview')
            ->assertSet('previewResult.total_rows', 5)
            ->assertSet('previewResult.valid_count', 2)
            ->assertSet('previewResult.failed_count', 3)
            ->call('confirmImport')
            ->assertSet('completedBatchResult.imported_rows', 2)
            ->assertSet('completedBatchResult.failed_rows', 3);

        // 2 clean rows imported; bad rows skipped
        $this->assertNotNull(Member::where('membership_number', 'MC-CLEAN-01')->first());
        $this->assertNotNull(Member::where('membership_number', 'MC-CLEAN-02')->first());
        $this->assertNull(Member::where('membership_number', 'MC-BADCAT-02')->first());
        $this->assertNull(Member::where('membership_number', 'MC-NEG-03')->first());

        $batchNumber = $component->get('completedBatchResult.batch_number');
        /** @var ImportBatch $batch */
        $batch = ImportBatch::where('batch_number', $batchNumber)->firstOrFail();
        $this->assertEquals(2, $batch->imported_rows);
        $this->assertEquals(3, $batch->failed_rows);

        // Guard against double-committing an already completed batch
        $this->expectException(InvalidArgumentException::class);
        $this->memberService->executeImport(
            $component->get('completedBatchResult')['execution_summary'] ?? [],
            $this->admin->id,
            ['import_batch_id' => $batch->id]
        );
    }

    public function test_remaining_error_rows_csv_can_be_fixed_and_reuploaded_directly(): void
    {
        $badCsv = implode("\n", [
            'membership_number,first_name,last_name,phone,email,category_code,joined_at,opening_balance_rupees',
            'MC-FIX-01,Manoj,Babu,+919847009900,manoj@example.com,UNKNOWN,2025-02-01,-100',
        ]);

        $preview = $this->memberService->previewCsvImport($badCsv);
        $this->assertEquals(1, $preview['failed_count']);

        $errorCsv = $this->memberService->buildErrorRowsCsv($preview['errors'], $preview['headers']);
        $this->assertStringContainsString('import_error_reason', $errorCsv);
        $this->assertStringContainsString('MC-FIX-01', $errorCsv);

        // Simulate fixing the error fields in Excel while keeping the extra import_error_reason column
        $fixedCsv = str_replace(',UNKNOWN,2025-02-01,-100,', ',ORD,2025-02-01,250.00,', $errorCsv);
        $repreview = $this->memberService->previewCsvImport($fixedCsv);

        $this->assertEquals(1, $repreview['valid_count']);
        $this->assertEquals(0, $repreview['failed_count']);
        $this->assertEquals(25000, $repreview['valid_rows'][0]['opening_balance_paise']);
    }

    public function test_manual_single_member_creation_supports_already_paid_existing_member_without_receipt(): void
    {
        $ord = MembershipCategory::where('code', 'ORD')->firstOrFail();
        $beforeReceipts = PaymentReceipt::count();

        Livewire::actingAs($this->admin)
            ->test(CreateMember::class)
            ->fillForm([
                'first_name' => 'Gopinath',
                'last_name' => 'Muthukad',
                'phone' => '+919847556677',
                'membership_number' => 'MC-SINGLE-PAID-01',
                'membership_category_id' => $ord->id,
                'status' => 'active',
                'joined_at' => '2020-07-10',
                'valid_until' => '2027-03-31',
                'historical_fees_already_paid' => true,
                'historical_fee_paid_at' => '2020-07-10',
                'historical_receipt_ref' => 'OLD-BOOK-18',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals($beforeReceipts, PaymentReceipt::count());

        $member = Member::where('membership_number', 'MC-SINGLE-PAID-01')->firstOrFail();
        $this->assertEquals('paid', $member->admission_fee_status);
        $this->assertEquals(5000000, $member->admission_fee_paise);
        $this->assertEquals('OLD-BOOK-18', $member->admission_fee_receipt_number);
        $this->assertEquals('paid', $member->fee_status);
        $this->assertEquals('OLD-BOOK-18', $member->last_fee_receipt_number);
    }

    public function test_rbac_blocks_cashier_from_import_page_and_export_handles_kyc_and_formula_safety(): void
    {
        $this->actingAs($this->cashier)
            ->get(MemberResourceUrlHelper::importUrl())
            ->assertForbidden();

        // Roster CSV export respects formula safety while preserving +91 phones and KYC gating
        $ord = MembershipCategory::where('code', 'ORD')->firstOrFail();
        $formulaMember = $this->memberService->registerMember(
            personData: [
                'first_name' => '=CMD()',
                'last_name' => 'Test',
                'phone' => '+919847099887',
                'id_proof_type' => 'PAN Card',
                'id_proof_number' => 'ABCDE1234F',
            ],
            categoryId: $ord->id,
            membershipNumber: 'MC-EXP-01',
            joinedAt: '2025-01-01',
            validUntil: '2027-03-31'
        );

        $responseNoKyc = $this->memberService->streamMembersCsvExport([$formulaMember], false);
        ob_start();
        $responseNoKyc->sendContent();
        $csvNoKyc = ob_get_clean();

        $this->assertStringContainsString("'=CMD()", (string) $csvNoKyc);
        $this->assertStringContainsString('+919847099887', (string) $csvNoKyc);
        $this->assertStringNotContainsString('ABCDE1234F', (string) $csvNoKyc);

        $responseWithKyc = $this->memberService->streamMembersCsvExport([$formulaMember], true);
        ob_start();
        $responseWithKyc->sendContent();
        $csvWithKyc = ob_get_clean();

        $this->assertStringContainsString('ABCDE1234F', (string) $csvWithKyc);

        // Authorized admin can load ImportMembers page and ListMembers with Bulk Import / Export actions
        $this->actingAs($this->admin)
            ->get(MemberResourceUrlHelper::importUrl())
            ->assertOk()
            ->assertSee('Import Members from CSV (Excel)')
            ->assertSee('Download Sample CSV');

        Livewire::actingAs($this->admin)
            ->test(ListMembers::class)
            ->assertActionVisible('bulkImport')
            ->assertActionVisible('exportRoster')
            ->callAction('exportRoster')
            ->assertFileDownloaded();

        Livewire::actingAs($this->admin)
            ->test(ListMembers::class)
            ->callTableBulkAction('exportSelected', [$formulaMember])
            ->assertFileDownloaded();
    }

    public function test_bulk_import_detects_duplicate_rfid_cards_respects_closed_period_and_optional_tally_queue(): void
    {
        // Duplicate RFID card within file and already in DB
        $dupCardCsv = implode("\n", [
            'membership_number,first_name,category_code,rfid_card_number,joined_at',
            'MC-CARD-01,One,ORD,CARD-UNIQ-01,2026-01-01',
            'MC-CARD-02,Two,ORD,CARD-UNIQ-01,2026-01-01',
        ]);
        $preview = $this->memberService->previewCsvImport($dupCardCsv);
        $this->assertEquals(1, $preview['valid_count']);
        $this->assertEquals(1, $preview['failed_count']);
        $this->assertStringContainsString('Duplicate rfid_card_number in CSV', $preview['errors'][0]['error']);

        // Closed period blocks opening balance journal posting
        app(PeriodClosingService::class)->closePeriod('2026-10', '2026-10-01', '2026-10-31', 'Oct locked', $this->admin);
        $walletCsv = implode("\n", [
            'membership_number,first_name,category_code,joined_at,opening_balance_rupees',
            'MC-PER-01,PeriodTest,ORD,2026-10-15,1000.00',
        ]);
        $walletPreview = $this->memberService->previewCsvImport($walletCsv);
        $this->assertEquals(1, $walletPreview['valid_count']);

        try {
            $this->memberService->executeImport($walletPreview['valid_rows'], $this->admin->id, [
                'opening_balance_date' => '2026-10-20',
            ]);
            $this->fail('Expected PeriodClosedException was not thrown.');
        } catch (PeriodClosedException) {
            $this->assertNull(Member::where('membership_number', 'MC-PER-01')->first());
        }

        // Open date with queue_tally_outbox = true queues an OutboxMessage
        $beforeOutbox = OutboxMessage::count();
        $this->memberService->executeImport($walletPreview['valid_rows'], $this->admin->id, [
            'opening_balance_date' => now()->toDateString(),
            'queue_tally_outbox' => true,
        ]);
        $this->assertEquals($beforeOutbox + 1, OutboxMessage::count());
    }
}

class MemberResourceUrlHelper
{
    public static function importUrl(): string
    {
        return \App\Filament\Resources\Members\MemberResource::getUrl('import');
    }
}
