<?php

namespace Tests\Feature;

use App\Models\BranchEvaluation;
use App\Models\Expense;
use App\Models\KpiStaffEvaluation;
use App\Models\OpsActionItem;
use App\Models\Sale;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardKpiAndFinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Afnan Owner',
            'email' => 'afnanakbarghulam@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('admin'),
        ]);
    }

    public function test_dashboard_removes_hero_and_live_pulse_while_rendering_admin_two_branch_pnl_and_silent_from_to_params(): void
    {
        // Old Airport: Revenue 3,500.00, Expenses 0.00 -> must NOT show 100% margin, must show 'No expenses logged' + '+ Add Expense'
        Sale::create([
            'branch' => 'old_airport',
            'services_total' => 3500.00,
            'products_total' => 0.00,
            'packages_total' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 3500.00,
            'created_by' => $this->admin->id,
        ]);

        // Al Wakrah: Revenue 2,000.00, Expenses 500.00 -> Net Profit 1,500.00, 75.0% margin
        Sale::create([
            'branch' => 'wakrah',
            'services_total' => 2000.00,
            'products_total' => 0.00,
            'packages_total' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 2000.00,
            'created_by' => $this->admin->id,
        ]);

        Expense::create([
            'branch' => 'wakrah',
            'category' => 'Supplies',
            'amount' => 500.00,
            'expense_date' => today()->toDateString(),
            'description' => 'Salon supplies',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();

        // 1. Removed top Laleen Ops hero card, date form, and Live Today Salon Pulse strip
        $response->assertDontSee('Executive salon pulse, to-date branch performance');
        $response->assertDontSee('Live Today Salon Pulse');
        $response->assertDontSee("Today's Appointments");
        $response->assertDontSee('Leads Action Queue');
        $response->assertDontSee('Active Staff on Duty');
        $response->assertDontSee('Total Active Clients');

        // 2. Combined To-Date header + both crisp branch cards
        $response->assertSee('Branch Revenue & Expenses — To Date');
        $response->assertSee('OLD AIRPORT — TO DATE');
        $response->assertSee('AL WAKRAH — TO DATE');
        $response->assertSee('QAR 5,500.00'); // Combined Revenue
        $response->assertSee('QAR 500.00');   // Combined / Wakrah Expenses
        $response->assertSee('QAR 5,000.00'); // Combined Net
        $response->assertSee('QAR 3,500.00'); // Old Airport Revenue & Net
        $response->assertSee('QAR 2,000.00'); // Al Wakrah Revenue
        $response->assertSee('QAR 1,500.00'); // Al Wakrah Net Profit

        // 3. Margin Safeguard: Al Wakrah has expenses > 0 -> shows 75.0% margin; Old Airport has 0 expenses -> hides 100% margin
        $response->assertSee('75.0% margin');
        $response->assertDontSee('100.0% margin');
        $response->assertDontSee('100% margin');
        $response->assertSee('No expenses logged');
        $response->assertSee('+ Add Expense');

        // 4. Optional ?from=&to= query params honored silently
        $pastFrom = now()->subMonths(2)->startOfMonth()->toDateString();
        $pastTo = now()->subMonths(2)->endOfMonth()->toDateString();
        $filteredResp = $this->actingAs($this->admin)->get(route('dashboard', [
            'from' => $pastFrom,
            'to' => $pastTo,
        ]));
        $filteredResp->assertOk();
        $this->assertSame(0.0, (float) $filteredResp->viewData('combinedPnl')['sales']);
    }

    public function test_non_admin_roles_receive_200_ok_with_financial_pnl_strictly_hidden(): void
    {
        Sale::create([
            'branch' => 'old_airport',
            'services_total' => 9876.50,
            'products_total' => 0.00,
            'packages_total' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 9876.50,
            'created_by' => $this->admin->id,
        ]);

        Expense::create([
            'branch' => 'old_airport',
            'category' => 'Rent',
            'amount' => 1234.00,
            'expense_date' => today()->toDateString(),
            'description' => 'Monthly branch rent',
            'created_by' => $this->admin->id,
        ]);

        $rolesToVerify = ['manager', 'agent', 'marketing_specialist', 'staff'];

        foreach ($rolesToVerify as $role) {
            $perms = User::defaultPermissionsForRole($role);
            $perms['dashboard'] = 'view';
            // Even if a non-admin account had finance=edit in its permission map, Dashboard Section 1 is strictly Admin-only
            $perms['finance'] = 'edit';

            $staffUser = User::create([
                'name' => 'Test ' . ucfirst($role),
                'email' => $role . '@laleen.qa',
                'password' => Hash::make('password123'),
                'role' => $role,
                'is_active' => true,
                'permissions' => $perms,
            ]);

            $response = $this->actingAs($staffUser)->get(route('dashboard'));

            $response->assertOk();
            $response->assertViewHas('branchPnl', null);
            $response->assertViewHas('combinedPnl', null);
            $response->assertDontSee('Branch Revenue & Expenses — To Date');
            $response->assertDontSee('OLD AIRPORT — TO DATE');
            $response->assertDontSee('AL WAKRAH — TO DATE');
            $response->assertDontSee('9,876.50');
            $response->assertDontSee('1,234.00');
            $response->assertSee('Team KPI Scorecards');
        }
    }

    public function test_minimalistic_split_scorecards_have_uniform_3_kpis_rounded_scores_short_roles_primary_gate_and_self_pinning(): void
    {
        $monthStart = now()->startOfMonth()->startOfDay();
        $today = now()->endOfDay();

        // 1. Seed Ahana (Old Airport) with 100% secondary habits (Staff Evals + 96% Branch Audit),
        //    but 0 QAR Chair Upsells (<50% of primary output target) -> Primary Output Gate must block 'On Target'!
        $oaBeautician = Staff::create([
            'name' => 'Mina Stylist',
            'role' => 'Hair Stylist',
            'branch' => 'old_airport',
            'status' => 'active',
            'availability_status' => 'present',
            'created_at' => $monthStart->copy()->subMonth(),
        ]);

        foreach (CarbonPeriod::create($monthStart->copy()->startOfDay(), $today->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                KpiStaffEvaluation::create([
                    'staff_id' => $oaBeautician->id,
                    'evaluator_id' => $this->admin->id,
                    'branch' => 'old_airport',
                    'evaluation_date' => $day->toDateString(),
                    'score_cleanliness' => 5,
                    'score_dress_code' => 5,
                    'score_attitude' => 5,
                    'score_sales' => 5,
                    'score_teamwork' => 5,
                    'score_communication' => 5,
                    'overall_average' => 5.00,
                ]);
            }
        }

        BranchEvaluation::create([
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => today()->toDateString(),
            'answers' => ['q1' => 5, 'q2' => 5],
            'total_score' => 130,
            'percentage' => 96.6,
            'grade' => 'Excellent',
            'created_by' => $this->admin->id,
        ]);

        // 2. Seed Arbab with 100% completed on-time Ops Action Items and 0 overdue -> 'On Target' (100%)
        OpsActionItem::create([
            'title' => 'Audit reception iPad sync',
            'scope' => 'Old Airport',
            'impact_lever' => 'admin_ops',
            'priority' => 'normal',
            'assigned_names' => ['Arbab'],
            'sla_hours' => 48,
            'ball_assigned_at' => now()->subHours(6),
            'due_at' => now()->addHours(42),
            'week_start_date' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'is_completed' => true,
            'completed_at' => now()->subHours(1),
            'completed_by' => 'Arbab',
        ]);

        // 3. Seed Sana with full Reels + Stories across the month to-date and 0 late replies -> 'On Target' (100%)
        $firstMonday = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $lastMonday = $today->copy()->startOfWeek(Carbon::MONDAY);
        for ($weekCursor = $firstMonday->copy(); $weekCursor->lte($lastMonday); $weekCursor->addWeek()) {
            foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
                SocialContentPlan::create([
                    'week_start_date' => $weekCursor->toDateString(),
                    'day_of_week' => $dayName,
                    'reel_content' => $idx < 5 ? 'Salon Reel ' . ($idx + 1) : null,
                    'reel_link' => $idx < 5 ? 'https://drive.google.com/reel-' . ($idx + 1) : null,
                    'is_reel_posted' => $idx < 5,
                    'is_reel_skipped' => $idx >= 5,
                    'stories_content' => 'Salon Story ' . ($idx + 1),
                    'are_stories_posted' => true,
                ]);
            }
        }

        $adminResponse = $this->actingAs($this->admin)->get(route('dashboard'));
        $adminResponse->assertOk();

        // All 8 tracked team members rendered, 'Ahana' present and 'Faye' strictly absent
        foreach (['Ayan', 'Sana', 'Zoya', 'Areeba', 'Laden', 'Ahana', 'Rhea', 'Arbab'] as $memberName) {
            $adminResponse->assertSee($memberName);
        }
        $adminResponse->assertDontSee('Faye');
        $adminResponse->assertDontSee('Click to inspect');
        $adminResponse->assertDontSee('KPIs Passing');

        // Minimalist short roles & streamlined 3-row KPI labels cleanly visible
        foreach (['Chat Agent', 'Content', 'Sales Lead', 'Old Airport', 'Al Wakrah', 'Paid Ads', 'Ops Lead'] as $shortRole) {
            $adminResponse->assertSee($shortRole);
        }
        foreach ([
            'Bookings MTD',
            "Today's Quota",
            'Chat QA (<5m)',
            'Reels',
            'Daily Stories',
            'Reply SLA',
            'Team Bookings',
            'Overdue Leads',
            'Lead Conversion',
            'Chair Upsells',
            'Staff Evals',
            'Branch Audit',
            'Ad Revenue',
            'Ad Conversion',
            'Funnel Pace',
            'Overdue Tasks',
            'Ops Actions',
            'SLA On-Time',
        ] as $kpiLabel) {
            $adminResponse->assertSee($kpiLabel);
        }

        /** @var array<int, array<string, mixed>> $cards */
        $cards = $adminResponse->viewData('teamScorecards');
        $this->assertCount(8, $cards);

        // Every single card has strictly 3 KPIs and an integer rounded score
        foreach ($cards as $card) {
            $this->assertCount(3, $card['kpis'], "Expected strictly 3 KPIs on {$card['name']}");
            $this->assertIsInt($card['overall_rounded']);
        }

        $cardsByKey = collect($cards)->keyBy('key');

        // Primary Output Gate verification: Ahana has 0% Chair Upsells despite high Evals/Audit -> NEVER 'On Target'
        $ahanaCard = $cardsByKey->get('ahana');
        $this->assertNotNull($ahanaCard);
        $this->assertLessThan(50.0, $ahanaCard['primary_pct']);
        $this->assertNotSame('On Target', $ahanaCard['status']);
        $this->assertContains($ahanaCard['status'], ['Off Track', 'At Risk']);
        $this->assertSame('97%', $ahanaCard['kpis'][2]['value_label']); // 96.6% rounded cleanly to '97%'

        // Sana & Arbab are 100% On Target -> Bottleneck auto-sorting puts all 'Off Track' / 'At Risk' cards BEFORE 'On Target' cards
        $this->assertSame('On Target', $cardsByKey->get('arbab')['status']);
        $this->assertSame('On Target', $cardsByKey->get('sana')['status']);
        $this->assertSame('Off Track', $cards[0]['status']);

        $statusRanks = array_map(fn (array $c) => $c['status_rank'], $cards);
        $sortedStatusRanks = $statusRanks;
        sort($sortedStatusRanks);
        $this->assertSame($sortedStatusRanks, $statusRanks, 'Scorecards must sort Off Track -> At Risk -> On Target');

        // Staff Login View verification: When Arbab (non-admin) logs in, Arbab's own scorecard is pinned FIRST (index 0)
        $arbabPerms = User::defaultPermissionsForRole('manager');
        $arbabPerms['dashboard'] = 'view';
        $arbabUser = User::create([
            'name' => 'Arbab Khan',
            'email' => 'arbab@laleen.qa',
            'password' => Hash::make('password123'),
            'role' => 'manager',
            'is_active' => true,
            'permissions' => $arbabPerms,
        ]);

        $staffResponse = $this->actingAs($arbabUser)->get(route('dashboard'));
        $staffResponse->assertOk();

        $staffCards = $staffResponse->viewData('teamScorecards');
        $this->assertSame('arbab', $staffCards[0]['key']);
        $this->assertTrue($staffCards[0]['is_self']);
    }
}
