<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Integrations\Glpi\GlpiApiClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DryRunGlpiApiTest extends TestCase
{
    public function test_dry_run_skips_glpi_write(): void
    {
        Config::set('glpi-ai.dry_run', true);
        Config::set('glpi-ai.auto_assign', true);

        $result = app(GlpiApiClient::class)->assignTechnician(123, 456, 'teste');

        $this->assertTrue($result['skipped']);
        $this->assertTrue($result['dry_run']);
    }
}
