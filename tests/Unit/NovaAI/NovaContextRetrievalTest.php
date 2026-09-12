<?php

namespace Tests\Unit\NovaAI;

use App\Models\AgentShiftLog;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\User;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Stage 7B: NovaAIService::buildSnapshot()'s null/[]/array domain
 * contract. These are direct unit tests against the private method via
 * reflection (the same style already used by the Stage 7A audit's own
 * measurement pass) - no Gemini call involved, so no Http::fake() is
 * needed here at all.
 */
class NovaContextRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private const FROZEN_NOW = '2026-06-15 12:00:00';

    private const ORIGINAL_SECTION_ORDER = [
        'revenueSection', 'appointmentFunnelSection', 'branchFinancialSection',
        'staffPerformanceSection', 'staffTargetSection', 'staffPayrollSection',
        'bookingAgentPerformanceSection', 'marketingLeadSection', 'serviceVolumeSection',
        'customerRetentionSection', 'pendingPackagesSection',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buildSnapshot(NovaAIService $service, ?array $domains = null, bool $passArgument = true): string
    {
        $method = (new ReflectionClass($service))->getMethod('buildSnapshot');
        $method->setAccessible(true);

        return $passArgument ? $method->invoke($service, $domains) : $method->invoke($service);
    }

    private function invokeSection(NovaAIService $service, string $method): string
    {
        $ref = new ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invoke($service);
    }

    /* ---------------- Test 19: full snapshot parity ---------------- */

    public function test_null_domains_is_byte_identical_to_manually_replicated_pre_stage_7_snapshot(): void
    {
        $service = new NovaAIService();

        $expectedSections = array_map(
            fn (string $method) => $this->invokeSection($service, $method),
            self::ORIGINAL_SECTION_ORDER
        );
        $expected = implode("\n\n", array_filter($expectedSections));

        $this->assertSame($expected, $this->buildSnapshot($service, null));
        // Omitting the argument entirely (every pre-Stage-7 call site) must
        // resolve to the exact same default.
        $this->assertSame($expected, $this->buildSnapshot($service, null, passArgument: false));
    }

    /* ---------------- Test 11: [] builds nothing, queries nothing ---------------- */

    public function test_empty_domains_returns_the_notice_and_issues_zero_queries(): void
    {
        $service = new NovaAIService();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $result = $this->buildSnapshot($service, []);

        $this->assertSame('No CRM domain was required for this request.', $result);
        $this->assertSame(0, $queryCount, 'buildSnapshot([]) must not issue any CRM query at all.');
    }

    /* ---------------- Test 20: selected-domain execution, proven by query isolation ---------------- */

    public function test_finance_only_excludes_every_other_section_and_their_queries(): void
    {
        $service = new NovaAIService();

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $body = $this->buildSnapshot($service, ['finance']);

        $this->assertStringContainsString('REVENUE', $body);
        $this->assertStringContainsString('BRANCH FINANCIAL PERFORMANCE', $body);

        foreach ([
            'STAFF PERFORMANCE (last', 'STAFF TARGET PERFORMANCE', 'STAFF PAYROLL COST',
            'BOOKING AGENT PERFORMANCE', 'MARKETING & LEAD INTELLIGENCE',
            'CUSTOMER & RETENTION INTELLIGENCE', 'UNREDEEMED COMBO PACKAGE',
            'SERVICE VOLUME (last', 'APPOINTMENT FUNNEL',
        ] as $excludedHeader) {
            $this->assertStringNotContainsString($excludedHeader, $body);
        }

        $sql = implode(' | ', $queries);

        foreach (['staff', 'client_packages', 'agent_shift_logs', 'leads', 'ad_lead_entries', 'appointments'] as $unrelatedTable) {
            $this->assertStringNotContainsString($unrelatedTable, $sql, "finance-only must never query {$unrelatedTable}");
        }
    }

    public function test_payroll_only_excludes_finance_and_customer_and_marketing_queries(): void
    {
        $service = new NovaAIService();
        Staff::create(['name' => 'Query Isolation Staffer', 'branch' => 'old_airport', 'base_salary' => 3000]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $body = $this->buildSnapshot($service, ['payroll']);

        $this->assertStringContainsString('STAFF PAYROLL COST', $body);

        foreach ([
            'REVENUE', 'BRANCH FINANCIAL PERFORMANCE', 'STAFF PERFORMANCE (last',
            'STAFF TARGET PERFORMANCE', 'BOOKING AGENT PERFORMANCE',
            'MARKETING & LEAD INTELLIGENCE', 'CUSTOMER & RETENTION INTELLIGENCE',
            'UNREDEEMED COMBO PACKAGE', 'SERVICE VOLUME (last', 'APPOINTMENT FUNNEL',
        ] as $excludedHeader) {
            $this->assertStringNotContainsString($excludedHeader, $body);
        }

        $sql = implode(' | ', $queries);

        foreach (['sales', 'expenses', 'appointments', 'client_packages', 'agent_shift_logs', 'leads', 'ad_lead_entries'] as $unrelatedTable) {
            $this->assertStringNotContainsString($unrelatedTable, $sql, "payroll-only must never query {$unrelatedTable}");
        }

        $this->assertStringContainsString('staff', $sql, 'payroll-only must still query the staff table it actually needs.');
    }

    public function test_selected_domain_snapshot_issues_fewer_queries_than_the_full_snapshot(): void
    {
        $service = new NovaAIService();

        $fullCount = 0;
        DB::listen(function () use (&$fullCount) { $fullCount++; });
        $this->buildSnapshot($service, null);

        $financeCount = 0;
        DB::listen(function () use (&$financeCount) { $financeCount++; });
        $this->buildSnapshot($service, ['finance']);

        $this->assertLessThan($fullCount, $financeCount);
    }

    /* ---------------- Test 12: canonical order preserved across multi-domain selection ---------------- */

    public function test_multi_domain_selection_preserves_canonical_section_order_not_router_order(): void
    {
        $service = new NovaAIService();
        Staff::create(['name' => 'Order Check Staffer', 'branch' => 'old_airport', 'base_salary' => 2500]);

        // Deliberately requested payroll before finance - output order must
        // still follow buildSnapshot()'s own canonical section order.
        $body = $this->buildSnapshot($service, ['payroll', 'finance']);

        $revenuePos = strpos($body, 'REVENUE');
        $branchFinancialPos = strpos($body, 'BRANCH FINANCIAL PERFORMANCE');
        $payrollPos = strpos($body, 'STAFF PAYROLL COST');

        $this->assertNotFalse($revenuePos);
        $this->assertNotFalse($branchFinancialPos);
        $this->assertNotFalse($payrollPos);
        $this->assertTrue($revenuePos < $branchFinancialPos && $branchFinancialPos < $payrollPos);
    }

    /* ---------------- Test 21: PII reduction ---------------- */

    public function test_finance_only_never_discloses_staff_agent_or_customer_names(): void
    {
        $service = new NovaAIService();
        $this->seedPiiFixtures();

        $body = $this->buildSnapshot($service, ['finance']);

        foreach (['Pii Test Staffer', 'Pii Test Agent', 'Pii Test Customer', '9999.00'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $body);
        }
    }

    public function test_payroll_only_discloses_the_staff_name_and_salary_it_needs(): void
    {
        $service = new NovaAIService();
        $this->seedPiiFixtures();

        $body = $this->buildSnapshot($service, ['payroll']);

        $this->assertStringContainsString('Pii Test Staffer', $body);
        $this->assertStringContainsString('9999.00', $body);
    }

    public function test_packages_only_discloses_the_customer_name_it_needs(): void
    {
        $service = new NovaAIService();
        $this->seedPiiFixtures();

        $body = $this->buildSnapshot($service, ['packages']);

        $this->assertStringContainsString('Pii Test Customer', $body);
        $this->assertStringNotContainsString('Pii Test Staffer', $body);
        $this->assertStringNotContainsString('Pii Test Agent', $body);
    }

    private function seedPiiFixtures(): void
    {
        Staff::create(['name' => 'Pii Test Staffer', 'branch' => 'old_airport', 'base_salary' => 9999]);

        $agent = User::create([
            'name' => 'Pii Test Agent',
            'email' => 'pii.test.agent@example.test',
            'password' => 'irrelevant-for-tests',
            'role' => 'agent',
        ]);
        AgentShiftLog::create([
            'user_id' => $agent->id,
            'date' => now()->toDateString(),
            'shift' => 'morning',
            'check_in_time' => '08:00:00',
            'check_out_time' => '14:00:00',
        ]);

        $customer = Customer::create(['name' => 'Pii Test Customer', 'phone' => '+97400000001']);
        $combo = Combo::create(['name' => 'PII Combo', 'price' => 200, 'quantity_included' => 2, 'validity_days' => 30]);
        ClientPackage::create([
            'customer_id' => $customer->id,
            'combo_id' => $combo->id,
            'combo_name' => 'PII Combo',
            'price_paid' => 200,
            'quantity_included' => 2,
            'purchased_at' => now()->subDays(2),
            'expires_at' => now()->addDays(10),
            'status' => 'active',
        ]);
    }

    /* ---------------- Stage 8: partial CRM section failure resilience ---------------- */

    /**
     * Stage 8 logging-hardening correction: a section-failure exception's
     * message can carry raw SQL, bindings, or the input values themselves -
     * potentially real customer/business data - so it must never reach the
     * log. Only section/domain/exception-class/code are safe metadata.
     */
    public function test_one_failing_section_does_not_take_down_the_rest_and_never_leaks_the_exception_message(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $sensitiveMessage = 'Customer phone +97455555555 / SELECT * FROM sales WHERE branch = \'wakrah\'';

        // Anonymous subclass: only branchFinancialSection() is broken, every
        // other section-builder method is the real, untouched Nova code.
        $service = new class($sensitiveMessage) extends NovaAIService {
            public function __construct(private string $sensitiveMessage) {}

            protected function branchFinancialSection(): string
            {
                throw new \RuntimeException($this->sensitiveMessage);
            }
        };

        $body = $this->buildSnapshot($service, ['finance']);

        // The healthy sibling section in the same domain still renders...
        $this->assertStringContainsString('REVENUE', $body);
        // ...while the broken one is clearly marked, never silently dropped
        // and never implying the figure is zero.
        $this->assertStringContainsString('[finance DATA UNAVAILABLE]', $body);
        $this->assertStringContainsString('NOT evidence the underlying figures are zero', $body);
        // The sensitive exception message must never reach Nova's prompt/output.
        $this->assertStringNotContainsString($sensitiveMessage, $body);
        $this->assertStringNotContainsString('+97455555555', $body);
        $this->assertStringNotContainsString('SELECT', $body);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($sensitiveMessage) {
                $serializedContext = json_encode($context);

                return str_contains($message, 'failed to build')
                    && ($context['section'] ?? null) === 'branchFinancialSection'
                    && ($context['domain'] ?? null) === 'finance'
                    && ($context['exception'] ?? null) === \RuntimeException::class
                    && array_key_exists('code', $context)
                    && !array_key_exists('message', $context)
                    && !str_contains($serializedContext, $sensitiveMessage)
                    && !str_contains($serializedContext, '+97455555555')
                    && !str_contains($serializedContext, 'SELECT');
            })
            ->once();
    }

    public function test_a_broken_section_never_breaks_the_full_snapshot_fallback_either(): void
    {
        $service = new class extends NovaAIService {
            protected function pendingPackagesSection(): string
            {
                throw new \RuntimeException('simulated failure');
            }
        };

        $body = $this->buildSnapshot($service, null);

        $this->assertStringContainsString('REVENUE', $body);
        $this->assertStringContainsString('CUSTOMER & RETENTION INTELLIGENCE', $body);
        $this->assertStringContainsString('[packages DATA UNAVAILABLE]', $body);
    }

    /* ---------------- Test 24 (part): domain selection never touches HTTP ---------------- */

    public function test_building_a_selected_domain_snapshot_makes_no_http_request(): void
    {
        $service = new NovaAIService();

        \Illuminate\Support\Facades\Http::fake();

        $this->buildSnapshot($service, ['finance']);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
