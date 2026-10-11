<?php

namespace Tests\Feature;

use App\Models\KpiStaffEvaluation;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffEvaluationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_renders(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('kpi.evaluations.index'))
            ->assertOk()
            ->assertSee('Salon Staff Evaluation')
            ->assertSee('The Hormozi Lens', false)
            ->assertSee('Frictionless Reset', false);
    }

    public function test_store_saves_scores_and_computes_average(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Verification Stylist']);

        $payload = [
            'staff_id' => $staff->id,
            'evaluation_date' => now()->format('Y-m-d'),
            'score_cleanliness' => 5,
            'score_dress_code' => 4,
            'score_attitude' => 3,
            'score_sales' => 2,
            'score_teamwork' => 5,
            'score_communication' => 4,
            'leader_notes' => 'Great hustle on retail, needs polish on dress code.',
        ];

        $this->actingAs($user)
            ->post(route('kpi.evaluations.store'), $payload)
            ->assertRedirect(route('kpi.evaluations.index'));

        $this->assertDatabaseCount('kpi_staff_evaluations', 1);

        $saved = KpiStaffEvaluation::first();

        $this->assertSame(5, $saved->score_cleanliness);
        $this->assertSame(4, $saved->score_dress_code);
        $this->assertSame(3, $saved->score_attitude);
        $this->assertSame(2, $saved->score_sales);
        $this->assertSame(5, $saved->score_teamwork);
        $this->assertSame(4, $saved->score_communication);
        $this->assertSame($staff->id, $saved->staff_id);
        $this->assertSame($user->id, $saved->evaluator_id);
        $this->assertEquals(3.83, (float) $saved->overall_average);
    }

    public function test_store_rejects_out_of_range_scores(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $staff = Staff::create(['name' => 'Out Of Range Stylist']);

        $payload = [
            'staff_id' => $staff->id,
            'evaluation_date' => now()->format('Y-m-d'),
            'score_cleanliness' => 6,
            'score_dress_code' => 4,
            'score_attitude' => 3,
            'score_sales' => 2,
            'score_teamwork' => 5,
            'score_communication' => 4,
        ];

        $this->actingAs($user)
            ->post(route('kpi.evaluations.store'), $payload)
            ->assertSessionHasErrors('score_cleanliness');

        $this->assertDatabaseCount('kpi_staff_evaluations', 0);
    }
}
