<?php

namespace Tests\Feature;

use App\Models\BranchEvaluation;
use App\Models\KpiStaffEvaluation;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BranchManagerKpiPipelineAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $otherAdmin;
    private User $ahana;
    private User $rhea;
    private User $chatAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->otherAdmin = User::factory()->create([
            'name' => 'HQ Auditor',
            'email' => 'hq@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->ahana = User::factory()->create([
            'name' => 'Ahana Sharma',
            'email' => 'ahana@laleen.qa',
            'role' => 'manager',
            'permissions' => ['kpis' => 'edit'],
            'is_active' => true,
        ]);

        $this->rhea = User::factory()->create([
            'name' => 'Rhea Jacob',
            'email' => 'rhea@laleen.qa',
            'role' => 'manager',
            'permissions' => ['kpis' => 'edit'],
            'is_active' => true,
        ]);

        $this->chatAgent = User::factory()->create([
            'name' => 'Areeba Khan',
            'email' => 'areeba@laleen.qa',
            'role' => 'agent',
            'permissions' => ['kpis' => 'edit'],
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function allPassBranchAnswers(): array
    {
        $answers = [];
        foreach (BranchEvaluation::questionFields() as $field) {
            $answers[$field] = '5';
        }

        return $answers;
    }

    /** @return array<string, int> */
    private function allPassStaffScores(): array
    {
        return [
            'score_cleanliness' => 5,
            'score_dress_code' => 5,
            'score_attitude' => 5,
            'score_sales' => 5,
            'score_teamwork' => 5,
            'score_communication' => 5,
        ];
    }

    private function seedStaffEvaluation(Staff $staff, User $evaluator, string $branch, string $dateStr): KpiStaffEvaluation
    {
        return KpiStaffEvaluation::create($this->allPassStaffScores() + [
            'staff_id' => $staff->id,
            'evaluator_id' => $evaluator->id,
            'branch' => $branch,
            'evaluation_date' => $dateStr,
            'overall_average' => 5.00,
        ]);
    }

    public function test_card2_mystery_shopper_enforces_admin_only_writes_audit_columns_and_zero_math(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', 'Asia/Qatar'));

        $oaStylist = Staff::create(['name' => 'Sara Stylist', 'branch' => 'old_airport', 'bookable' => true]);
        $this->seedStaffEvaluation($oaStylist, $this->ahana, 'old_airport', '2026-11-02');
        $this->seedStaffEvaluation($oaStylist, $this->ahana, 'old_airport', '2026-11-09');

        // 1. 0 Mystery Shopper evaluations MTD -> 0.0% FAIL, Overall = 50.0% when Card 1 is 100.0%
        $hubRes = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
        $hubRes->assertOk();
        $ahanaCard = $hubRes->viewData('scorecard')['ahana'];

        $this->assertSame(100.0, (float) $ahanaCard['eval_compliance_pct']);
        $this->assertTrue($ahanaCard['eval_compliance_passing']);
        $this->assertSame(0, $ahanaCard['audits_count_mtd']);
        $this->assertSame(0.0, (float) $ahanaCard['mystery_shopper_avg_pct']);
        $this->assertSame(0.0, (float) $ahanaCard['mystery_shopper_attainment_pct']);
        $this->assertFalse($ahanaCard['mystery_shopper_passing']);
        $this->assertSame(50.0, (float) $ahanaCard['overall_score_pct']);

        // 2. Admin creates & updates a Mystery Shopper evaluation -> created_by and updated_by recorded
        $storePayload = [
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => '2026-11-10',
            'answers' => $this->allPassBranchAnswers(),
            'action_plan' => 'Initial HQ visit.',
        ];

        $this->actingAs($this->admin)
            ->post(route('kpi.branch-evaluation.store'), $storePayload)
            ->assertRedirect();

        $evaluation = BranchEvaluation::firstOrFail();
        $this->assertSame($this->admin->id, $evaluation->created_by);
        $this->assertSame($this->admin->id, $evaluation->updated_by);

        $this->actingAs($this->otherAdmin)
            ->put(route('kpi.branch-evaluation.update', $evaluation), [
                'branch' => 'old_airport',
                'manager' => 'Ahana',
                'evaluation_date' => '2026-11-11',
                'answers' => $this->allPassBranchAnswers(),
                'action_plan' => 'Updated by Owner.',
            ])
            ->assertRedirect(route('kpi.branch-evaluation.show', $evaluation));

        $evaluation->refresh();
        $this->assertSame($this->admin->id, $evaluation->created_by);
        $this->assertSame($this->otherAdmin->id, $evaluation->updated_by);

        // 3. Ahana, Rhea, and Chat Agents (all with kpis = edit) can view reports, NEVER see create/edit buttons, and get 403 on write routes
        foreach ([$this->ahana, $this->rhea, $this->chatAgent] as $nonAdmin) {
            $this->actingAs($nonAdmin)
                ->get(route('kpi.branch-evaluation.index'))
                ->assertOk()
                ->assertDontSee('New Branch Evaluation')
                ->assertDontSee(route('kpi.branch-evaluation.edit', $evaluation), false);

            $this->actingAs($nonAdmin)
                ->get(route('kpi.branch-evaluation.show', $evaluation))
                ->assertOk()
                ->assertDontSee('Edit Report')
                ->assertDontSee(route('kpi.branch-evaluation.edit', $evaluation), false);

            $this->actingAs($nonAdmin)
                ->get(route('kpi.branch-evaluation.create'))
                ->assertForbidden();

            $this->actingAs($nonAdmin)
                ->post(route('kpi.branch-evaluation.store'), $storePayload)
                ->assertForbidden();

            $this->actingAs($nonAdmin)
                ->get(route('kpi.branch-evaluation.edit', $evaluation))
                ->assertForbidden();

            $this->actingAs($nonAdmin)
                ->put(route('kpi.branch-evaluation.update', $evaluation), $storePayload)
                ->assertForbidden();
        }
    }

    public function test_card1_branch_isolation_agent_block_and_anti_backdating_rules(): void
    {
        // Monday Nov 9, 2026 in Asia/Qatar (current week Mon-Tue window: 2026-11-09 .. 2026-11-10)
        Carbon::setTestNow(Carbon::parse('2026-11-09 11:00:00', 'Asia/Qatar'));

        $oaStylist = Staff::create(['name' => 'Layla OA', 'branch' => 'old_airport', 'bookable' => true]);
        $wkStylist = Staff::create(['name' => 'Fatima WK', 'branch' => 'wakrah', 'bookable' => true]);

        // 1. Ahana can submit on Monday for old_airport staff
        $this->actingAs($this->ahana)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $oaStylist->id,
                'branch' => 'old_airport',
                'evaluation_date' => '2026-11-09',
            ])
            ->assertRedirect(route('kpi.evaluations.index'));

        // Ahana gets 403 trying to post to wakrah
        $this->actingAs($this->ahana)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $wkStylist->id,
                'branch' => 'wakrah',
                'evaluation_date' => '2026-11-09',
            ])
            ->assertForbidden();

        // Ahana fails validation trying to post a wakrah-only stylist under old_airport
        $this->actingAs($this->ahana)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $wkStylist->id,
                'branch' => 'old_airport',
                'evaluation_date' => '2026-11-09',
            ])
            ->assertSessionHasErrors('staff_id');

        // 2. Rhea can submit on Tuesday for wakrah staff, gets 403 on old_airport
        Carbon::setTestNow(Carbon::parse('2026-11-10 15:30:00', 'Asia/Qatar'));

        $this->actingAs($this->rhea)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $wkStylist->id,
                'branch' => 'wakrah',
                'evaluation_date' => '2026-11-10',
            ])
            ->assertRedirect(route('kpi.evaluations.index'));

        $this->actingAs($this->rhea)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $oaStylist->id,
                'branch' => 'old_airport',
                'evaluation_date' => '2026-11-10',
            ])
            ->assertForbidden();

        // 3. Chat Agent (even with kpis = edit) gets 403 and does not see "+ New Evaluation"
        $this->actingAs($this->chatAgent)
            ->get(route('kpi.evaluations.index'))
            ->assertOk()
            ->assertDontSee('data-bs-target="#newEvaluationModal"', false);

        $this->actingAs($this->chatAgent)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $oaStylist->id,
                'branch' => 'old_airport',
                'evaluation_date' => '2026-11-10',
            ])
            ->assertForbidden();

        // 4. Anti-Backdating: Ahana cannot backdate evaluation_date to prior Monday (2026-11-02)
        $this->actingAs($this->ahana)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $oaStylist->id,
                'branch' => 'old_airport',
                'evaluation_date' => '2026-11-02',
            ])
            ->assertSessionHasErrors('evaluation_date');

        // 5. Admin CAN backfill prior Monday even on a Thursday
        Carbon::setTestNow(Carbon::parse('2026-11-12 16:00:00', 'Asia/Qatar'));
        $this->actingAs($this->admin)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $oaStylist->id,
                'branch' => 'old_airport',
                'evaluation_date' => '2026-11-02',
            ])
            ->assertRedirect(route('kpi.evaluations.index'));

        $this->assertDatabaseHas('kpi_staff_evaluations', [
            'staff_id' => $oaStylist->id,
            'branch' => 'old_airport',
            'evaluator_id' => $this->admin->id,
        ]);
    }

    public function test_roster_includes_on_leave_and_sick_stylists_excludes_agents_and_managers_and_respects_mid_month_hire_dates(): void
    {
        // Friday Nov 13, 2026 (2 Mondays elapsed: Nov 2 & Nov 9)
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Asia/Qatar'));

        // Included in Old Airport on both Nov 2 & Nov 9 (even though on-leave / sick / StaffTimeOff):
        $presentStylist = Staff::create([
            'name' => 'Farhana Stylist', // also verifies 'Farhana' is NOT mistaken for 'Ahana'
            'branch' => 'old_airport',
            'bookable' => true,
            'availability_status' => 'present',
            'start_date' => '2026-01-01',
        ]);

        $onLeaveStylist = Staff::create([
            'name' => 'Mona On Leave',
            'branch' => 'old_airport',
            'bookable' => true,
            'availability_status' => 'on-leave',
            'start_date' => '2026-03-01',
        ]);
        StaffTimeOff::create([
            'staff_id' => $onLeaveStylist->id,
            'type' => 'annual_leave',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-20',
            'status' => 'approved',
        ]);

        $sickStylist = Staff::create([
            'name' => 'Huda Sick',
            'branch' => 'old_airport',
            'bookable' => true,
            'availability_status' => 'sick',
        ]);

        // Excluded: linked to chat agent user (role = 'agent')
        $nadiaAgentUser = User::factory()->create(['name' => 'Nadia Youssef', 'role' => 'agent']);
        $nadiaStaff = Staff::create([
            'name' => 'Nadia Youssef',
            'branch' => 'old_airport',
            'bookable' => true,
            'user_id' => $nadiaAgentUser->id,
        ]);

        // Excluded: Branch Manager self-rows (both linked to manager user and standalone name match)
        $ahanaSelfStaff = Staff::create([
            'name' => 'Ahana Sharma',
            'branch' => 'old_airport',
            'bookable' => true,
            'user_id' => $this->ahana->id,
        ]);
        Staff::create([
            'name' => 'Rhea Jacob',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);

        // Mid-month hire on 2026-11-12: active today (active_staff_count = 4),
        // MUST NOT be counted in Nov 2 or Nov 9 Monday denominators!
        $midMonthHire = Staff::create([
            'name' => 'Zainab New Hire',
            'branch' => 'old_airport',
            'bookable' => true,
            'start_date' => '2026-11-12',
        ]);

        // Evaluating an excluded staff row (e.g. Nadia agent) does NOT mask a missing eligible stylist
        $this->seedStaffEvaluation($nadiaStaff, $this->admin, 'old_airport', '2026-11-02');
        $this->seedStaffEvaluation($ahanaSelfStaff, $this->admin, 'old_airport', '2026-11-02');

        // Evaluate all 3 eligible stylists (present + on-leave + sick) on Nov 2 and Nov 9 -> 6/6 = 100.0% PASS
        foreach (['2026-11-02', '2026-11-09'] as $mondayStr) {
            foreach ([$presentStylist, $onLeaveStylist, $sickStylist] as $eligible) {
                $this->seedStaffEvaluation($eligible, $this->ahana, 'old_airport', $mondayStr);
            }
        }

        $hubRes = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
        $hubRes->assertOk();
        $ahanaCard = $hubRes->viewData('scorecard')['ahana'];

        $this->assertSame(4, $ahanaCard['active_staff_count']); // includes Nov 12 hire as of Nov 13
        $this->assertSame(2, $ahanaCard['mondays_elapsed_count']);
        $this->assertSame(6, $ahanaCard['expected_evaluations_mtd']); // 3 stylists * 2 past Mondays (Nov 2 & Nov 9)
        $this->assertSame(6, $ahanaCard['completed_evaluations_mtd']);
        $this->assertSame(100.0, (float) $ahanaCard['eval_compliance_pct']);
        $this->assertTrue($ahanaCard['eval_compliance_passing']);

        // Verify /kpis/evaluations dropdown includes on-leave, sick, and Nov 12 hire today, while excluding Nadia & Ahana
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Qatar'));
        $evalIndexRes = $this->actingAs($this->ahana)->get(route('kpi.evaluations.index'));
        $evalIndexRes->assertOk();
        preg_match('/var STAFF_BY_BRANCH = (\{.*?\});/s', $evalIndexRes->getContent(), $matches);
        $oaDropdownNames = array_column(json_decode($matches[1], true)['old_airport'], 'name');

        $this->assertContains('Farhana Stylist', $oaDropdownNames);
        $this->assertContains('Mona On Leave', $oaDropdownNames);
        $this->assertContains('Huda Sick', $oaDropdownNames);
        $this->assertContains('Zainab New Hire', $oaDropdownNames);
        $this->assertNotContains('Nadia Youssef', $oaDropdownNames);
        $this->assertNotContains('Ahana Sharma', $oaDropdownNames);
        $this->assertNotContains('Rhea Jacob', $oaDropdownNames);

        // And Ahana CAN submit an evaluation for the on-leave stylist on Monday
        $this->actingAs($this->ahana)
            ->post(route('kpi.evaluations.store'), $this->allPassStaffScores() + [
                'staff_id' => $onLeaveStylist->id,
                'branch' => 'old_airport',
                'evaluation_date' => '2026-11-16',
            ])
            ->assertRedirect(route('kpi.evaluations.index'));

        $this->assertNotNull($midMonthHire->fresh());
    }

    public function test_monday_tuesday_grace_window_and_wednesday_deadline_transition(): void
    {
        $stylistA = Staff::create(['name' => 'Stylist A', 'branch' => 'old_airport', 'bookable' => true, 'start_date' => '2026-01-01']);
        $stylistB = Staff::create(['name' => 'Stylist B', 'branch' => 'old_airport', 'bookable' => true, 'start_date' => '2026-01-01']);

        // 0. Sunday Nov 1, 2026 (before 1st Monday of month): 0/0 -> 0.0% (no free 100%)
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Asia/Qatar'));
        $sunCard = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard')['ahana'];
        $this->assertSame(0, $sunCard['mondays_elapsed_count']);
        $this->assertSame(0, $sunCard['expected_evaluations_mtd']);
        $this->assertSame(0, $sunCard['completed_evaluations_mtd']);
        $this->assertSame(0.0, (float) $sunCard['eval_compliance_pct']);
        $this->assertFalse($sunCard['eval_compliance_passing']);

        // 1. Week 1 (Monday 2026-11-02): Ahana evaluates 100% (2/2) of Old Airport stylists
        $this->seedStaffEvaluation($stylistA, $this->ahana, 'old_airport', '2026-11-02');
        $this->seedStaffEvaluation($stylistB, $this->ahana, 'old_airport', '2026-11-02');

        // 2. Monday 2026-11-09 10:00 Asia/Qatar (Week 2 in grace, 0/2 submitted for Week 2) -> stays 100.0% PASSING
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'Asia/Qatar'));
        $monCard = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard')['ahana'];
        $this->assertSame(1, $monCard['mondays_elapsed_count']);
        $this->assertSame(2, $monCard['expected_evaluations_mtd']);
        $this->assertSame(2, $monCard['completed_evaluations_mtd']);
        $this->assertSame(100.0, (float) $monCard['eval_compliance_pct']);
        $this->assertTrue($monCard['eval_compliance_passing']);

        // 3. Tuesday 2026-11-10 18:00 Asia/Qatar (still in grace, Week 2 unsubmitted) -> stays 100.0% PASSING
        Carbon::setTestNow(Carbon::parse('2026-11-10 18:00:00', 'Asia/Qatar'));
        $tueCard = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard')['ahana'];
        $this->assertSame(100.0, (float) $tueCard['eval_compliance_pct']);
        $this->assertTrue($tueCard['eval_compliance_passing']);

        // 4. Wednesday 2026-11-11 00:05 Asia/Qatar (grace expired, Week 2 left unsubmitted) -> drops to 50.0% FAIL (2/4)
        Carbon::setTestNow(Carbon::parse('2026-11-11 00:05:00', 'Asia/Qatar'));
        $wedCard = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard')['ahana'];
        $this->assertSame(2, $wedCard['mondays_elapsed_count']);
        $this->assertSame(4, $wedCard['expected_evaluations_mtd']);
        $this->assertSame(2, $wedCard['completed_evaluations_mtd']);
        $this->assertSame(50.0, (float) $wedCard['eval_compliance_pct']);
        $this->assertFalse($wedCard['eval_compliance_passing']);
    }

    public function test_hub_renders_live_progress_bars_short_delta_and_month_drill_downs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Asia/Qatar'));

        $oaStylist = Staff::create(['name' => 'OA Stylist', 'branch' => 'old_airport', 'bookable' => true]);
        $this->seedStaffEvaluation($oaStylist, $this->admin, 'old_airport', '2026-10-05');
        $this->seedStaffEvaluation($oaStylist, $this->admin, 'old_airport', '2026-11-02');

        BranchEvaluation::create([
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => '2026-10-15',
            'answers' => $this->allPassBranchAnswers(),
            'total_score' => 135,
            'percentage' => 100.00,
            'grade' => 'Excellent',
        ]);
        BranchEvaluation::create([
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => '2026-11-10',
            'answers' => $this->allPassBranchAnswers(),
            'total_score' => 115,
            'percentage' => 85.19,
            'grade' => 'Pass',
        ]);

        $hubHtml = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-11']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(e(route('kpi.evaluations.index', ['branch' => 'old_airport', 'month' => '2026-11'])), $hubHtml);
        $this->assertStringContainsString(e(route('kpi.evaluations.index', ['branch' => 'wakrah', 'month' => '2026-11'])), $hubHtml);
        $this->assertStringContainsString(e(route('kpi.branch-evaluation.index', ['branch' => 'old_airport', 'month' => '2026-11'])), $hubHtml);
        $this->assertStringContainsString(e(route('kpi.branch-evaluation.index', ['branch' => 'wakrah', 'month' => '2026-11'])), $hubHtml);
        $this->assertStringContainsString('1/2 Evaluated</span> &bull; 2 Mons MTD (100% Target)', $hubHtml);
        $this->assertStringContainsString('<span>85% Target</span>', $hubHtml);

        // Verify ?month=2026-11 filters StaffEvaluationController::index() and BranchEvaluationController::index()
        $this->actingAs($this->admin)
            ->get(route('kpi.evaluations.index', ['branch' => 'old_airport', 'month' => '2026-11']))
            ->assertOk()
            ->assertSee('02 Nov 2026')
            ->assertDontSee('05 Oct 2026');

        $this->actingAs($this->admin)
            ->get(route('kpi.branch-evaluation.index', ['branch' => 'old_airport', 'month' => '2026-11']))
            ->assertOk()
            ->assertSee('10 Nov 2026')
            ->assertDontSee('15 Oct 2026');
    }
}
