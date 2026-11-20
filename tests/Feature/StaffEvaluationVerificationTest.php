<?php

namespace Tests\Feature;

use App\Models\KpiStaffEvaluation;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffEvaluationVerificationTest extends TestCase
{
    use RefreshDatabase;

    // A fixed Monday/Tuesday/Wednesday set so store() tests don't depend on which
    // day the suite actually runs on.
    private const A_MONDAY = '2026-10-12 10:00:00';
    private const A_TUESDAY = '2026-10-13 10:00:00';
    private const A_WEDNESDAY = '2026-10-14 10:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeEvaluation(Staff $staff, User $evaluator, string $branch, Carbon $date, array $scores, ?string $notes = null): KpiStaffEvaluation
    {
        return KpiStaffEvaluation::create([
            'staff_id' => $staff->id,
            'evaluator_id' => $evaluator->id,
            'branch' => $branch,
            'evaluation_date' => $date->toDateString(),
            'score_cleanliness' => $scores[0],
            'score_dress_code' => $scores[1],
            'score_attitude' => $scores[2],
            'score_sales' => $scores[3],
            'score_teamwork' => $scores[4],
            'score_communication' => $scores[5],
            'overall_average' => KpiStaffEvaluation::averageOf([
                'score_cleanliness' => $scores[0],
                'score_dress_code' => $scores[1],
                'score_attitude' => $scores[2],
                'score_sales' => $scores[3],
                'score_teamwork' => $scores[4],
                'score_communication' => $scores[5],
            ]),
            'leader_notes' => $notes,
        ]);
    }

    public function test_index_page_renders_weekly_cards_toggle_and_branch_filter(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index'))
            ->assertOk()
            ->assertSee('Salon Staff Evaluation')
            ->assertSee('Cleanliness &amp; Hygiene', false)
            ->assertDontSee('The Hormozi Lens', false)
            ->assertSee('Old Airport (Ahana)')
            ->assertSee('Wakrah (Rhea)')
            ->assertSee('Salon Weakest Metric')
            ->assertSee('This Week')
            ->assertSee('Previous Weeks')
            ->assertSee('All Branches')
            ->assertSee('id="dashboardBranchFilter"', false);
    }

    /**
     * Extracts a single button's opening tag from rendered HTML so tests can
     * assert on its attributes without confusing it with other buttons on
     * the page (e.g. the dashboard trigger vs. the modal's submit button,
     * both of which can carry "disabled" depending on the day).
     */
    private function extractButtonTag(string $html, string $needle): string
    {
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos, "Could not find \"{$needle}\" in response HTML.");

        $tagStart = strrpos(substr($html, 0, $pos), '<button');
        $tagEnd = strpos($html, '>', $pos);

        return substr($html, $tagStart, $tagEnd - $tagStart + 1);
    }

    public function test_dashboard_new_evaluation_button_always_enabled(): void
    {
        foreach ([self::A_MONDAY, self::A_TUESDAY] as $day) {
            Carbon::setTestNow($day);

            $user = User::factory()->create(['role' => 'admin']);

            $response = $this->actingAs($user)->get(route('kpi.evaluations.index'));
            $response->assertOk();
            $response->assertDontSee('Evaluations unlock on Mondays');

            $tag = $this->extractButtonTag($response->getContent(), 'data-bs-target="#newEvaluationModal"');
            $this->assertStringNotContainsString('disabled', $tag);
            $this->assertStringNotContainsString('opacity-50', $tag);
            $this->assertStringNotContainsString('cursor-not-allowed', $tag);
        }
    }

    public function test_submit_button_disabled_outside_monday_with_warning(): void
    {
        Carbon::setTestNow(self::A_WEDNESDAY);

        $manager = User::factory()->create([
            'name' => 'Rhea Jacob',
            'role' => 'manager',
            'permissions' => ['kpis' => 'edit'],
        ]);

        $response = $this->actingAs($manager)->get(route('kpi.evaluations.index'));
        $response->assertOk();
        $response->assertSee('Evaluations can be submitted on Mondays and Tuesdays (2-day window).');

        $tag = $this->extractButtonTag($response->getContent(), '>Save Evaluation<');
        $this->assertStringContainsString('disabled', $tag);
        $this->assertStringContainsString('opacity-50', $tag);
        $this->assertStringContainsString('cursor-not-allowed', $tag);
    }

    public function test_submit_button_enabled_on_monday(): void
    {
        foreach ([self::A_MONDAY, self::A_TUESDAY] as $allowedDay) {
            Carbon::setTestNow($allowedDay);

            $manager = User::factory()->create([
                'name' => 'Rhea Jacob',
                'role' => 'manager',
                'permissions' => ['kpis' => 'edit'],
            ]);

            $response = $this->actingAs($manager)->get(route('kpi.evaluations.index'));
            $response->assertOk();
            $response->assertDontSee('Evaluations can be submitted on Mondays and Tuesdays (2-day window).');

            $tag = $this->extractButtonTag($response->getContent(), '>Save Evaluation<');
            $this->assertStringNotContainsString('disabled', $tag);
            $this->assertStringNotContainsString('opacity-50', $tag);
            $this->assertStringNotContainsString('cursor-not-allowed', $tag);
        }
    }

    public function test_store_blocked_outside_monday(): void
    {
        Carbon::setTestNow(self::A_WEDNESDAY);

        $manager = User::factory()->create([
            'name' => 'Rhea Jacob',
            'role' => 'manager',
            'permissions' => ['kpis' => 'edit'],
        ]);
        $staff = Staff::create(['name' => 'Wednesday Stylist', 'branch' => 'wakrah']);

        $payload = [
            'staff_id' => $staff->id,
            'branch' => 'wakrah',
            'evaluation_date' => now('Asia/Qatar')->format('Y-m-d'),
            'score_cleanliness' => 5,
            'score_dress_code' => 1,
            'score_attitude' => 5,
            'score_sales' => 1,
            'score_teamwork' => 5,
            'score_communication' => 1,
        ];

        $this->actingAs($manager)
            ->post(route('kpi.evaluations.store'), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('kpi_staff_evaluations', 0);

        // Submitting on Tuesday (2nd day of the 2-day window) succeeds cleanly
        Carbon::setTestNow(self::A_TUESDAY);
        $payload['evaluation_date'] = now('Asia/Qatar')->format('Y-m-d');
        $this->actingAs($manager)
            ->post(route('kpi.evaluations.store'), $payload)
            ->assertRedirect(route('kpi.evaluations.index'));

        $this->assertDatabaseCount('kpi_staff_evaluations', 1);
    }

    public function test_store_saves_binary_scores_and_branch_and_computes_average(): void
    {
        Carbon::setTestNow(self::A_MONDAY);

        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Verification Stylist']);

        $payload = [
            'staff_id' => $staff->id,
            'branch' => 'wakrah',
            'evaluation_date' => now()->format('Y-m-d'),
            'score_cleanliness' => 5,
            'score_dress_code' => 1,
            'score_attitude' => 5,
            'score_sales' => 1,
            'score_teamwork' => 5,
            'score_communication' => 1,
            'leader_notes' => 'Great hustle on retail, needs polish on dress code.',
        ];

        $this->actingAs($user)
            ->post(route('kpi.evaluations.store'), $payload)
            ->assertRedirect(route('kpi.evaluations.index'));

        $this->assertDatabaseCount('kpi_staff_evaluations', 1);

        $saved = KpiStaffEvaluation::first();

        $this->assertSame('wakrah', $saved->branch);
        $this->assertSame(5, $saved->score_cleanliness);
        $this->assertSame(1, $saved->score_dress_code);
        $this->assertSame(5, $saved->score_attitude);
        $this->assertSame(1, $saved->score_sales);
        $this->assertSame(5, $saved->score_teamwork);
        $this->assertSame(1, $saved->score_communication);
        $this->assertSame($staff->id, $saved->staff_id);
        $this->assertSame($user->id, $saved->evaluator_id);
        $this->assertEquals(3.0, (float) $saved->overall_average);
    }

    public function test_store_rejects_mid_scale_scores(): void
    {
        Carbon::setTestNow(self::A_MONDAY);

        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Mid Scale Stylist']);

        $payload = [
            'staff_id' => $staff->id,
            'branch' => 'wakrah',
            'evaluation_date' => now()->format('Y-m-d'),
            'score_cleanliness' => 3,
            'score_dress_code' => 1,
            'score_attitude' => 5,
            'score_sales' => 1,
            'score_teamwork' => 5,
            'score_communication' => 1,
        ];

        $this->actingAs($user)
            ->post(route('kpi.evaluations.store'), $payload)
            ->assertSessionHasErrors('score_cleanliness');

        $this->assertDatabaseCount('kpi_staff_evaluations', 0);
    }

    public function test_store_rejects_invalid_branch(): void
    {
        Carbon::setTestNow(self::A_MONDAY);

        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Branchless Stylist']);

        $payload = [
            'staff_id' => $staff->id,
            'branch' => 'dubai',
            'evaluation_date' => now()->format('Y-m-d'),
            'score_cleanliness' => 5,
            'score_dress_code' => 1,
            'score_attitude' => 5,
            'score_sales' => 1,
            'score_teamwork' => 5,
            'score_communication' => 1,
        ];

        $this->actingAs($user)
            ->post(route('kpi.evaluations.store'), $payload)
            ->assertSessionHasErrors('branch');

        $this->assertDatabaseCount('kpi_staff_evaluations', 0);
    }

    public function test_branch_dropdown_defaults_from_evaluator_name(): void
    {
        $rhea = User::factory()->create(['role' => 'admin', 'name' => 'Rhea Fernandes']);
        $ahana = User::factory()->create(['role' => 'admin', 'name' => 'Ahana Kapoor']);
        $other = User::factory()->create(['role' => 'admin', 'name' => 'Test User']);

        $this->assertSame('wakrah', KpiStaffEvaluation::defaultBranchFor($rhea));
        $this->assertSame('old_airport', KpiStaffEvaluation::defaultBranchFor($ahana));
        $this->assertNull(KpiStaffEvaluation::defaultBranchFor($other));
    }

    public function test_weekly_status_cards_reflect_branch_totals_and_evaluations(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $staffA = Staff::create(['name' => 'Wakrah Stylist A', 'branch' => 'wakrah']);
        Staff::create(['name' => 'Wakrah Stylist B', 'branch' => 'wakrah']);

        $this->makeEvaluation($staffA, $user, 'wakrah', now(), [5, 5, 5, 5, 5, 5]);

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index'))
            ->assertOk()
            ->assertSee('1 / 2');
    }

    public function test_weakest_metric_card_counts_ones_across_branches_this_week(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $staffA = Staff::create(['name' => 'Metric Stylist A']);
        $staffB = Staff::create(['name' => 'Metric Stylist B']);

        // score_sales = 1 for both staff this week; every other metric is 5.
        $this->makeEvaluation($staffA, $user, 'wakrah', now(), [5, 5, 5, 1, 5, 5]);
        $this->makeEvaluation($staffB, $user, 'old_airport', now(), [5, 5, 5, 1, 5, 5]);

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index'))
            ->assertOk()
            ->assertSee('Sales Focus')
            ->assertSee('2 of 2 evaluations scored a 1 here this week');
    }

    public function test_period_toggle_filters_current_vs_previous_week(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Period Stylist']);

        $this->makeEvaluation($staff, $user, 'wakrah', now(), [5, 5, 5, 5, 5, 5], 'Current week note');
        $this->makeEvaluation($staff, $user, 'wakrah', now()->subWeeks(2), [1, 1, 1, 1, 1, 1], 'Previous week note');

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index', ['period' => 'current']))
            ->assertOk()
            ->assertSee('Current week note')
            ->assertDontSee('Previous week note');

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index', ['period' => 'previous']))
            ->assertOk()
            ->assertSee('Previous week note')
            ->assertDontSee('Current week note');
    }

    public function test_dashboard_branch_filter_restricts_table_to_selected_branch(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Branch Filter Stylist']);

        $this->makeEvaluation($staff, $user, 'wakrah', now(), [5, 5, 5, 5, 5, 5], 'Wakrah branch note');
        $this->makeEvaluation($staff, $user, 'old_airport', now(), [1, 1, 1, 1, 1, 1], 'Old Airport branch note');

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index', ['branch' => 'wakrah']))
            ->assertOk()
            ->assertSee('Wakrah branch note')
            ->assertDontSee('Old Airport branch note');

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index', ['branch' => 'old_airport']))
            ->assertOk()
            ->assertSee('Old Airport branch note')
            ->assertDontSee('Wakrah branch note');

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index'))
            ->assertOk()
            ->assertSee('Wakrah branch note')
            ->assertSee('Old Airport branch note');
    }

    public function test_staff_by_branch_grouping_includes_both_branch_staff_in_each_list(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Staff::create(['name' => 'Wakrah Only Stylist', 'branch' => 'wakrah']);
        Staff::create(['name' => 'Old Airport Only Stylist', 'branch' => 'old_airport']);
        Staff::create(['name' => 'Floating Stylist', 'branch' => 'both']);

        $response = $this->actingAs($user)->get(route('kpi.evaluations.index'));

        $response->assertOk();

        $html = $response->getContent();
        preg_match('/var STAFF_BY_BRANCH = (\{.*?\});/s', $html, $matches);
        $this->assertNotEmpty($matches, 'STAFF_BY_BRANCH JSON not found in page.');

        $staffByBranch = json_decode($matches[1], true);
        $wakrahNames = array_column($staffByBranch['wakrah'], 'name');
        $oldAirportNames = array_column($staffByBranch['old_airport'], 'name');

        $this->assertContains('Wakrah Only Stylist', $wakrahNames);
        $this->assertContains('Floating Stylist', $wakrahNames);
        $this->assertNotContains('Old Airport Only Stylist', $wakrahNames);

        $this->assertContains('Old Airport Only Stylist', $oldAirportNames);
        $this->assertContains('Floating Stylist', $oldAirportNames);
        $this->assertNotContains('Wakrah Only Stylist', $oldAirportNames);
    }

    public function test_view_details_payload_includes_full_binary_breakdown(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Breakdown Stylist']);

        // cleanliness/attitude/teamwork = 5, dress_code/sales/communication = 1
        $this->makeEvaluation($staff, $user, 'old_airport', now(), [5, 1, 5, 1, 5, 1], 'Mixed performance this week');

        $response = $this->actingAs($user)->get(route('kpi.evaluations.index'));

        $response->assertOk();
        $response->assertSee('"score_cleanliness":5', false);
        $response->assertSee('"score_dress_code":1', false);
        $response->assertSee('"score_attitude":5', false);
        $response->assertSee('"score_sales":1', false);
        $response->assertSee('"score_teamwork":5', false);
        $response->assertSee('"score_communication":1', false);
        $response->assertSee('Mixed performance this week', false);
        $response->assertSee('👁️ View Details', false);
    }

    public function test_non_admin_users_do_not_see_delete_button_and_receive_403_on_delete(): void
    {
        Carbon::setTestNow(self::A_MONDAY);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $staff = Staff::create(['name' => 'Arpana', 'branch' => 'old_airport']);

        $currentEval = $this->makeEvaluation($staff, $admin, 'old_airport', now('Asia/Qatar'), [5, 5, 5, 1, 5, 5], 'This week note');
        $previousEval = $this->makeEvaluation($staff, $admin, 'old_airport', now('Asia/Qatar')->subWeeks(2), [5, 5, 5, 5, 5, 5], 'Previous week note');

        $nonAdmins = [
            User::factory()->create([
                'name' => 'Ahana Manager',
                'role' => 'manager',
                'is_active' => true,
                'permissions' => ['kpis' => 'edit'],
            ]),
            User::factory()->create([
                'name' => 'Chat Agent',
                'role' => 'agent',
                'is_active' => true,
                'permissions' => ['kpis' => 'edit'],
            ]),
            User::factory()->create([
                'name' => 'Salon Staff',
                'role' => 'staff',
                'is_active' => true,
                'permissions' => ['kpis' => 'view'],
            ]),
        ];

        $destroyUrl = route('kpi.evaluations.destroy', $currentEval);

        foreach ($nonAdmins as $user) {
            foreach (['current', 'previous'] as $period) {
                $this->actingAs($user)
                    ->get(route('kpi.evaluations.index', ['period' => $period]))
                    ->assertOk()
                    ->assertDontSee('se-delete-icon-btn')
                    ->assertDontSee('id="seDeleteConfirmModal"', false)
                    ->assertDontSee('id="viewEvalDeleteBtn"', false)
                    ->assertDontSee('data-delete-url=', false)
                    ->assertDontSee('Delete Evaluation');
            }

            $this->actingAs($user)
                ->delete($destroyUrl)
                ->assertForbidden();
        }

        $this->assertDatabaseHas('kpi_staff_evaluations', ['id' => $currentEval->id]);
        $this->assertDatabaseHas('kpi_staff_evaluations', ['id' => $previousEval->id]);
    }

    public function test_admin_can_delete_staff_evaluation_and_weekly_summary_cards_update_accurately(): void
    {
        Carbon::setTestNow(self::A_MONDAY);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $arpana = Staff::create(['name' => 'Arpana', 'branch' => 'old_airport']);
        $maya = Staff::create(['name' => 'Maya', 'branch' => 'old_airport']);

        // Both Old Airport staff evaluated this week -> "2 / 2" & "Fully Evaluated", Sales Focus weakest metric (1 of 2)
        $arpanaCurrent = $this->makeEvaluation($arpana, $admin, 'old_airport', now('Asia/Qatar'), [5, 5, 5, 1, 5, 5], 'Arpana weekly review');
        $mayaCurrent = $this->makeEvaluation($maya, $admin, 'old_airport', now('Asia/Qatar'), [5, 5, 5, 5, 5, 5], 'Maya weekly review');
        $arpanaPrev = $this->makeEvaluation($arpana, $admin, 'old_airport', now('Asia/Qatar')->subWeeks(2), [5, 5, 5, 5, 5, 5], 'Arpana older review');

        $destroyCurrentUrl = route('kpi.evaluations.destroy', $arpanaCurrent);
        $destroyPrevUrl = route('kpi.evaluations.destroy', $arpanaPrev);

        // 1. Before deletion: Admin sees delete buttons on both This Week and Previous Weeks, and card shows 2 / 2 (Fully Evaluated)
        $this->actingAs($admin)
            ->get(route('kpi.evaluations.index', ['period' => 'current', 'branch' => 'old_airport']))
            ->assertOk()
            ->assertSee('se-delete-icon-btn')
            ->assertSee('id="seDeleteConfirmModal"', false)
            ->assertSee('id="viewEvalDeleteBtn"', false)
            ->assertSee($destroyCurrentUrl, false)
            ->assertSee('2 / 2')
            ->assertSee('Fully Evaluated')
            ->assertSee('Sales Focus')
            ->assertSee('Arpana weekly review');

        $this->actingAs($admin)
            ->get(route('kpi.evaluations.index', ['period' => 'previous']))
            ->assertOk()
            ->assertSee('se-delete-icon-btn')
            ->assertSee($destroyPrevUrl, false)
            ->assertSee('Arpana older review');

        // 2. Delete Arpana's current-week evaluation -> preserves period & branch filter on redirect with flash message
        $this->actingAs($admin)
            ->delete($destroyCurrentUrl, ['period' => 'current', 'branch' => 'old_airport'])
            ->assertRedirect(route('kpi.evaluations.index', ['period' => 'current', 'branch' => 'old_airport']))
            ->assertSessionHas('success', 'Staff evaluation deleted.');

        $this->assertDatabaseMissing('kpi_staff_evaluations', ['id' => $arpanaCurrent->id]);
        $this->assertDatabaseHas('kpi_staff_evaluations', ['id' => $mayaCurrent->id]);

        // 3. Index immediately reflects updated counts: 1 / 2, 1 Remaining, no Sales Focus weakest metric, row gone
        $this->actingAs($admin)
            ->get(route('kpi.evaluations.index', ['period' => 'current', 'branch' => 'old_airport']))
            ->assertOk()
            ->assertDontSee('Arpana weekly review')
            ->assertSee('Maya weekly review')
            ->assertSee('1 / 2')
            ->assertSee('1 Remaining')
            ->assertDontSee('1 of 2 evaluations scored a 1 here this week');

        // 4. Delete previous-week evaluation -> preserves period=previous cleanly
        $this->actingAs($admin)
            ->delete($destroyPrevUrl, ['period' => 'previous'])
            ->assertRedirect(route('kpi.evaluations.index', ['period' => 'previous']))
            ->assertSessionHas('success', 'Staff evaluation deleted.');

        $this->assertDatabaseMissing('kpi_staff_evaluations', ['id' => $arpanaPrev->id]);
    }
}
