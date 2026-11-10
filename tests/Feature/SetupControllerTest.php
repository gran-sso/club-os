<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_displays_token_prompt_without_token(): void
    {
        $response = $this->get('/setup-club');
        $response->assertSuccessful();
        $response->assertSee('Enter Setup Security Token');
    }

    public function test_setup_page_authenticates_with_valid_token(): void
    {
        $response = $this->get('/setup-club?token=malabar-club-setup-2026');
        $response->assertSuccessful();
        $response->assertSee('System Pre-Flight Health Check');
        $response->assertSee('Execute Setup Now');
    }

    public function test_setup_execution_rejects_invalid_token(): void
    {
        $response = $this->post('/setup-club', [
            'token' => 'invalid-token',
        ]);
        $response->assertStatus(403);
    }

    public function test_setup_execution_runs_with_valid_token(): void
    {
        $response = $this->post('/setup-club', [
            'token' => 'malabar-club-setup-2026',
            'run_migrations' => '1',
            'seeder_type' => 'none',
        ]);
        $response->assertSuccessful();
        $response->assertSee('Execution Diagnostic Log');
        $response->assertSee('Database Schema Migration');
    }
}

