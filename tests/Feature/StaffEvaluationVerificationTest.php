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

    public function test_index_page_renders_weekly_cards_and_toggle(): void
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
            ->assertSee('Previous Weeks');
    }

    public function test_store_saves_binary_scores_and_branch_and_computes_average(): void
    {
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
}
