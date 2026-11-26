<?php

namespace Tests\Feature;

use App\Models\BranchEvaluation;
use App\Models\KpiStaffEvaluation;
use App\Models\LegalTask;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockWeek;
use App\Models\StockWeekEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StockAndLegalTeamKpiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('admin'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_salon_manager_scorecards_include_branch_scoped_stock_kpi_with_strict_isolation_and_zero_item_neutral_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00', 'Asia/Qatar'));

        // Active Salon Managers dynamically bound to their branches
        $ahanaUser = User::factory()->create([
            'name' => 'Ahana Sharma',
            'email' => 'ahana@laleen.qa',
            'role' => 'salon_manager',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);
        Staff::create([
            'name' => 'Ahana Sharma',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => false,
            'user_id' => $ahanaUser->id,
        ]);

        $rheaUser = User::factory()->create([
            'name' => 'Rhea Jacob',
            'email' => 'rhea@laleen.qa',
            'role' => 'salon_manager',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);
        Staff::create([
            'name' => 'Rhea Jacob',
            'branch' => 'wakrah',
            'is_remote' => false,
            'bookable' => false,
            'user_id' => $rheaUser->id,
        ]);

        // Seed 100% Monday evaluations + 100% mystery shopper for both branches so Stock impact is crystal clear
        $oaStylist = Staff::create(['name' => 'OA Stylist', 'branch' => 'old_airport', 'bookable' => true, 'start_date' => '2026-01-01']);
        $wkStylist = Staff::create(['name' => 'WK Stylist', 'branch' => 'wakrah', 'bookable' => true, 'start_date' => '2026-01-01']);

        foreach (['2026-11-02', '2026-11-09', '2026-11-16'] as $monday) {
            foreach ([[$oaStylist, 'old_airport'], [$wkStylist, 'wakrah']] as [$st, $br]) {
                KpiStaffEvaluation::create([
                    'staff_id' => $st->id,
                    'evaluator_id' => $this->admin->id,
                    'branch' => $br,
                    'evaluation_date' => $monday,
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

        foreach (['old_airport' => 'Ahana', 'wakrah' => 'Rhea'] as $br => $mgrName) {
            BranchEvaluation::create([
                'branch' => $br,
                'manager' => $mgrName,
                'evaluation_date' => '2026-11-12',
                'answers' => [],
                'total_score' => 120,
                'percentage' => 90.0,
                'grade' => 'Excellent',
                'created_by' => $this->admin->id,
            ]);
        }

        // 1. Neutral state when 0 stock items are configured: renders N/A cleanly without crashing or penalizing score
        $emptyRes = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('Branch Stock Health')
            ->assertSee('No branch stock items configured yet');

        $emptyScorecard = $emptyRes->viewData('scorecard');
        $this->assertFalse($emptyScorecard['ahana']['stock_has_data']);
        $this->assertNull($emptyScorecard['ahana']['stock_health_pct']);
        $this->assertSame(0, $emptyScorecard['ahana']['stock_tracked_items_count']);
        $this->assertSame(2, $emptyScorecard['ahana']['total_kpis_count']);
        $this->assertSame(100.0, (float) $emptyScorecard['ahana']['overall_score_pct']);

        $this->assertFalse($emptyScorecard['rhea']['stock_has_data']);
        $this->assertNull($emptyScorecard['rhea']['stock_health_pct']);
        $this->assertSame(2, $emptyScorecard['rhea']['total_kpis_count']);
        $this->assertSame(100.0, (float) $emptyScorecard['rhea']['overall_score_pct']);

        // 2. Seed strictly branch-isolated stock items, weeks, entries, and movements
        // Old Airport only items (par_level_al_wakrah = 0 -> MUST NOT leak into Rhea's Wakrah scorecard!)
        $oaHealthy1 = StockItem::create([
            'name' => 'OA Keratin Serum',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'treatments',
            'unit' => 'Bottle',
            'par_level_old_airport' => 5,
            'par_level_al_wakrah' => 0,
            'is_active' => true,
        ]);
        $oaHealthy2 = StockItem::create([
            'name' => 'OA Hair Botox Cream',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'treatments',
            'unit' => 'Jar',
            'par_level_old_airport' => 4,
            'par_level_al_wakrah' => 0,
            'is_active' => true,
        ]);
        $oaHealthy3 = StockItem::create([
            'name' => 'OA Color Developer',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'hair_color',
            'unit' => 'Bottle',
            'par_level_old_airport' => 6,
            'par_level_al_wakrah' => 0,
            'is_active' => true,
        ]);
        $oaLowItem = StockItem::create([
            'name' => 'OA Toning Gloss',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'hair_color',
            'unit' => 'Tube',
            'par_level_old_airport' => 5,
            'par_level_al_wakrah' => 0,
            'is_active' => true,
        ]);

        // Wakrah only items (par_level_old_airport = 0 -> MUST NOT leak into Ahana's Old Airport scorecard!)
        $wkHealthy = StockItem::create([
            'name' => 'WK Hydra Cleanser',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'facials',
            'unit' => 'Bottle',
            'par_level_old_airport' => 0,
            'par_level_al_wakrah' => 4,
            'is_active' => true,
        ]);
        $wkOutItem = StockItem::create([
            'name' => 'WK Wax Beads',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'waxing',
            'unit' => 'Pack',
            'par_level_old_airport' => 0,
            'par_level_al_wakrah' => 5,
            'is_active' => true,
        ]);

        // Inactive item must be ignored in both branches
        StockItem::create([
            'name' => 'Discontinued Shampoo',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'other',
            'unit' => 'Bottle',
            'par_level_old_airport' => 10,
            'par_level_al_wakrah' => 10,
            'is_active' => false,
        ]);

        $bounds = StockWeek::weekBoundsFor('2026-11-18');

        $oaWeek = StockWeek::create([
            'branch' => 'old_airport',
            'week_start_date' => $bounds['start_str'],
            'week_end_date' => $bounds['end_str'],
            'status' => StockWeek::STATUS_SUBMITTED,
            'submitted_by' => $ahanaUser->id,
            'submitted_at' => now('Asia/Qatar'),
        ]);
        StockWeekEntry::create(['stock_week_id' => $oaWeek->id, 'stock_item_id' => $oaHealthy1->id, 'opening_qty' => 10, 'closing_qty' => 8, 'used_qty' => 2]);
        StockWeekEntry::create(['stock_week_id' => $oaWeek->id, 'stock_item_id' => $oaHealthy2->id, 'opening_qty' => 6, 'closing_qty' => 4, 'used_qty' => 2]);
        StockWeekEntry::create(['stock_week_id' => $oaWeek->id, 'stock_item_id' => $oaHealthy3->id, 'opening_qty' => 8, 'closing_qty' => 7, 'used_qty' => 1]);
        StockWeekEntry::create(['stock_week_id' => $oaWeek->id, 'stock_item_id' => $oaLowItem->id, 'opening_qty' => 4, 'closing_qty' => 2, 'used_qty' => 2]);

        StockMovement::create([
            'stock_week_id' => $oaWeek->id,
            'stock_item_id' => $oaHealthy1->id,
            'branch' => 'old_airport',
            'type' => StockMovement::TYPE_RECEIVED,
            'quantity' => 5,
            'created_by' => $ahanaUser->id,
        ]);
        StockMovement::create([
            'stock_week_id' => $oaWeek->id,
            'stock_item_id' => $oaHealthy3->id,
            'branch' => 'old_airport',
            'type' => StockMovement::TYPE_RECEIVED,
            'quantity' => 3,
            'created_by' => $ahanaUser->id,
        ]);

        $wkWeek = StockWeek::create([
            'branch' => 'al_wakrah',
            'week_start_date' => $bounds['start_str'],
            'week_end_date' => $bounds['end_str'],
            'status' => StockWeek::STATUS_OPEN,
        ]);
        StockWeekEntry::create(['stock_week_id' => $wkWeek->id, 'stock_item_id' => $wkHealthy->id, 'opening_qty' => 8, 'closing_qty' => 6, 'used_qty' => 2]);
        StockWeekEntry::create(['stock_week_id' => $wkWeek->id, 'stock_item_id' => $wkOutItem->id, 'opening_qty' => 3, 'closing_qty' => 0, 'used_qty' => 3]);

        StockMovement::create([
            'stock_week_id' => $wkWeek->id,
            'stock_item_id' => $wkHealthy->id,
            'branch' => 'al_wakrah',
            'type' => StockMovement::TYPE_RECEIVED,
            'quantity' => 4,
            'created_by' => $rheaUser->id,
        ]);

        $hubRes = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk();

        $scorecard = $hubRes->viewData('scorecard');

        // Old Airport (Ahana): 3 healthy, 1 low, 0 out of stock = 75.0% (< 85% target), 3 updates MTD (2 movements + 1 submitted audit)
        $ahana = $scorecard['ahana'];
        $this->assertSame('Ahana', $ahana['owner']);
        $this->assertSame('old_airport', $ahana['stock_branch']);
        $this->assertTrue($ahana['stock_has_data']);
        $this->assertSame(4, $ahana['stock_tracked_items_count']);
        $this->assertSame(3, $ahana['stock_healthy_count']);
        $this->assertSame(1, $ahana['stock_low_count']);
        $this->assertSame(0, $ahana['stock_out_of_stock_count']);
        $this->assertSame(75.0, (float) $ahana['stock_health_pct']);
        $this->assertFalse($ahana['stock_passing']);
        $this->assertSame(2, $ahana['stock_movements_mtd']);
        $this->assertSame(1, $ahana['stock_audits_submitted_mtd']);
        $this->assertSame(3, $ahana['stock_updates_mtd']);
        $this->assertSame(3, $ahana['total_kpis_count']);
        $this->assertSame(2, $ahana['passing_kpis_count']);
        $this->assertSame(round((100.0 + 100.0 + 75.0) / 3, 1), (float) $ahana['overall_score_pct']);

        // Al Wakrah (Rhea): 1 healthy, 0 low, 1 out of stock = 50.0% (< 85% target), 1 update MTD (1 movement + 0 submitted audits)
        $rhea = $scorecard['rhea'];
        $this->assertSame('Rhea', $rhea['owner']);
        $this->assertSame('al_wakrah', $rhea['stock_branch']);
        $this->assertTrue($rhea['stock_has_data']);
        $this->assertSame(2, $rhea['stock_tracked_items_count']);
        $this->assertSame(1, $rhea['stock_healthy_count']);
        $this->assertSame(0, $rhea['stock_low_count']);
        $this->assertSame(1, $rhea['stock_out_of_stock_count']);
        $this->assertSame(50.0, (float) $rhea['stock_health_pct']);
        $this->assertFalse($rhea['stock_passing']);
        $this->assertSame(1, $rhea['stock_movements_mtd']);
        $this->assertSame(0, $rhea['stock_audits_submitted_mtd']);
        $this->assertSame(1, $rhea['stock_updates_mtd']);
        $this->assertSame(3, $rhea['total_kpis_count']);
        $this->assertSame(2, $rhea['passing_kpis_count']);
        $this->assertSame(round((100.0 + 100.0 + 50.0) / 3, 1), (float) $rhea['overall_score_pct']);

        // Rendered HTML contains exact sub-lines and branch-filtered /stock links
        $hubRes->assertSee('Branch Stock Health')
            ->assertSee('1 low · 0 out of stock')
            ->assertSee('0 low · 1 out of stock')
            ->assertSee(route('stock.index', ['branch' => 'old_airport']), false)
            ->assertSee(route('stock.index', ['branch' => 'al_wakrah']), false);
    }

    public function test_executive_kpi_dashboard_renders_dynamic_legal_team_scorecard_with_mtd_completion_on_time_late_and_overdue_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00', 'Asia/Qatar'));

        // Clear any default seeded migration entries so our exact counts are deterministic
        LegalTask::query()->delete();

        $shaukat = User::factory()->create([
            'name' => 'Shaukat Ansari',
            'email' => 'shaukat@laleen.qa',
            'role' => 'legal_team',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('legal_team'),
        ]);

        // Inactive legal user must NOT appear on the dashboard
        User::factory()->create([
            'name' => 'Former Legal Counsel',
            'email' => 'former.legal@laleen.qa',
            'role' => 'legal_team',
            'is_active' => false,
            'permissions' => User::defaultPermissionsForRole('legal_team'),
        ]);

        // 1. Neutral state when 0 legal tasks exist in the period
        $neutralRes = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('data-team-filter="legal"', false)
            ->assertSee('Legal Team')
            ->assertSee('Shaukat Ansari')
            ->assertDontSee('Former Legal Counsel')
            ->assertSee('No tasks due')
            ->assertSee('100% — On Track');

        $neutralList = $neutralRes->viewData('scorecard')['legal_team_list'];
        $this->assertCount(1, $neutralList);
        $this->assertSame('legal_' . $shaukat->id, $neutralList[0]['owner_key']);
        $this->assertTrue($neutralList[0]['completion_is_neutral']);
        $this->assertSame(0, $neutralList[0]['overdue_count']);
        $this->assertSame(100.0, (float) $neutralList[0]['overall_score_pct']);

        // 2. Seed Legal Tasks spanning on-time, late, overdue, in-progress, and prior-month overdue
        // Task 1: Due Nov 15, completed Nov 14 Qatar -> Completed ON TIME
        LegalTask::create([
            'nature_of_work' => 'Al Wakrah Commercial Permit Renewal',
            'deadline' => '2026-11-15',
            'status' => LegalTask::STATUS_COMPLETED,
            'completed_at' => Carbon::parse('2026-11-14 16:00:00', 'Asia/Qatar'),
            'created_by' => $shaukat->id,
        ]);

        // Task 2: Due Nov 10, completed Nov 12 Qatar -> Completed LATE
        LegalTask::create([
            'nature_of_work' => 'Staff Health Card Batch Submission',
            'deadline' => '2026-11-10',
            'status' => LegalTask::STATUS_COMPLETED,
            'completed_at' => Carbon::parse('2026-11-12 09:00:00', 'Asia/Qatar'),
            'created_by' => $shaukat->id,
        ]);

        // Task 3: Due Nov 18 (< Nov 20), still Pending -> Due in Nov & OVERDUE (Open)
        LegalTask::create([
            'nature_of_work' => 'Civil Defense Fire Certificate',
            'deadline' => '2026-11-18',
            'status' => LegalTask::STATUS_PENDING,
            'created_by' => $shaukat->id,
        ]);

        // Task 4: Due Nov 25 (>= Nov 20), Under Process -> Due in Nov, NOT overdue (In Progress)
        LegalTask::create([
            'nature_of_work' => 'Main Branch Location Update',
            'deadline' => '2026-11-25',
            'status' => LegalTask::STATUS_UNDER_PROCESS,
            'created_by' => $shaukat->id,
        ]);

        // Task 5: Due Oct 28 (< Nov 20), on Hold -> Prior-month OVERDUE (Open), not in Nov due pool
        LegalTask::create([
            'nature_of_work' => 'Retail Activity License Amendment',
            'deadline' => '2026-10-28',
            'status' => LegalTask::STATUS_HOLD,
            'created_by' => $shaukat->id,
        ]);

        // Task 6: No deadline, completed Nov 19 Qatar -> Included via completed-in-month (On Time)
        LegalTask::create([
            'nature_of_work' => 'Giveaway Promotion Inquiry Clearance',
            'deadline' => null,
            'status' => LegalTask::STATUS_COMPLETED,
            'completed_at' => Carbon::parse('2026-11-19 10:00:00', 'Asia/Qatar'),
            'created_by' => $shaukat->id,
        ]);

        $hubRes = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk();

        $legalRow = $hubRes->viewData('scorecard')['legal_team_list'][0];

        $this->assertSame('Shaukat Ansari', $legalRow['name']);
        $this->assertSame(5, $legalRow['period_total_count']);     // Tasks 1, 2, 3, 4, 6
        $this->assertSame(3, $legalRow['period_completed_count']); // Tasks 1, 2, 6
        $this->assertSame(2, $legalRow['completed_on_time_count']); // Tasks 1, 6
        $this->assertSame(1, $legalRow['completed_late_count']);    // Task 2
        $this->assertFalse($legalRow['completion_is_neutral']);
        $this->assertSame(60.0, (float) $legalRow['completion_pct']);
        $this->assertFalse($legalRow['completion_passing']);

        $this->assertSame(2, $legalRow['overdue_count']);          // Tasks 3, 5
        $this->assertSame(2, $legalRow['open_count']);             // Tasks 3 (pending) + 5 (hold)
        $this->assertSame(1, $legalRow['in_progress_count']);      // Task 4 (under_process)
        $this->assertSame(3, $legalRow['completed_mtd_count']);    // Tasks 1, 2, 6
        $this->assertSame(50.0, (float) $legalRow['compliance_pct']);
        $this->assertFalse($legalRow['compliance_passing']);
        $this->assertSame(55.0, (float) $legalRow['overall_score_pct']);
        $this->assertSame(0, $legalRow['passing_kpis_count']);
        $this->assertSame(2, $legalRow['total_kpis_count']);

        $hubRes->assertSee('Shaukat Ansari &mdash; Legal Team (55%)', false)
            ->assertSee('MTD Legal Task Completion')
            ->assertSee('2 completed on time')
            ->assertSee('1 completed late')
            ->assertSee('Overdue &amp; Active Legal Compliance', false)
            ->assertSee('2 Open · 1 In Progress · 3 Completed MTD')
            ->assertSee(route('legal.tasks.index'), false);

        // Also confirm Home /dashboard renders Shaukat Ansari's 3-KPI Split Scorecard dynamically for Admin
        $dashRes = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $dashCards = collect($dashRes->viewData('teamScorecards'))->keyBy('key');
        $this->assertTrue($dashCards->has('legal_' . $shaukat->id));
        $this->assertCount(3, $dashCards->get('legal_' . $shaukat->id)['kpis']);
    }

    public function test_legal_team_rbac_strictly_blocks_non_legal_modules_and_other_staff_kpis(): void
    {
        $shaukat = User::factory()->create([
            'name' => 'Shaukat Ansari',
            'email' => 'shaukat.rbac@laleen.qa',
            'role' => 'legal_team',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('legal_team'),
        ]);

        // Allowed: Legal Tasks module (both canonical /legal/tasks and /legal-tasks alias)
        $this->actingAs($shaukat)->get(route('legal.tasks.index'))->assertOk();
        $this->actingAs($shaukat)->get('/legal-tasks')->assertOk();

        // Strictly Blocked (403): Executive KPIs, Dashboard, Finance, Clients, Appointments, Services, Stock, Staff, Monthly Offers
        $blockedUrls = [
            route('kpi.hub'),
            route('dashboard'),
            route('appointments.revenue.index'),
            route('customers.index'),
            route('appointments.index'),
            route('services.index'),
            route('stock.index'),
            route('staffs.index'),
            route('offers.index'),
        ];

        foreach ($blockedUrls as $url) {
            $this->actingAs($shaukat)
                ->get($url)
                ->assertForbidden();
        }

        // Self-service /my-profile?tab=performance shows ONLY Shaukat's own Legal KPIs with zero cross-staff or blocked links
        $selfPerf = $this->actingAs($shaukat)
            ->get('/my-profile?tab=performance')
            ->assertOk()
            ->assertSee('MTD Legal Task Completion')
            ->assertSee('Overdue &amp; Active Legal Compliance', false)
            ->assertDontSee('Branch Stock Health')
            ->assertDontSee('Old Airport Ad Revenue')
            ->assertDontSee(route('kpi.hub'), false)
            ->assertDontSee(route('stock.index'), false);

        $vm = $selfPerf->viewData('performanceScorecard');
        $this->assertSame('legal_' . $shaukat->id, $vm['owner_key']);
    }
}
