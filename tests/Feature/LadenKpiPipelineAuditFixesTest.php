<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiAgentTargetReport;
use App\Models\KpiChatEvaluation;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LadenKpiPipelineAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $areeba;
    private User $zoya;
    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin-laden-audit@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->areeba = User::factory()->create([
            'name' => 'Areeba Khan',
            'email' => 'areeba-audit@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $this->zoya = User::factory()->create([
            'name' => 'Zoya Ali',
            'email' => 'zoya-audit@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $this->receptionist = User::factory()->create([
            'name' => 'Front Desk Reception',
            'email' => 'reception-audit@laleen.qa',
            'role' => 'user',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'edit',
                'leads' => 'edit',
                'appointments' => 'edit',
            ],
        ]);
    }

    /**
     * @return array<string, array{answer: string, remark: ?string}>
     */
    private function fullPassChatAnswers(): array
    {
        $answers = [];
        foreach (array_keys(KpiChatEvaluation::QUESTIONS) as $num) {
            $answers[KpiChatEvaluation::answerKey($num)] = [
                'answer' => 'pass',
                'remark' => null,
            ];
        }

        return $answers;
    }

    public function test_cancelled_and_no_show_agent_bookings_keep_credit_walkins_excluded_and_single_day_loophole_removed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00'));

        try {
            $now = now();
            $stylist = Staff::create(['name' => 'Laden Test Stylist', 'branch' => 'old_airport', 'bookable' => true]);

            // 1. Areeba books 3 appointments: confirmed, cancelled, no_show -> all 3 count (+3)
            foreach (['confirmed', 'cancelled', 'no_show'] as $idx => $status) {
                $appt = Appointment::create([
                    'customer_name' => "Areeba Guest {$idx}",
                    'phone' => '5510000' . $idx,
                    'service_name' => 'HydraFacial',
                    'appointment_datetime' => $now->copy()->addDay()->setTime(11 + $idx, 0),
                    'booking_date' => $now->copy()->addDay()->toDateString(),
                    'start_time' => (11 + $idx) . ':00:00',
                    'staff_id' => $stylist->id,
                    'branch' => 'old_airport',
                    'booking_agent_id' => $this->areeba->id,
                    'created_by' => $this->areeba->id,
                    'status' => $status,
                    'price' => 250,
                ]);
                $appt->forceFill(['created_at' => $now])->saveQuietly();
            }

            // 2. Shared booking: created_by Areeba, booking_agent_id = Zoya (cancelled) -> +1 ONLY for Zoya, 0 for Areeba
            $sharedAppt = Appointment::create([
                'customer_name' => 'Zoya Attributed Guest',
                'phone' => '55100099',
                'service_name' => 'Manicure',
                'appointment_datetime' => $now->copy()->addDay()->setTime(16, 0),
                'booking_date' => $now->copy()->addDay()->toDateString(),
                'start_time' => '16:00:00',
                'staff_id' => $stylist->id,
                'branch' => 'wakrah',
                'booking_agent_id' => $this->zoya->id,
                'created_by' => $this->areeba->id,
                'status' => 'cancelled',
                'price' => 150,
            ]);
            $sharedAppt->forceFill(['created_at' => $now])->saveQuietly();

            // 3. Front-desk walk-in (booking_agent_id = NULL, created_by = Receptionist/Admin) -> 0 for agents & Laden
            $walkInReception = Appointment::create([
                'customer_name' => 'Salon Walk-In 1',
                'phone' => '55200001',
                'service_name' => 'Blowdry',
                'appointment_datetime' => $now->copy()->setTime(15, 0),
                'booking_date' => $now->toDateString(),
                'branch' => 'old_airport',
                'booking_agent_id' => null,
                'created_by' => $this->receptionist->id,
                'status' => 'completed',
                'price' => 180,
            ]);
            $walkInReception->forceFill(['created_at' => $now])->saveQuietly();

            $walkInAdmin = Appointment::create([
                'customer_name' => 'Salon Walk-In 2',
                'phone' => '55200002',
                'service_name' => 'Pedicure',
                'appointment_datetime' => $now->copy()->setTime(15, 30),
                'booking_date' => $now->toDateString(),
                'branch' => 'wakrah',
                'booking_agent_id' => null,
                'created_by' => $this->admin->id,
                'status' => 'confirmed',
                'price' => 160,
            ]);
            $walkInAdmin->forceFill(['created_at' => $now])->saveQuietly();

            // Assert KpiAgentTargetReport::rawShiftBookings & liveAgentScorecards
            $report = new KpiAgentTargetReport([
                'date_from' => $now->copy()->startOfDay(),
                'date_to' => $now->copy()->endOfDay(),
            ]);
            $this->assertSame(4, $report->combined()['bookings']);

            $liveCards = collect(KpiAgentTargetReport::liveAgentScorecards('2026-11'))->keyBy('id');
            $this->assertSame(3, $liveCards[$this->areeba->id]['today_bookings']);
            $this->assertSame(3, $liveCards[$this->areeba->id]['mtd_bookings']);
            $this->assertSame(1, $liveCards[$this->zoya->id]['today_bookings']);
            $this->assertSame(1, $liveCards[$this->zoya->id]['mtd_bookings']);

            // Assert KpiHubController scorecard
            $scorecard = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->viewData('scorecard');

            $this->assertSame(3, $scorecard['areeba']['today_bookings']);
            $this->assertSame(3, $scorecard['areeba']['mtd_bookings']);
            $this->assertSame(1, $scorecard['zoya']['today_bookings']);
            $this->assertSame(1, $scorecard['zoya']['mtd_bookings']);
            $this->assertSame(4, $scorecard['laden']['today_bookings_count']);
            $this->assertSame(4, $scorecard['laden']['mtd_bookings_count']);

            // 4. Single-day OR pass loophole test:
            // Add 10 more bookings today for Areeba (total 13 today vs 130 MTD target on Nov 10)
            // and 12 more bookings today for Zoya (total 13 today; Laden = 26 today vs 260 MTD target on Nov 10)
            for ($i = 1; $i <= 10; $i++) {
                $a = Appointment::create([
                    'customer_name' => "Areeba Extra {$i}",
                    'phone' => '5531' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'service_name' => 'Facial',
                    'appointment_datetime' => $now->copy()->addDays(2),
                    'booking_date' => $now->copy()->addDays(2)->toDateString(),
                    'start_time' => '12:00:00',
                    'staff_id' => $stylist->id,
                    'branch' => 'old_airport',
                    'booking_agent_id' => $this->areeba->id,
                    'created_by' => $this->areeba->id,
                    'status' => 'confirmed',
                    'price' => 200,
                ]);
                $a->forceFill(['created_at' => $now])->saveQuietly();
            }
            for ($j = 1; $j <= 12; $j++) {
                $z = Appointment::create([
                    'customer_name' => "Zoya Extra {$j}",
                    'phone' => '5532' . str_pad((string) $j, 4, '0', STR_PAD_LEFT),
                    'service_name' => 'Nails',
                    'appointment_datetime' => $now->copy()->addDays(2),
                    'booking_date' => $now->copy()->addDays(2)->toDateString(),
                    'start_time' => '13:00:00',
                    'staff_id' => $stylist->id,
                    'branch' => 'wakrah',
                    'booking_agent_id' => $this->zoya->id,
                    'created_by' => $this->zoya->id,
                    'status' => 'no_show',
                    'price' => 140,
                ]);
                $z->forceFill(['created_at' => $now])->saveQuietly();
            }

            $loopholeScorecard = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->viewData('scorecard');

            $this->assertSame(26, $loopholeScorecard['laden']['today_bookings_count']);
            $this->assertSame(26, $loopholeScorecard['laden']['mtd_bookings_count']);
            $this->assertSame(260, $loopholeScorecard['laden']['mtd_target_bookings']);
            $this->assertFalse($loopholeScorecard['laden']['booking_passing'], 'Hitting 26 on a single day must NOT pass a 26/260 MTD deficit');
            $this->assertFalse($loopholeScorecard['areeba']['booking_passing'], 'Hitting 13 on a single day must NOT pass a 13/130 MTD deficit');
            $this->assertFalse($loopholeScorecard['zoya']['booking_passing'], 'Hitting 13 on a single day must NOT pass a 13/130 MTD deficit');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_leads_and_ad_leads_modules_are_completely_separated_sharing_only_customer_profile(): void
    {
        Service::create([
            'name' => 'HydraFacial',
            'price' => 350,
            'duration' => 60,
        ]);

        // Part A: AdLeadEntry store / update / destroy NEVER creates or mutates rows in `leads`
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => now()->toDateString(),
                'customer_name' => 'Mariam Al-Kuwari',
                'country_code' => '+974',
                'phone_number' => '5544 1234',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
                'remarks' => 'Meta Ad Lead',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $this->assertSame(0, Lead::count(), 'Logging an AdLeadEntry must NEVER create a Lead row');
        $adEntry = AdLeadEntry::where('phone', '55441234')->firstOrFail();
        $this->assertNotNull($adEntry->customer_id);
        $this->assertNull($adEntry->lead_id);
        $sharedCustomer = Customer::findOrFail($adEntry->customer_id);
        $this->assertSame('55441234', $sharedCustomer->phone);

        // Update AdLeadEntry -> still 0 rows in `leads`
        $this->actingAs($this->admin)
            ->put(route('kpi.ad-leads.update', $adEntry), [
                'date' => now()->toDateString(),
                'customer_name' => 'Mariam Al-Kuwari',
                'country_code' => '974',
                'phone_number' => '55441234',
                'category' => 'Hair Treatment',
                'ticket_amount' => 450,
                'branch' => 'old_airport',
                'remarks' => 'Updated manual ticket',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $this->assertSame(0, Lead::count(), 'Updating an AdLeadEntry must NEVER create a Lead row');

        // Delete AdLeadEntry -> does not touch `leads`, Customer remains intact
        $this->actingAs($this->admin)
            ->delete(route('kpi.ad-leads.destroy', $adEntry))
            ->assertRedirect(route('kpi.ads.index'));

        $this->assertSame(0, AdLeadEntry::count());
        $this->assertSame(0, Lead::count());
        $this->assertNotNull(Customer::find($sharedCustomer->id));

        // Part B: Lead store / update in `/leads` NEVER creates or mutates rows in `ad_lead_entries`
        $this->actingAs($this->admin)
            ->post(route('leads.store'), [
                'country_code' => '974',
                'phone_number' => '5544 1234',
                'customer_name' => 'Mariam Al-Kuwari',
                'category' => 'inquiry',
                'source' => 'Instagram',
                'service_interest' => ['HydraFacial'],
                'next_followup_date' => now()->addDay()->toDateString(),
                'customer_remarks' => 'Instagram DM in Leads module',
            ])
            ->assertRedirect();

        $this->assertSame(1, Lead::count());
        $this->assertSame(0, AdLeadEntry::count(), 'Creating a Lead (even with source=Instagram) must NEVER insert into ad_lead_entries');

        $lead = Lead::firstOrFail();
        $this->assertSame($sharedCustomer->id, $lead->customer_id, 'Both modules share the canonical Customer profile');

        // Log an independent AdLeadEntry for the same customer, then edit the Lead -> AdLeadEntry stays untouched (lead_id remains null)
        $independentAdEntry = AdLeadEntry::create([
            'date' => now()->toDateString(),
            'customer_id' => $sharedCustomer->id,
            'customer_name' => $sharedCustomer->name,
            'phone' => $sharedCustomer->phone,
            'category' => 'Facial',
            'ticket_amount' => 0,
            'branch' => null,
        ]);

        $this->actingAs($this->admin)
            ->put(route('leads.update', $lead), [
                'country_code' => '974',
                'phone_number' => '55441234',
                'customer_name' => 'Mariam Al-Kuwari',
                'category' => 'inquiry',
                'service_interest' => ['HydraFacial'],
                'next_followup_date' => now()->addDays(2)->toDateString(),
                'customer_remarks' => 'Updated remarks in Leads',
            ])
            ->assertRedirect(route('leads.index'));

        $independentAdEntry->refresh();
        $this->assertNull($independentAdEntry->lead_id, 'Editing a Lead must not mutate ad_lead_entries.lead_id');
    }

    public function test_chat_evaluation_write_routes_are_admin_only_and_index_filters_mtd_by_default(): void
    {
        $reportThisMonth = KpiChatEvaluation::create([
            'agent_id' => $this->areeba->id,
            'evaluator_id' => $this->admin->id,
            'evaluation_date' => now()->toDateString(),
            'answers' => [],
            'total_score' => 153,
            'percentage' => 90.00,
        ]);

        $prevMonthDate = now()->copy()->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString();
        $reportPrevMonth = KpiChatEvaluation::create([
            'agent_id' => $this->zoya->id,
            'evaluator_id' => $this->admin->id,
            'evaluation_date' => $prevMonthDate,
            'answers' => [],
            'total_score' => 119,
            'percentage' => 70.00,
        ]);

        // Non-admin user with `kpis => edit` can VIEW index & show, without seeing write buttons
        $indexResp = $this->actingAs($this->receptionist)->get(route('kpi.chat-eval.index'));
        $indexResp->assertOk();
        $indexResp->assertDontSee('New Evaluation');
        $indexResp->assertSee($this->areeba->name);
        $indexResp->assertDontSee($this->zoya->name); // Previous month excluded from default current-month MTD view

        // Viewing previous month via ?month=YYYY-MM shows Zoya's report
        $prevMonthParam = Carbon::parse($prevMonthDate)->format('Y-m');
        $this->actingAs($this->receptionist)
            ->get(route('kpi.chat-eval.index', ['month' => $prevMonthParam]))
            ->assertOk()
            ->assertSee($this->zoya->name)
            ->assertDontSee($this->areeba->name);

        $showResp = $this->actingAs($this->receptionist)->get(route('kpi.chat-eval.show', $reportThisMonth));
        $showResp->assertOk();
        $showResp->assertDontSee('Edit Report');

        // Non-admin receives 403 on all write/form endpoints (create, store, edit, update, destroy)
        $this->actingAs($this->receptionist)->get(route('kpi.chat-eval.create'))->assertForbidden();
        $this->actingAs($this->receptionist)->post(route('kpi.chat-eval.store'), [
            'agent_id' => $this->areeba->id,
            'evaluation_date' => now()->toDateString(),
            'answers' => $this->fullPassChatAnswers(),
        ])->assertForbidden();
        $this->actingAs($this->receptionist)->get(route('kpi.chat-eval.edit', $reportThisMonth))->assertForbidden();
        $this->actingAs($this->receptionist)->put(route('kpi.chat-eval.update', $reportThisMonth), [
            'agent_id' => $this->areeba->id,
            'evaluation_date' => now()->toDateString(),
            'answers' => $this->fullPassChatAnswers(),
        ])->assertForbidden();
        $this->actingAs($this->receptionist)->delete(route('kpi.chat-eval.destroy', $reportThisMonth))->assertForbidden();

        // Admin succeeds on create & store, and 1 graded evaluation >= 85% passes Card 2 with no minimum sample quota
        $this->actingAs($this->admin)->get(route('kpi.chat-eval.create'))->assertOk();
        $hubScorecard = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => now()->format('Y-m')]))
            ->viewData('scorecard');

        $this->assertSame(1, $hubScorecard['laden']['chat_evals_count']);
        $this->assertSame(90.0, (float) $hubScorecard['laden']['chat_eval_avg_pct']);
        $this->assertTrue($hubScorecard['laden']['chat_passing']);
    }

    public function test_lead_conversion_card_passes_purely_on_conversion_rate_and_renders_ratio_and_progress_bars(): void
    {
        $now = now();

        $activeAppt = Appointment::create([
            'customer_name' => 'Booked Lead Client',
            'phone' => '55600001',
            'service_name' => 'Facial',
            'appointment_datetime' => $now->copy()->addDay(),
            'booking_date' => $now->copy()->addDay()->toDateString(),
            'branch' => 'old_airport',
            'booking_agent_id' => $this->areeba->id,
            'created_by' => $this->areeba->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        // 1 booked out of 2 leads = 50.0% (>= 30.0% target), while 2nd lead is BOTH unassigned and overdue
        Lead::create([
            'phone' => '55600001',
            'appointment_id' => $activeAppt->id,
            'assigned_agent_id' => $this->areeba->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'needful_done' => 'booked',
        ]);
        Lead::create([
            'phone' => '55600002',
            'assigned_agent_id' => null,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'next_followup_date' => $now->copy()->subDays(3)->toDateString(),
            'needful_done' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => $now->format('Y-m')]));
        $response->assertOk();

        $scorecard = $response->viewData('scorecard');
        $this->assertSame(50.0, (float) $scorecard['laden']['lead_conversion_pct']);
        $this->assertSame(100.0, (float) $scorecard['laden']['lead_conv_achievement_pct']);
        $this->assertTrue($scorecard['laden']['lead_conv_passing'], 'Unassigned/Overdue counts must not fail Lead Conversion Rate');
        $this->assertSame(1, $scorecard['laden']['unassigned_leads_count']);
        $this->assertSame(1, $scorecard['laden']['overdue_followups_count']);

        $html = $response->getContent();
        $this->assertStringContainsString('(1/2)', $html);
        $this->assertStringContainsString('50.0% Lead Conv', $html);
        $this->assertStringContainsString('start_date=' . $scorecard['period_start'], $html);
        $this->assertStringContainsString('complaints_from=' . $scorecard['period_start'], $html);
    }

    public function test_complaint_sla_filters_strictly_by_complaint_date_allows_open_under_48h_and_catches_backdated_breaches(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 18:00:00'));

        try {
            $now = now();

            // 1. October complaint_date logged/updated in November -> MUST BE EXCLUDED from November MTD
            $octComplaint = StaffComplaint::create([
                'complaint_date' => '2026-10-28',
                'branch' => 'old_airport',
                'customer_name' => 'October Incident',
                'customer_phone' => '55700001',
                'category' => 'Service Quality',
                'description' => 'Late October complaint logged in Nov',
                'what_happened' => 'Late October complaint logged in Nov',
                'is_staff_mistake' => null,
            ]);
            $octComplaint->forceFill([
                'created_at' => $now->copy()->subHours(72),
                'updated_at' => $now->copy()->subHours(72),
            ])->saveQuietly();

            // 2. Open November complaint only 10 hours old (< 48h) -> MUST NOT fail or penalize SLA (100% PASS)
            $freshOpenComplaint = StaffComplaint::create([
                'complaint_date' => '2026-11-12',
                'complaint_time' => '08:00',
                'branch' => 'wakrah',
                'customer_name' => 'Fresh Open Guest',
                'customer_phone' => '55700002',
                'category' => 'Wait Time',
                'description' => 'Logged 10h ago, under investigation',
                'what_happened' => 'Logged 10h ago, under investigation',
                'is_staff_mistake' => null,
            ]);
            $freshOpenComplaint->forceFill([
                'created_at' => Carbon::parse('2026-11-12 08:00:00'),
                'updated_at' => Carbon::parse('2026-11-12 08:00:00'),
            ])->saveQuietly();

            $passResp = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
            $passScorecard = $passResp->viewData('scorecard');

            $this->assertSame(1, $passScorecard['laden']['total_complaints_count'], 'October complaint_date must not bleed into November MTD');
            $this->assertSame(1, $passScorecard['laden']['open_complaints_count']);
            $this->assertSame(1, $passScorecard['laden']['open_within_48h_count']);
            $this->assertSame(0, $passScorecard['laden']['breaching_open_count']);
            $this->assertSame(100.0, (float) $passScorecard['laden']['complaint_sla_pct']);
            $this->assertTrue($passScorecard['laden']['complaint_passing'], 'Open complaint < 48h old must pass SLA');

            // 3. Backdated November complaint: incident on Nov 03 at 12:00, logged & resolved on Nov 07 at 14:00 (98h > 48h) -> BREACH!
            $backdatedBreach = StaffComplaint::create([
                'complaint_date' => '2026-11-03',
                'complaint_time' => '12:00',
                'branch' => 'old_airport',
                'customer_name' => 'Backdated Breach Guest',
                'customer_phone' => '55700003',
                'category' => 'Service Quality',
                'description' => 'Incident Nov 3, resolved Nov 7',
                'what_happened' => 'Incident Nov 3, resolved Nov 7',
                'is_staff_mistake' => true,
            ]);
            $backdatedBreach->forceFill([
                'created_at' => Carbon::parse('2026-11-07 13:00:00'),
                'resolved_at' => Carbon::parse('2026-11-07 14:00:00'),
                'updated_at' => Carbon::parse('2026-11-07 14:00:00'),
            ])->saveQuietly();

            $failResp = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
            $failScorecard = $failResp->viewData('scorecard');

            $this->assertSame(2, $failScorecard['laden']['total_complaints_count']);
            $this->assertSame(1, $failScorecard['laden']['breaching_resolved_count']);
            $this->assertSame(50.0, (float) $failScorecard['laden']['complaint_sla_pct']);
            $this->assertFalse($failScorecard['laden']['complaint_passing'], 'Backdated complaint resolved > 48h after complaint_date must breach SLA');
            $this->assertStringContainsString('orb-ruby', $failResp->getContent());
        } finally {
            Carbon::setTestNow();
        }
    }
}
