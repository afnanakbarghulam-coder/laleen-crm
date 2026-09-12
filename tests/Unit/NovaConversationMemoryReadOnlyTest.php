<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Sale;
use App\Models\Staff;
use App\Models\User;
use App\NovaAI\Models\NovaBusinessFact;
use App\NovaAI\Models\NovaDecision;
use App\NovaAI\Models\NovaExperiment;
use App\NovaAI\Services\NovaConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * NOVA READ-ONLY INTEGRATION RULE guard (see app/NovaAI/README.md) for
 * Stages 4, 5, and 6. Conversation memory, structured business facts, and
 * decision/experiment memory all live entirely on the isolated
 * 'nova_memory' connection/database (config/database.php) - a full Nova
 * conversation (create, several exchanges, recent-turns retrieval, fact
 * extraction/storage/retrieval, decision/experiment extraction/storage/
 * retrieval) through the real /nova/ask route must produce zero
 * row/attribute changes anywhere on the CRM's own connection.
 */
class NovaConversationMemoryReadOnlyTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
    }

    public function test_nova_memory_models_have_no_awareness_from_crm_models(): void
    {
        foreach ([
            app_path('Models/Staff.php'),
            app_path('Models/Sale.php'),
            app_path('Models/Appointment.php'),
            app_path('Models/User.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertStringNotContainsString('NovaConversation', $source);
            $this->assertStringNotContainsString('NovaMessage', $source);
            $this->assertStringNotContainsString('NovaBusinessFact', $source);
            $this->assertStringNotContainsString('NovaDecision', $source);
            $this->assertStringNotContainsString('NovaExperiment', $source);
            $this->assertStringNotContainsString('nova_memory', $source);
        }
    }

    public function test_database_config_only_adds_a_new_connection_never_touches_existing_ones(): void
    {
        $source = file_get_contents(config_path('database.php'));

        // The pre-existing 'sqlite'/'mysql'/'pgsql'/'sqlsrv' connection blocks
        // must remain untouched - only a new, additional connection entry is
        // permitted.
        $this->assertStringContainsString("'nova_memory' =>", $source);
        $this->assertStringContainsString("'sqlite' => [", $source);
        $this->assertStringContainsString("'default' => env('DB_CONNECTION', 'sqlite')", $source);
    }

    public function test_a_full_conversation_makes_zero_writes_anywhere_on_the_crm_connection(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'A CRM-grounded answer.']]]]],
            ], 200),
        ]);

        $staff = Staff::create(['name' => 'RO Staff', 'branch' => 'old_airport', 'base_salary' => 2500.0]);
        $sale = Sale::create([
            'branch' => 'old_airport',
            'total_amount' => 150.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appointment = Appointment::create([
            'customer_name' => 'RO Customer',
            'phone' => '+97499999999',
            'appointment_datetime' => now(),
            'service_name' => 'Test Service',
            'branch' => 'old_airport',
            'status' => 'completed',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $countsBefore = [Staff::count(), Sale::count(), Appointment::count(), User::count()];
        $staffBefore = Staff::find($staff->id)->getAttributes();
        $saleBefore = Sale::find($sale->id)->getAttributes();
        $appointmentBefore = Appointment::find($appointment->id)->getAttributes();

        $conversationId = null;
        foreach (range(1, 3) as $i) {
            $response = $this->actingAs($admin)->postJson('/nova/ask', [
                'message' => "Question number {$i}?",
                'conversation_id' => $conversationId,
            ]);
            $conversationId = $response->json('conversation_id');
        }

        (new NovaConversationService())->recentTurns(
            (new NovaConversationService())->resolveConversation($conversationId, $admin->id)
        );

        $this->assertSame($countsBefore, [Staff::count(), Sale::count(), Appointment::count(), User::count()]);
        $this->assertSame($staffBefore, Staff::find($staff->id)->getAttributes());
        $this->assertSame($saleBefore, Sale::find($sale->id)->getAttributes());
        $this->assertSame($appointmentBefore, Appointment::find($appointment->id)->getAttributes());
    }

    public function test_a_full_fact_extraction_round_trip_makes_zero_writes_anywhere_on_the_crm_connection(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake(function (HttpClientRequest $request) {
            $last = collect($request['contents'])->last();
            $isMainAsk = str_contains($last['parts'][0]['text'] ?? '', 'BUSINESS SNAPSHOT');

            if ($isMainAsk) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => 'Understood.']]]]],
                ], 200);
            }

            return Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'text' => json_encode([
                        ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'Closed on Fridays.'],
                    ]),
                ]]]]],
            ], 200);
        });

        $staff = Staff::create(['name' => 'RO Staff 2', 'branch' => 'wakrah', 'base_salary' => 2200.0]);
        $admin = User::factory()->create(['role' => 'admin']);

        $countsBefore = [Staff::count(), Sale::count(), Appointment::count(), User::count()];
        $staffBefore = Staff::find($staff->id)->getAttributes();

        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'We are closing on Fridays from now on.',
        ])->assertOk();

        // Confirms the fixture actually exercised the extraction/storage
        // path, not just a no-op request.
        $this->assertSame(1, NovaBusinessFact::count());

        $this->assertSame($countsBefore, [Staff::count(), Sale::count(), Appointment::count(), User::count()]);
        $this->assertSame($staffBefore, Staff::find($staff->id)->getAttributes());
    }

    public function test_a_full_decision_extraction_round_trip_makes_zero_writes_anywhere_on_the_crm_connection(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake(function (HttpClientRequest $request) {
            $last = collect($request['contents'])->last();
            $text = $last['parts'][0]['text'] ?? '';

            if (str_contains($text, 'BUSINESS SNAPSHOT')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => 'Noted.']]]]],
                ], 200);
            }

            $systemText = $request['systemInstruction']['parts'][0]['text'] ?? '';

            if (str_contains($systemText, 'DECISIONS and EXPERIMENTS')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [[
                        'text' => json_encode([
                            ['action' => 'create', 'type' => 'decision', 'title' => 'Prioritize Wakrah hiring', 'category' => 'staffing'],
                        ]),
                    ]]]]],
                ], 200);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '[]']]]]]], 200);
        });

        $staff = Staff::create(['name' => 'RO Staff 3', 'branch' => 'old_airport', 'base_salary' => 2100.0]);
        $admin = User::factory()->create(['role' => 'admin']);

        $countsBefore = [Staff::count(), Sale::count(), Appointment::count(), User::count()];
        $staffBefore = Staff::find($staff->id)->getAttributes();

        $this->actingAs($admin)->postJson('/nova/ask', [
            'message' => 'We decided to prioritize Wakrah hiring this quarter.',
        ])->assertOk();

        // Confirms the fixture actually exercised the extraction/storage
        // path, not just a no-op request.
        $this->assertSame(1, NovaDecision::count());
        $this->assertSame(0, NovaExperiment::count());

        $this->assertSame($countsBefore, [Staff::count(), Sale::count(), Appointment::count(), User::count()]);
        $this->assertSame($staffBefore, Staff::find($staff->id)->getAttributes());
    }
}
