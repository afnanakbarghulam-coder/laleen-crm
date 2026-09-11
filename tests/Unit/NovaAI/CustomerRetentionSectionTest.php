<?php

namespace Tests\Unit\NovaAI;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Service;
use App\NovaAI\Services\NovaAIService;
use App\Support\ClientMaintenancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 3E: the customer & retention intelligence snapshot section. Nova
 * reads Appointment/Sale directly and consumes the CRM's existing,
 * unmodified ClientMaintenancePlanner for maintenance/rebooking data - so
 * the maintenance-related assertions are parity checks against a live
 * ClientMaintenancePlanner instance rather than hand-derived numbers.
 */
class CustomerRetentionSectionTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_URL_PATTERN = 'generativelanguage.googleapis.com/*';
    private const FROZEN_NOW = '2026-06-15 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-gemini-key']);
        config(['services.gemini.model' => 'gemini-3.5-flash-lite']);

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeCustomer(string $name, string $phone): Customer
    {
        return Customer::create(['name' => $name, 'phone' => $phone]);
    }

    private function makeAppointment(int $customerId, string $branch, string $status, Carbon $when): Appointment
    {
        return Appointment::create([
            'customer_name' => 'Test Customer',
            'phone' => '+97400000000',
            'customer_id' => $customerId,
            'appointment_datetime' => $when,
            'service_name' => 'Test Service',
            'branch' => $branch,
            'status' => $status,
        ]);
    }

    private function makeSale(int $customerId, float $amount): Sale
    {
        return Sale::create([
            'customer_id' => $customerId,
            'branch' => 'old_airport',
            'services_total' => $amount,
            'products_total' => 0,
            'total_amount' => $amount,
        ]);
    }

    private function makeService(string $name, int $intervalDays): Service
    {
        return Service::create(['name' => $name, 'price' => 100, 'duration' => 60, 'rebooking_interval_days' => $intervalDays]);
    }

    private function makeAppointmentService(int $appointmentId, int $serviceId, string $name, Carbon $when): AppointmentService
    {
        return AppointmentService::create([
            'appointment_id' => $appointmentId,
            'service_id' => $serviceId,
            'name' => $name,
            'price' => 100,
            'duration' => 60,
            'start_time' => $when,
        ]);
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            self::GEMINI_URL_PATTERN => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);
    }

    private function userTurnText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['contents'][0]['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    private function systemInstructionText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['systemInstruction']['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    /** Isolates just the CUSTOMER & RETENTION INTELLIGENCE section from the full snapshot text. */
    private function retentionSection(string $text): string
    {
        $section = substr($text, strpos($text, 'CUSTOMER & RETENTION INTELLIGENCE'));
        $nextBreak = strpos($section, "\n\n");

        return $nextBreak !== false ? substr($section, 0, $nextBreak) : $section;
    }

    public function test_cancelled_and_no_show_appointments_do_not_count_as_visits(): void
    {
        $customer = $this->makeCustomer('Cancelled Visit Customer', '+97411111111');
        $this->makeAppointment($customer->id, 'old_airport', 'cancelled', now()->subDays(5));
        $this->makeAppointment($customer->id, 'old_airport', 'no_show', now()->subDays(3));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $section = $this->retentionSection($this->userTurnText());

        $this->assertStringContainsString('No customers with a qualifying', $section);
    }

    public function test_future_appointments_do_not_count_as_visits(): void
    {
        $customer = $this->makeCustomer('Future Visit Customer', '+97411111112');
        $this->makeAppointment($customer->id, 'old_airport', 'pending', now()->addDays(3));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $section = $this->retentionSection($this->userTurnText());

        $this->assertStringContainsString('No customers with a qualifying', $section);
    }

    public function test_repeat_customer_requires_two_qualifying_visits(): void
    {
        $oneVisit = $this->makeCustomer('One Visit Customer', '+97411111113');
        $this->makeAppointment($oneVisit->id, 'old_airport', 'completed', now()->subDays(10));

        $twoVisits = $this->makeCustomer('Two Visit Customer', '+97411111114');
        $this->makeAppointment($twoVisits->id, 'old_airport', 'completed', now()->subDays(20));
        $this->makeAppointment($twoVisits->id, 'old_airport', 'arrived', now()->subDays(5));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $section = $this->retentionSection($this->userTurnText());

        $this->assertStringContainsString('Customers with completed visit history: 2', $section);
        $this->assertStringContainsString('Repeat customers (2+ qualifying visits): 1 (50%', $section);
    }

    public function test_historical_spend_matches_sale_totals_for_customers(): void
    {
        $customer = $this->makeCustomer('Big Spender', '+97411111115');
        $this->makeAppointment($customer->id, 'old_airport', 'completed', now()->subDays(10));
        $this->makeSale($customer->id, 300.0);
        $this->makeSale($customer->id, 200.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $section = $this->retentionSection($this->userTurnText());

        $this->assertStringContainsString('500.00 QAR total across 1 customers with a sale, average 500.00 QAR/customer', $section);
    }

    public function test_maintenance_counts_match_client_maintenance_planner_exactly(): void
    {
        $overdueCustomer = $this->makeCustomer('Overdue Customer', '+97411111116');
        $dueSoonCustomer = $this->makeCustomer('Due Soon Customer', '+97411111117');

        $colorService = $this->makeService('Hair Color', 30);

        $overdueAppt = $this->makeAppointment($overdueCustomer->id, 'old_airport', 'completed', now()->subDays(60));
        $this->makeAppointmentService($overdueAppt->id, $colorService->id, 'Hair Color', now()->subDays(60));

        $dueSoonAppt = $this->makeAppointment($dueSoonCustomer->id, 'wakrah', 'completed', now()->subDays(28));
        $this->makeAppointmentService($dueSoonAppt->id, $colorService->id, 'Hair Color', now()->subDays(28));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $text = $this->userTurnText();
        $section = $this->retentionSection($text);

        // Build the expected counts FROM a live ClientMaintenancePlanner
        // instance - a parity check, not a hand-derived formula.
        $schedule = (new ClientMaintenancePlanner())->buildSchedule();
        $expectedOverdue = $schedule->where('urgency', 'overdue')->pluck('customer_id')->unique()->count();
        $expectedDueSoon = $schedule->where('urgency', 'due_soon')->pluck('customer_id')->unique()->count();
        $expectedUpcoming = $schedule->where('urgency', 'upcoming')->pluck('customer_id')->unique()->count();

        $this->assertStringContainsString(
            sprintf('%d overdue | %d due soon | %d upcoming', $expectedOverdue, $expectedDueSoon, $expectedUpcoming),
            $section
        );
        $this->assertStringContainsString('Hair Color:', $text);
    }

    public function test_branch_distribution_classifies_by_majority_with_two_visit_minimum(): void
    {
        // Primarily Old Airport: 3 visits there, 1 at Wakrah.
        $mostlyOldAirport = $this->makeCustomer('Mostly Old Airport', '+97411111118');
        $this->makeAppointment($mostlyOldAirport->id, 'old_airport', 'completed', now()->subDays(30));
        $this->makeAppointment($mostlyOldAirport->id, 'old_airport', 'completed', now()->subDays(20));
        $this->makeAppointment($mostlyOldAirport->id, 'old_airport', 'completed', now()->subDays(10));
        $this->makeAppointment($mostlyOldAirport->id, 'wakrah', 'completed', now()->subDays(5));

        // Mixed: exactly 1 visit each branch, no majority.
        $mixedCustomer = $this->makeCustomer('Mixed Branches', '+97411111119');
        $this->makeAppointment($mixedCustomer->id, 'old_airport', 'completed', now()->subDays(15));
        $this->makeAppointment($mixedCustomer->id, 'wakrah', 'completed', now()->subDays(8));

        // Insufficient history: only 1 visit ever.
        $singleVisit = $this->makeCustomer('Single Visit', '+97411111120');
        $this->makeAppointment($singleVisit->id, 'old_airport', 'completed', now()->subDays(2));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $section = $this->retentionSection($this->userTurnText());

        $this->assertStringContainsString(
            '1 primarily Old Airport, 0 primarily Al Wakrah, 0 primarily Home Service, 1 mixed, 1 insufficient history',
            $section
        );
    }

    public function test_no_pii_appears_in_the_customer_retention_section(): void
    {
        $customer = $this->makeCustomer('Sensitive Name Example', '+97455566677');
        $this->makeAppointment($customer->id, 'old_airport', 'completed', now()->subDays(10));
        $this->makeSale($customer->id, 150.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $section = $this->retentionSection($this->userTurnText());

        $this->assertStringNotContainsString('Sensitive Name Example', $section);
        $this->assertStringNotContainsString('+97455566677', $section);
    }

    public function test_customer_retention_data_is_in_the_user_turn_not_the_system_instruction(): void
    {
        $customer = $this->makeCustomer('Placement Check Customer', '+97411111121');
        $this->makeAppointment($customer->id, 'old_airport', 'completed', now()->subDays(10));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $systemText = $this->systemInstructionText();

        $this->assertStringNotContainsString('CUSTOMER & RETENTION INTELLIGENCE', $systemText);
        $this->assertStringNotContainsString('Placement Check Customer', $systemText);
    }

    public function test_system_instruction_carries_the_retention_evidence_caveats(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $systemText = $this->systemInstructionText();
        $normalized = preg_replace('/\s+/', ' ', $systemText);

        $this->assertStringContainsStringIgnoringCase('Nova-defined business rule', $normalized);
        $this->assertStringContainsStringIgnoringCase('never the same thing as customer profitability', $normalized);
    }

    public function test_zero_customers_reports_plainly_without_fabricating_metrics(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How is customer retention looking?');

        $section = $this->retentionSection($this->userTurnText());

        $this->assertStringContainsString('No customers with a qualifying', $section);
        $this->assertStringNotContainsString('0%', $section);
    }
}
