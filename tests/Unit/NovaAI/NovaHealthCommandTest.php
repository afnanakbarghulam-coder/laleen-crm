<?php

namespace Tests\Unit\NovaAI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Stage 8: `php artisan nova:health` is a read-only, secret-free diagnostic
 * - these tests exist mainly to prove it never leaks the configured Gemini
 * key and never fails the whole command just because it can't reach
 * Gemini (it must never call Gemini at all).
 */
class NovaHealthCommandTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
    }

    public function test_health_command_succeeds_when_everything_is_configured(): void
    {
        config(['services.gemini.key' => 'test-key-value-must-not-appear-below']);

        $this->artisan('nova:health')
            ->assertExitCode(0)
            ->expectsOutputToContain('SET')
            ->doesntExpectOutputToContain('test-key-value-must-not-appear-below');
    }

    public function test_health_command_reports_not_set_without_leaking_anything_when_key_is_missing(): void
    {
        config(['services.gemini.key' => null]);

        $this->artisan('nova:health')
            ->assertExitCode(1)
            ->expectsOutputToContain('NOT SET');
    }

    public function test_health_command_never_makes_an_http_request(): void
    {
        \Illuminate\Support\Facades\Http::fake();

        $this->artisan('nova:health');

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
