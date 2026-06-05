<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RecommendedAction;
use App\Enums\SuggestionStatus;
use App\Integrations\Glpi\GlpiApiClient;
use App\Jobs\AssignGlpiTicketJob;
use App\Models\GlpiAiAssignmentSuggestion;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssignGlpiTicketJobStatusTest extends TestCase
{
    public function test_human_assignment_keeps_accepted_status(): void
    {
        $suggestion = $this->suggestion();

        $this->fakeGlpiApi();

        (new AssignGlpiTicketJob($suggestion, automatic: false))->handle(app(GlpiApiClient::class));

        $this->assertSame(SuggestionStatus::Accepted->value, $suggestion->status);
        $this->assertSame('human_assignment_sent_to_glpi', $suggestion->action_taken);
    }

    public function test_automatic_assignment_uses_auto_assigned_status(): void
    {
        $suggestion = $this->suggestion();

        $this->fakeGlpiApi();

        (new AssignGlpiTicketJob($suggestion, automatic: true))->handle(app(GlpiApiClient::class));

        $this->assertSame(SuggestionStatus::AutoAssigned->value, $suggestion->status);
        $this->assertSame('auto_assigned', $suggestion->action_taken);
    }

    private function suggestion(): InMemorySuggestion
    {
        return new InMemorySuggestion([
            'glpi_ticket_id' => 123,
            'title' => 'Chamado de teste',
            'recommended_action' => RecommendedAction::AssignToTechnician->value,
            'recommended_technician_id' => 456,
            'recommended_group_id' => 24,
            'confidence' => 90,
            'risk_level' => 'low',
            'status' => SuggestionStatus::Accepted->value,
        ]);
    }

    private function fakeGlpiApi(): void
    {
        Config::set('glpi-ai.dry_run', false);
        Config::set('glpi-ai.auto_assign', true);
        Config::set('glpi-ai.glpi_api.base_url', 'https://glpi.test/apirest.php');
        Config::set('glpi-ai.glpi_api.app_token', 'app-token');
        Config::set('glpi-ai.glpi_api.user_token', 'user-token');
        Config::set('glpi-ai.glpi_api.verify_ssl', false);

        Http::fake([
            'glpi.test/apirest.php/initSession' => Http::response(['session_token' => 'session-token']),
            'glpi.test/apirest.php/changeActiveEntities' => Http::response(['ok' => true]),
            'glpi.test/apirest.php/Ticket_User' => Http::response(['id' => 999], 201),
            'glpi.test/apirest.php/killSession' => Http::response(['ok' => true]),
        ]);
    }
}

class InMemorySuggestion extends GlpiAiAssignmentSuggestion
{
    public $exists = true;

    public function refresh(): static
    {
        return $this;
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        $this->fill($attributes);

        return true;
    }
}
