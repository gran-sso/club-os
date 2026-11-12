<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Integrations\Adapters\SimulatorTallyConnector;
use App\Domain\Integrations\Adapters\TallyPrimeHttpConnector;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallySyncService;
use App\Domain\Integrations\TallyXmlSerializer;
use App\Domain\Shared\Enums\AccountType;
use App\Domain\Shared\Models\SystemSetting;
use App\Filament\Pages\IntegrationStatusPage;
use App\Filament\Pages\SystemSettingsPage;
use App\Filament\Resources\JournalHeaders\Pages\ListJournalHeaders;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TallyRealWorldUpgradeTest extends TestCase
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

    public function test_tally_xml_serializer_escapes_special_chars_and_gates_cost_centres_by_default(): void
    {
        SystemSetting::set('tally_company_name', 'Malabar & Cultural Club', 'tally');
        SystemSetting::set('tally_company', 'Malabar & Cultural Club', 'tally');
        SystemSetting::set('tally_include_cost_centres', false, 'tally');

        $revenueAccount = Account::where('type', AccountType::REVENUE)->firstOrFail();
        $journal = app(JournalService::class)->post(
            sourceType: 'manual_test_xml',
            sourceId: 9901,
            description: 'Bar & Kitchen Entry മലയാളം <Batch #1>',
            lines: [
                [
                    'account_code' => '1010',
                    'debit_paise' => 125000,
                    'department_code' => 'bar',
                    'narration' => 'Cash Dr',
                ],
                [
                    'account_code' => $revenueAccount->code,
                    'credit_paise' => 125000,
                    'department_code' => 'bar',
                    'narration' => 'Sales & Service Cr <check>',
                ],
            ],
            businessDate: Carbon::parse('2026-10-20'),
            userId: $this->admin->id
        );

        $serializer = app(TallyXmlSerializer::class);

        // 1. Default serialization omits Cost Centres and produces parseable XML
        $xmlDefault = $serializer->serializeVouchers([$journal]);
        $parsedDefault = @simplexml_load_string($xmlDefault);
        $this->assertNotFalse($parsedDefault, 'Generated Tally Voucher XML must be valid XML.');
        $this->assertStringContainsString('<REPORTNAME>Vouchers</REPORTNAME>', $xmlDefault);
        $this->assertStringContainsString('<SVCURRENTCOMPANY>Malabar &amp; Cultural Club</SVCURRENTCOMPANY>', $xmlDefault);
        $this->assertStringContainsString("<GUID>malabar-club-jnl-{$journal->journal_number}</GUID>", $xmlDefault);
        $this->assertStringContainsString("<REMOTEID>malabar-club-jnl-{$journal->journal_number}</REMOTEID>", $xmlDefault);
        $this->assertEquals(
            'Bar & Kitchen Entry മലയാളം <Batch #1>',
            (string) $parsedDefault->BODY->IMPORTDATA->REQUESTDATA->TALLYMESSAGE->VOUCHER->NARRATION
        );
        $this->assertStringNotContainsString('<CATEGORYALLOCATIONS.LIST>', $xmlDefault);
        $this->assertStringNotContainsString('<COSTCENTREALLOCATIONS.LIST>', $xmlDefault);

        // 2. Opting into Cost Centres adds them ONLY to Revenue/Expense lines (not Cash Asset 1010)
        $xmlWithCostCentres = $serializer->serializeVouchers([$journal], ['include_cost_centres' => true]);
        $parsedCc = @simplexml_load_string($xmlWithCostCentres);
        $this->assertNotFalse($parsedCc);
        $this->assertSame(1, substr_count($xmlWithCostCentres, '<CATEGORYALLOCATIONS.LIST>'));
        $this->assertSame(1, substr_count($xmlWithCostCentres, '<COSTCENTREALLOCATIONS.LIST>'));
    }

    public function test_tally_sync_service_builds_valid_masters_xml_and_handles_outbox_without_duplication(): void
    {
        $syncService = app(TallySyncService::class);
        $revenueAccount = Account::where('type', AccountType::REVENUE)->firstOrFail();

        $masters = $syncService->buildMastersXmlExport();
        $parsedMasters = @simplexml_load_string($masters['xml']);
        $this->assertNotFalse($parsedMasters, 'Generated Tally Masters XML must be valid XML.');
        $this->assertGreaterThan(5, $masters['account_count']);
        $this->assertStringContainsString('<REPORTNAME>All Masters</REPORTNAME>', $masters['xml']);
        $this->assertStringContainsString('<PARENT>Cash-in-Hand</PARENT>', $masters['xml']);
        $this->assertStringContainsString('<PARENT>Bank Accounts</PARENT>', $masters['xml']);

        $journal = app(JournalService::class)->post(
            sourceType: 'manual_outbox_dedup',
            sourceId: 9902,
            description: 'Outbox deduplication check',
            lines: [
                ['account_code' => '1010', 'debit_paise' => 50000, 'department_code' => 'admin'],
                ['account_code' => $revenueAccount->code, 'credit_paise' => 50000, 'department_code' => 'admin'],
            ],
            businessDate: Carbon::parse('2026-10-21'),
            userId: $this->admin->id
        );

        // JournalService::post already created one 'finance.journal.posted' outbox message
        $this->assertEquals(
            1,
            OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $journal->id)
                ->count()
        );

        // Calling queueVoucherSync must reuse the existing outbox row rather than duplicating it
        $syncService->queueVoucherSync($journal);
        $this->assertEquals(
            1,
            OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $journal->id)
                ->count()
        );

        // Simulate failure then requeue then process cleanly
        $outbox = OutboxMessage::where('aggregate_type', 'JournalHeader')
            ->where('aggregate_id', $journal->id)
            ->firstOrFail();
        $outbox->update([
            'status' => 'failed',
            'last_error' => 'Simulated temporary Tally LAN disconnect',
        ]);

        $statsBefore = $syncService->getOutboxStats();
        $this->assertEquals(1, $statsBefore['failed']);

        $requeued = $syncService->requeueFailedOutbox();
        $this->assertEquals(1, $requeued);

        $report = $syncService->processPendingOutbox(app(SimulatorTallyConnector::class));
        $this->assertEquals(1, $report['processed']);
        $this->assertEquals(0, $report['failed']);

        $outbox->refresh();
        $this->assertEquals('acknowledged', $outbox->status);
    }

    public function test_tally_prime_http_connector_validates_active_company_altered_count_and_multiple_errors(): void
    {
        Http::fake([
            '10.10.20.1:9000' => Http::sequence()
                ->push('<RESPONSE><COMPANYNAME>Different Club Pvt Ltd</COMPANYNAME></RESPONSE>', 200)
                ->push('<RESPONSE><COMPANY NAME="Malabar Club"></COMPANY></RESPONSE>', 200),
            '10.10.20.2:9000' => Http::sequence()
                ->push('<RESPONSE><CREATED>0</CREATED><ALTERED>0</ALTERED><ERRORS>2</ERRORS><LINEERROR>Ledger Missing</LINEERROR><EXCEPTION>Voucher Date Outside Period</EXCEPTION></RESPONSE>', 200)
                ->push('<RESPONSE><CREATED>0</CREATED><ALTERED>1</ALTERED><ERRORS>0</ERRORS></RESPONSE>', 200),
        ]);

        $mismatchProbe = TallyPrimeHttpConnector::testConnection('10.10.20.1', 9000, 3, 'Malabar Club');
        $this->assertTrue($mismatchProbe['success']);
        $this->assertFalse($mismatchProbe['company_matched']);
        $this->assertStringContainsString('was not listed among open companies', $mismatchProbe['message']);

        $matchedProbe = TallyPrimeHttpConnector::testConnection('10.10.20.1', 9000, 3, 'Malabar Club');
        $this->assertTrue($matchedProbe['success']);
        $this->assertTrue($matchedProbe['company_matched']);
        $this->assertStringContainsString('is currently loaded', $matchedProbe['message']);

        // Configure connector to hit faked LAN endpoint
        SystemSetting::set('tally_host', '10.10.20.2', 'tally');
        SystemSetting::set('tally_port', 9000, 'tally');
        SystemSetting::set('tally_driver', 'live', 'tally');
        SystemSetting::set('tally_company_name', 'Malabar Club', 'tally');

        $revenueAccount = Account::where('type', AccountType::REVENUE)->firstOrFail();
        $journal = app(JournalService::class)->post(
            sourceType: 'manual_http_altered',
            sourceId: 9903,
            description: 'Tally HTTP Altered & LineError check',
            lines: [
                ['account_code' => '1010', 'debit_paise' => 75000],
                ['account_code' => $revenueAccount->code, 'credit_paise' => 75000],
            ],
            businessDate: Carbon::parse('2026-10-22'),
            userId: $this->admin->id
        );

        $syncService = app(TallySyncService::class);
        $connector = app(TallyPrimeHttpConnector::class);

        $failedReport = $syncService->processPendingOutbox($connector);
        $this->assertEquals(0, $failedReport['processed']);
        $this->assertEquals(1, $failedReport['failed']);
        $this->assertStringContainsString('Ledger Missing', $failedReport['errors'][0]);
        $this->assertStringContainsString('Voucher Date Outside Period', $failedReport['errors'][0]);

        $outbox = OutboxMessage::where('aggregate_type', 'JournalHeader')
            ->where('aggregate_id', $journal->id)
            ->firstOrFail();
        $this->assertEquals('failed', $outbox->status);

        $syncService->requeueFailedOutbox();

        $alteredReport = $syncService->processPendingOutbox($connector);
        $this->assertEquals(1, $alteredReport['processed']);
        $this->assertEquals(0, $alteredReport['failed']);

        $outbox->refresh();
        $this->assertEquals('acknowledged', $outbox->status);
    }

    public function test_system_settings_integration_status_and_journal_headers_xml_exports_and_rbac(): void
    {
        // 1. SystemSettingsPage sanitizes host and syncs both tally_company and tally_company_name + tally_url
        Livewire::actingAs($this->admin)
            ->test(SystemSettingsPage::class)
            ->set('tallyDriver', 'live')
            ->set('tallyHost', 'http://192.168.1.75:9000/')
            ->set('tallyPort', 9001)
            ->set('tallyCompany', 'Malabar Club Calicut')
            ->set('tallyIncludeCostCentres', true)
            ->set('tallyAutoSync', true)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals('192.168.1.75', SystemSetting::get('tally_host'));
        $this->assertEquals('http://192.168.1.75:9001', SystemSetting::get('tally_url'));
        $this->assertEquals('Malabar Club Calicut', SystemSetting::get('tally_company'));
        $this->assertEquals('Malabar Club Calicut', SystemSetting::get('tally_company_name'));
        $this->assertTrue(SystemSetting::getBool('tally_include_cost_centres'));
        $this->assertTrue(SystemSetting::getBool('tally_auto_sync'));

        // Reset to simulator so tests don't make external calls accidentally
        SystemSetting::set('tally_driver', 'simulator', 'tally');

        // 2. Post journals on different business dates
        $revenueAccount = Account::where('type', AccountType::REVENUE)->firstOrFail();
        $j1 = app(JournalService::class)->post(
            sourceType: 'manual_export_1',
            sourceId: 9904,
            description: 'October Early Entry',
            lines: [
                ['account_code' => '1010', 'debit_paise' => 20000],
                ['account_code' => $revenueAccount->code, 'credit_paise' => 20000],
            ],
            businessDate: Carbon::parse('2026-10-05'),
            userId: $this->admin->id
        );
        $j2 = app(JournalService::class)->post(
            sourceType: 'manual_export_2',
            sourceId: 9905,
            description: 'October Mid Entry',
            lines: [
                ['account_code' => '1010', 'debit_paise' => 30000],
                ['account_code' => $revenueAccount->code, 'credit_paise' => 30000],
            ],
            businessDate: Carbon::parse('2026-10-15'),
            userId: $this->admin->id
        );

        // 3. IntegrationStatusPage Masters & Pending XML downloads
        Livewire::actingAs($this->admin)
            ->test(IntegrationStatusPage::class)
            ->call('downloadMastersXml')
            ->assertFileDownloaded();

        Livewire::actingAs($this->admin)
            ->test(IntegrationStatusPage::class)
            ->set('markExportedSynced', false)
            ->call('downloadPendingVouchersXml')
            ->assertFileDownloaded();

        $this->assertEquals(
            'pending',
            OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $j1->id)
                ->value('status')
        );

        // Date range download with markExportedSynced = true acknowledges only matching journals
        Livewire::actingAs($this->admin)
            ->test(IntegrationStatusPage::class)
            ->set('exportFromDate', '2026-10-01')
            ->set('exportToDate', '2026-10-10')
            ->set('markExportedSynced', true)
            ->call('downloadDateRangeVouchersXml')
            ->assertFileDownloaded();

        $this->assertEquals(
            'acknowledged',
            OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $j1->id)
                ->value('status')
        );
        $this->assertEquals(
            'pending',
            OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $j2->id)
                ->value('status')
        );

        // 4. ListJournalHeaders actions & RBAC check
        Livewire::actingAs($this->admin)
            ->test(ListJournalHeaders::class)
            ->callAction('downloadTallyMastersXml')
            ->assertFileDownloaded();

        Livewire::actingAs($this->admin)
            ->test(ListJournalHeaders::class)
            ->callAction('downloadTallyVouchersXml', data: [
                'scope' => 'pending_only',
                'include_cost_centres' => false,
                'mark_as_synced' => false,
            ])
            ->assertFileDownloaded();

        // View-only staff user cannot see mark_as_synced field or use it to mark outbox synced
        $viewOnlyUser = User::factory()->create(['is_active' => true]);
        $viewOnlyUser->givePermissionTo(['journals.view', 'integrations.view']);

        Livewire::actingAs($viewOnlyUser)
            ->test(IntegrationStatusPage::class)
            ->set('markExportedSynced', true)
            ->call('downloadPendingVouchersXml')
            ->assertForbidden();

        Livewire::actingAs($viewOnlyUser)
            ->test(ListJournalHeaders::class)
            ->callAction('downloadTallyVouchersXml', data: [
                'scope' => 'pending_only',
                'include_cost_centres' => false,
                'mark_as_synced' => true,
            ]);

        // Even if mark_as_synced => true was posted, j2 must remain 'pending'
        $this->assertEquals(
            'pending',
            OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $j2->id)
                ->value('status')
        );

        // Authorized admin can also download ListJournalHeaders by explicit date range and acknowledge
        Livewire::actingAs($this->admin)
            ->test(ListJournalHeaders::class)
            ->callAction('downloadTallyVouchersXml', data: [
                'scope' => 'date_range',
                'from_date' => '2026-10-12',
                'to_date' => '2026-10-18',
                'include_cost_centres' => false,
                'mark_as_synced' => true,
            ])
            ->assertFileDownloaded();

        $this->assertEquals(
            'acknowledged',
            OutboxMessage::where('aggregate_type', 'JournalHeader')
                ->where('aggregate_id', $j2->id)
                ->value('status')
        );
    }

    public function test_tally_bridge_pull_api_security_lifecycle_and_artisan_sync_command(): void
    {
        // 1. Fail-closed when no bridge token has been configured yet
        $this->getJson('/api/integrations/tally/pending')
            ->assertStatus(401);

        $this->getJson('/api/integrations/tally/pending', ['Authorization' => 'Bearer random-token'])
            ->assertStatus(401);

        // 2. Generate one-time token (stored hashed in DB)
        $syncService = app(TallySyncService::class);
        $plainToken = $syncService->generateBridgeToken();
        $this->assertTrue($syncService->hasBridgeToken());
        $this->assertNotEquals($plainToken, SystemSetting::get('tally_bridge_token_hash'));

        $this->getJson('/api/integrations/tally/pending', ['Authorization' => 'Bearer wrong-token'])
            ->assertStatus(401);

        // Post a journal to sync via Pull-Bridge
        $revenueAccount = Account::where('type', AccountType::REVENUE)->firstOrFail();
        $journal = app(JournalService::class)->post(
            sourceType: 'manual_bridge_pull',
            sourceId: 9906,
            description: 'Hostinger Pull Bridge Voucher',
            lines: [
                ['account_code' => '1010', 'debit_paise' => 45000],
                ['account_code' => $revenueAccount->code, 'credit_paise' => 45000],
            ],
            businessDate: Carbon::parse('2026-10-25'),
            userId: $this->admin->id
        );
        $outbox = OutboxMessage::where('aggregate_type', 'JournalHeader')
            ->where('aggregate_id', $journal->id)
            ->firstOrFail();

        // 3. Valid token can download masters & pending batch
        $mastersResp = $this->getJson('/api/integrations/tally/masters', [
            'Authorization' => "Bearer {$plainToken}",
        ])->assertOk();
        $this->assertNotFalse(@simplexml_load_string((string) $mastersResp->json('xml')));

        $pullResp = $this->getJson('/api/integrations/tally/pending?limit=10', [
            'Authorization' => "Bearer {$plainToken}",
        ])->assertOk();

        $this->assertTrue((bool) $pullResp->json('has_pending'));
        $this->assertEquals(1, $pullResp->json('voucher_count'));
        $this->assertEquals([$journal->journal_number], $pullResp->json('journal_numbers'));
        $this->assertEquals([$outbox->id], $pullResp->json('outbox_ids'));
        $this->assertStringContainsString("malabar-club-jnl-{$journal->journal_number}", (string) $pullResp->json('xml'));

        // 4. Report transient local error -> retryable; report rejection -> failed -> requeue -> acknowledge
        $this->postJson('/api/integrations/tally/ack', [
            'outbox_ids' => [$outbox->id],
            'status' => 'error',
            'error_message' => 'Tally localhost:9000 busy',
        ], [
            'Authorization' => "Bearer {$plainToken}",
        ])->assertOk();

        $outbox->refresh();
        $this->assertEquals('retryable', $outbox->status);

        $this->postJson('/api/integrations/tally/ack', [
            'outbox_ids' => [$outbox->id],
            'status' => 'rejected',
            'error_message' => 'Ledger not created yet',
        ], [
            'Authorization' => "Bearer {$plainToken}",
        ])->assertOk();

        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertStringContainsString('Ledger not created yet', (string) $outbox->last_error);

        $syncService->requeueFailedOutbox();

        $this->postJson('/api/integrations/tally/ack', [
            'outbox_ids' => [$outbox->id],
            'status' => 'acknowledged',
        ], [
            'Authorization' => "Bearer {$plainToken}",
        ])->assertOk();

        $outbox->refresh();
        $this->assertEquals('acknowledged', $outbox->status);

        // 5. Revoking token immediately blocks further bridge requests
        $syncService->revokeBridgeToken();
        $this->assertFalse($syncService->hasBridgeToken());

        $this->getJson('/api/integrations/tally/pending', ['Authorization' => "Bearer {$plainToken}"])
            ->assertStatus(401);

        // 6. Artisan command `php artisan tally:sync` processes pending outbox via Simulator in non-production
        SystemSetting::set('tally_driver', 'simulator', 'tally');
        $journal2 = app(JournalService::class)->post(
            sourceType: 'manual_artisan_cmd',
            sourceId: 9907,
            description: 'Artisan Tally Sync Command Check',
            lines: [
                ['account_code' => '1010', 'debit_paise' => 15000],
                ['account_code' => $revenueAccount->code, 'credit_paise' => 15000],
            ],
            businessDate: Carbon::parse('2026-10-26'),
            userId: $this->admin->id
        );
        $outbox2 = OutboxMessage::where('aggregate_type', 'JournalHeader')
            ->where('aggregate_id', $journal2->id)
            ->firstOrFail();

        $this->artisan('tally:sync')->assertExitCode(0);
        $outbox2->refresh();
        $this->assertEquals('acknowledged', $outbox2->status);
    }
}
