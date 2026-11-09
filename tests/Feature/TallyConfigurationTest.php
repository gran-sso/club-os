<?php

namespace Tests\Feature;

use App\Domain\Integrations\Adapters\SimulatorTallyConnector;
use App\Domain\Integrations\Adapters\TallyPrimeHttpConnector;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Shared\Models\SystemSetting;
use App\Filament\Pages\IntegrationStatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TallyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
    }

    public function test_admin_can_view_tally_network_configuration_on_integration_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/integration-status');
        $response->assertStatus(200);
        $response->assertSee('Tally Prime LAN Network Settings');
        $response->assertSee('Tally Computer LAN IP / Hostname');
        $response->assertSee('Port');
        $response->assertSee('Save Configuration');
        $response->assertSee('Test Tally Connection');
    }

    public function test_admin_can_save_tally_lan_settings_in_admin_panel(): void
    {
        Livewire::actingAs($this->admin)
            ->test(IntegrationStatusPage::class)
            ->set('tallyHost', '192.168.1.120')
            ->set('tallyPort', '9001')
            ->set('tallyCompanyName', 'Malabar Club Prime')
            ->set('tallyDriver', 'live')
            ->call('saveTallySettings')
            ->assertHasNoErrors();

        $this->assertEquals('192.168.1.120', SystemSetting::get('tally_host'));
        $this->assertEquals(9001, (int) SystemSetting::get('tally_port'));
        $this->assertEquals('http://192.168.1.120:9001', SystemSetting::get('tally_url'));
        $this->assertEquals('Malabar Club Prime', SystemSetting::get('tally_company_name'));
        $this->assertEquals('live', SystemSetting::get('tally_driver'));

        // Connector should now dynamically resolve this endpoint
        $connector = app(TallyPrimeHttpConnector::class);
        $this->assertEquals('http://192.168.1.120:9001', $connector->getEndpointUrl());

        // Container resolution should resolve the live connector
        $resolved = app(AccountingConnectorInterface::class);
        $this->assertInstanceOf(TallyPrimeHttpConnector::class, $resolved);
    }

    public function test_test_tally_connection_reports_success_on_live_response(): void
    {
        Http::fake([
            'http://192.168.1.50:9000*' => Http::response(
                '<ENVELOPE><HEADER><STATUS>1</STATUS></HEADER><BODY><DATA>OK</DATA></BODY></ENVELOPE>',
                200
            ),
        ]);

        $result = TallyPrimeHttpConnector::testConnection('192.168.1.50', 9000);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Successfully connected', $result['message']);
        $this->assertEquals('http://192.168.1.50:9000', $result['endpoint']);
    }

    public function test_test_tally_connection_reports_failure_on_timeout_or_offline(): void
    {
        Http::fake([
            'http://192.168.1.99:9000*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException("Connection refused");
            },
        ]);

        $result = TallyPrimeHttpConnector::testConnection('192.168.1.99', 9000);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection failed', $result['message']);
        $this->assertStringContainsString('Ensure Tally Prime is running', $result['message']);
    }

    public function test_switching_driver_back_to_simulator_restores_safe_simulation(): void
    {
        SystemSetting::set('tally_driver', 'simulator');

        $resolved = app(AccountingConnectorInterface::class);
        $this->assertInstanceOf(SimulatorTallyConnector::class, $resolved);
    }
}

