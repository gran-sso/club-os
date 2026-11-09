<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Integrations\Adapters\TallyPrimeHttpConnector;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallyXmlSerializer;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealTallyAndPwaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement 5: Real Tally connector code handles XML delivery, parses <CREATED> response,
     * parses <LINEERROR> into actionable failures, and handles connection timeouts.
     */
    public function test_real_tally_connector_successful_voucher_export(): void
    {
        Http::fake([
            'http://127.0.0.1:9000*' => Http::response(
                <<<XML
                <RESPONSE>
                    <CREATED>1</CREATED>
                    <ALTERED>0</ALTERED>
                    <DELETED>0</DELETED>
                    <LASTVCHID>10042</LASTVCHID>
                </RESPONSE>
                XML,
                200
            ),
        ]);

        (new ProductionBootstrapSeeder())->run();
        $journalService = app(JournalService::class);

        $header = $journalService->post(
            sourceType: 'test_voucher',
            sourceId: 101,
            description: 'Prepaid top-up for M-001',
            lines: [
                ['account_code' => '1020', 'debit_paise' => 50000, 'narration' => 'Bank deposit', 'department_code' => 'membership'],
                ['account_code' => '2010', 'credit_paise' => 50000, 'narration' => 'Prepaid liability', 'department_code' => 'membership'],
            ]
        );

        $connector = new TallyPrimeHttpConnector(
            serializer: app(TallyXmlSerializer::class),
            endpointUrl: 'http://127.0.0.1:9000',
            timeoutSeconds: 5
        );

        $result = $connector->sendVouchers([$header->id]);

        $this->assertEquals('acknowledged', $result['status']);
        $this->assertEquals(1, $result['vouchers_delivered']);
        $this->assertEquals(1, $result['tally_created_count']);

        $outbox = OutboxMessage::where('aggregate_type', 'JournalHeader')
            ->where('aggregate_id', $header->id)
            ->first();

        $this->assertNotNull($outbox);
        $this->assertEquals('acknowledged', $outbox->status);
        $this->assertNotNull($outbox->delivered_at);
    }

    public function test_real_tally_connector_line_error_marks_rejected(): void
    {
        Http::fake([
            'http://127.0.0.1:9000*' => Http::response(
                <<<XML
                <RESPONSE>
                    <CREATED>0</CREATED>
                    <ERRORS>1</ERRORS>
                    <LINEERROR>Ledger 'Unknown Liquor Account' does not exist in Tally Company.</LINEERROR>
                </RESPONSE>
                XML,
                200
            ),
        ]);

        (new ProductionBootstrapSeeder())->run();
        $journalService = app(JournalService::class);

        $header = $journalService->post(
            sourceType: 'test_voucher_reject',
            sourceId: 102,
            description: 'Faulty mapping test',
            lines: [
                ['account_code' => '1020', 'debit_paise' => 10000, 'narration' => 'Bank', 'department_code' => 'bar'],
                ['account_code' => '2010', 'credit_paise' => 10000, 'narration' => 'Liability', 'department_code' => 'bar'],
            ]
        );

        $connector = new TallyPrimeHttpConnector(
            serializer: app(TallyXmlSerializer::class),
            endpointUrl: 'http://127.0.0.1:9000',
            timeoutSeconds: 5
        );

        $result = $connector->sendVouchers([$header->id]);

        $this->assertEquals('rejected', $result['status']);
        $this->assertStringContainsString("Ledger 'Unknown Liquor Account' does not exist in Tally Company.", $result['error']);
    }

    public function test_real_tally_connector_timeout_throws_retryable_exception(): void
    {
        Http::fake([
            'http://127.0.0.1:9000*' => function () {
                throw new ConnectionException("Connection timed out after 5000 milliseconds");
            },
        ]);

        (new ProductionBootstrapSeeder())->run();
        $journalService = app(JournalService::class);

        $header = $journalService->post(
            sourceType: 'test_voucher_timeout',
            sourceId: 103,
            description: 'Timeout test',
            lines: [
                ['account_code' => '1020', 'debit_paise' => 10000, 'narration' => 'Bank', 'department_code' => 'bar'],
                ['account_code' => '2010', 'credit_paise' => 10000, 'narration' => 'Liability', 'department_code' => 'bar'],
            ]
        );

        $connector = new TallyPrimeHttpConnector(
            serializer: app(TallyXmlSerializer::class),
            endpointUrl: 'http://127.0.0.1:9000',
            timeoutSeconds: 5
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Tally Prime HTTP connection failed at http://127.0.0.1:9000");

        $connector->sendVouchers([$header->id]);
    }

    /**
     * Requirement 6: Basic PWA support: manifest, icons, static-only service worker caching,
     * offline fallback, no caching of authenticated financial responses, no offline mutations.
     */
    public function test_pwa_manifest_and_icons_accessible(): void
    {
        $response = $this->get('/manifest.json');
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals('Malabar Club Staff Management', $data['name']);
        $this->assertEquals('standalone', $data['display']);
        $this->assertNotEmpty($data['icons']);

        // Check icons
        $icon192 = $this->get('/icons/icon-192.svg');
        $icon192->assertStatus(200);

        $icon512 = $this->get('/icons/icon-512.svg');
        $icon512->assertStatus(200);
    }

    public function test_pwa_service_worker_prohibits_offline_mutations_and_caches_static_only(): void
    {
        $response = $this->get('/sw.js');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Must define static assets cache
        $this->assertStringContainsString('malabar-club-static', $content);
        // Must prohibit offline mutations
        $this->assertStringContainsString('Prohibit offline mutations', $content);
        $this->assertStringContainsString('Authoritative server connection required', $content);
        // Must route to offline.html
        $this->assertStringContainsString('/offline.html', $content);

        // Check offline fallback page
        $offline = $this->get('/offline.html');
        $offline->assertStatus(200);
        $offline->assertSee('Financial Integrity Rule');
        $offline->assertSee('offline spending, offline balance caching');
    }

    /**
     * Requirement 7: Secure account provisioning, separate development and production setup,
     * and guard demo seeders.
     */
    public function test_demo_seeder_throws_exception_in_production_environment(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CRITICAL SAFETY GUARD: DemoDataSeeder contains test users and mock accounts and cannot be run in production!');

        $seeder = new DemoDataSeeder();
        $seeder->run();
    }

    public function test_production_bootstrap_seeder_creates_clean_baseline_without_demo_records(): void
    {
        // Run clean production bootstrap
        $seeder = new ProductionBootstrapSeeder();
        $seeder->run();

        // Master chart of accounts exists in 'accounts' table
        $this->assertDatabaseHas('accounts', ['code' => '1010']); // Cash
        $this->assertDatabaseHas('accounts', ['code' => '1020']); // Bank
        $this->assertDatabaseHas('accounts', ['code' => '2010']); // Prepaid Member Liability
        $this->assertDatabaseHas('accounts', ['code' => '2020']); // External Food Contractor Payable
        $this->assertDatabaseHas('accounts', ['code' => '4010']); // Guest Entry & Pass Revenue

        // Facilities exist
        $this->assertDatabaseHas('facilities', ['code' => 'COURT-1']);
        $this->assertDatabaseHas('facilities', ['code' => 'ROOM-101']);

        // Production admin user exists
        $this->assertDatabaseHas('users', ['email' => 'admin@malabarclub.local']);

        // Assert NO demo members or demo test cards were created by production seeder
        $this->assertDatabaseMissing('members', ['membership_number' => 'M-DEMO-001']);
        $this->assertDatabaseMissing('cards', ['card_number' => 'DEMO-CARD-001']);
    }
}
