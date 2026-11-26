<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiAgentTargetReport;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MandatoryBookingAgentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $areeba;
    private User $zoya;
    private User $otherAgent;
    private User $manager;
    private Staff $stylist;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Boss',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->otherAgent = User::factory()->create([
            'name' => 'Nadia Youssef',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $this->areeba = User::factory()->create([
            'name' => 'Areeba Khan',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'calendar' => 'edit'],
        ]);

        $this->zoya = User::factory()->create([
            'name' => 'Zoya Ali',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'calendar' => 'edit'],
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Areeba Manager',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'calendar' => 'edit'],
        ]);

        $this->service = Service::create([
            'name' => 'Blowdry',
            'price' => 150,
            'duration' => 60,
        ]);

        $this->stylist = Staff::create([
            'name' => 'Maya Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->stylist->services()->attach($this->service->id);
    }

    public function test_enhanced_calendar_renders_required_booking_agent_dropdown_in_exact_order(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('appointments.calendar'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Booking Agent <span class="text-danger">*</span>', $html);
        $this->assertStringContainsString('id="bookAgentError"', $html);
        $this->assertStringContainsString('Please select a Booking Agent (Salon Staff, Areeba, or Zoya) before saving the appointment.', $html);

        // Extract the #bookAgent <select> block and verify exact option order
        $matched = preg_match('/<select[^>]*id="bookAgent"[^>]*>(.*?)<\/select>/s', $html, $selectMatch);
        $this->assertSame(1, $matched, '#bookAgent select should be present');

        preg_match_all('/<option\s+value="([^"]*)"[^>]*>\s*([^<]+?)\s*<\/option>/s', $selectMatch[1], $optMatches, PREG_SET_ORDER);
        $renderedOptions = array_map(fn ($m) => [$m[1], trim($m[2])], $optMatches);

        $this->assertSame([
            ['', '-- Select Agent --'],
            [(string) $this->areeba->id, 'Areeba'],
            [(string) $this->zoya->id, 'Zoya'],
            ['salon_staff', 'Salon Staff'],
        ], $renderedOptions);

        // When logged in as Chat Agent Areeba, the calendar auto-selects her own agent value
        $agentHtml = $this->actingAs($this->areeba)
            ->get(route('appointments.calendar'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('const FB_AUTH_DEFAULT_AGENT_VALUE = "' . $this->areeba->id . '";', $agentHtml);
    }

    public function test_store_and_update_reject_missing_or_invalid_booking_agent_with_422(): void
    {
        $expectedError = 'Please select a Booking Agent (Salon Staff, Areeba, or Zoya) before saving.';

        $basePayload = [
            'customer_name' => 'No Agent Client',
            'phone' => '+97455770011',
            'appointment_datetime' => '2026-11-24 12:00:00',
            'service_name' => ['Blowdry'],
            'branch' => 'old_airport',
            'staff_id' => $this->stylist->id,
            'price' => 150,
        ];

        // 1. Omitted booking_agent_id -> 422
        $this->actingAs($this->admin)
            ->postJson(route('appointments.store'), $basePayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['booking_agent_id'])
            ->assertJsonPath('errors.booking_agent_id.0', $expectedError);

        // 2. Empty string on checkout flow -> 422
        $this->actingAs($this->admin)
            ->postJson(route('appointments.store'), array_merge($basePayload, [
                'booking_agent_id' => '',
                'then' => 'checkout',
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.booking_agent_id.0', $expectedError);

        // 3. Empty string on unscheduled queue flow -> 422
        $this->actingAs($this->admin)
            ->postJson(route('appointments.store'), array_merge($basePayload, [
                'booking_agent_id' => '',
                'unscheduled' => 1,
                'booking_date' => '2026-11-24',
                'appointment_datetime' => null,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.booking_agent_id.0', $expectedError);

        // 4. Non-agent user (manager) -> 422
        $this->actingAs($this->admin)
            ->postJson(route('appointments.store'), array_merge($basePayload, [
                'booking_agent_id' => $this->manager->id,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.booking_agent_id.0', $expectedError);

        // 5. Disallowed agent (Nadia Youssef) -> 422
        $this->actingAs($this->admin)
            ->postJson(route('appointments.store'), array_merge($basePayload, [
                'booking_agent_id' => $this->otherAgent->id,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.booking_agent_id.0', $expectedError);

        $this->assertSame(0, Appointment::count());

        // 6. Update with explicitly cleared booking_agent_id -> 422
        $validRes = $this->actingAs($this->admin)
            ->postJson(route('appointments.store'), array_merge($basePayload, [
                'booking_agent_id' => $this->areeba->id,
            ]))
            ->assertCreated();
        $appt = Appointment::firstOrFail();

        $this->actingAs($this->admin)
            ->putJson(route('appointments.update', $appt), [
                'customer_name' => 'No Agent Client',
                'phone' => '+97455770011',
                'appointment_datetime' => '2026-11-24 13:00:00',
                'service_name' => ['Blowdry'],
                'branch' => 'old_airport',
                'staff_id' => $this->stylist->id,
                'booking_agent_id' => '',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.booking_agent_id.0', $expectedError);

        $this->assertSame($this->areeba->id, $appt->fresh()->booking_agent_id);
    }

    public function test_salon_staff_areeba_and_zoya_persist_update_agent_kpis_and_trigger_ad_attribution_and_profiles(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 11:00:00', 'Asia/Qatar'));

        try {
            $usersBefore = User::count();

            // Seed 3 Ad Lead Entries to verify 14-day attribution works across all 3 Booking Agent choices
            $adSalon = AdLeadEntry::create([
                'date' => '2026-11-20',
                'customer_name' => 'Walkin Ad Client',
                'phone' => '55771001',
                'category' => 'Blowdry',
                'ticket_amount' => 0,
            ]);
            $adAreeba = AdLeadEntry::create([
                'date' => '2026-11-20',
                'customer_name' => 'Areeba Ad Client',
                'phone' => '55771002',
                'category' => 'Blowdry',
                'ticket_amount' => 0,
            ]);
            $adZoya = AdLeadEntry::create([
                'date' => '2026-11-20',
                'customer_name' => 'Zoya Ad Client',
                'phone' => '55771003',
                'category' => 'Blowdry',
                'ticket_amount' => 0,
            ]);

            // 1. Save with Salon Staff
            $this->actingAs($this->admin)
                ->postJson(route('appointments.store'), [
                    'customer_name' => 'Walkin Ad Client',
                    'phone' => '+97455771001',
                    'appointment_datetime' => '2026-11-24 10:00:00',
                    'service_name' => ['Blowdry'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->stylist->id,
                    'booking_agent_id' => 'salon_staff',
                    'price' => 150,
                ])
                ->assertCreated();

            // 2. Save with Areeba
            $this->actingAs($this->areeba)
                ->postJson(route('appointments.store'), [
                    'customer_name' => 'Areeba Ad Client',
                    'phone' => '+97455771002',
                    'appointment_datetime' => '2026-11-24 12:00:00',
                    'service_name' => ['Blowdry'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->stylist->id,
                    'booking_agent_id' => $this->areeba->id,
                    'price' => 150,
                ])
                ->assertCreated();

            // 3. Save with Zoya
            $this->actingAs($this->zoya)
                ->postJson(route('appointments.store'), [
                    'customer_name' => 'Zoya Ad Client',
                    'phone' => '+97455771003',
                    'appointment_datetime' => '2026-11-24 14:00:00',
                    'service_name' => ['Blowdry'],
                    'branch' => 'old_airport',
                    'staff_id' => $this->stylist->id,
                    'booking_agent_id' => $this->zoya->id,
                    'price' => 150,
                ])
                ->assertCreated();

            // Zero fake User rows created for Salon Staff
            $this->assertSame($usersBefore, User::count());

            $salonAppt = Appointment::where('customer_name', 'Walkin Ad Client')->firstOrFail();
            $areebaAppt = Appointment::where('customer_name', 'Areeba Ad Client')->firstOrFail();
            $zoyaAppt = Appointment::where('customer_name', 'Zoya Ad Client')->firstOrFail();

            // Verify persisted columns + reload payload via GET /appointments/{id}
            $this->assertNull($salonAppt->booking_agent_id);
            $this->assertSame(Appointment::BOOKING_AGENT_SALON_STAFF, $salonAppt->booking_agent_type);
            $this->actingAs($this->admin)
                ->getJson(route('appointments.show', $salonAppt))
                ->assertOk()
                ->assertJson([
                    'booking_agent_id' => null,
                    'booking_agent_type' => 'salon_staff',
                    'booking_agent_label' => 'Salon Staff',
                    'agent_name' => 'Salon Staff',
                ]);

            $this->assertSame($this->areeba->id, $areebaAppt->booking_agent_id);
            $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $areebaAppt->booking_agent_type);
            $this->actingAs($this->admin)
                ->getJson(route('appointments.show', $areebaAppt))
                ->assertOk()
                ->assertJson([
                    'booking_agent_id' => $this->areeba->id,
                    'booking_agent_type' => 'agent',
                    'booking_agent_label' => 'Areeba',
                    'agent_name' => 'Areeba',
                ]);

            $this->assertSame($this->zoya->id, $zoyaAppt->booking_agent_id);
            $this->assertSame(Appointment::BOOKING_AGENT_TYPE_AGENT, $zoyaAppt->booking_agent_type);
            $this->actingAs($this->admin)
                ->getJson(route('appointments.show', $zoyaAppt))
                ->assertOk()
                ->assertJson([
                    'booking_agent_id' => $this->zoya->id,
                    'booking_agent_type' => 'agent',
                    'booking_agent_label' => 'Zoya',
                    'agent_name' => 'Zoya',
                ]);

            // Verify automatic Customer profile creation for all 3
            $this->assertNotNull($salonAppt->customer_id);
            $this->assertNotNull($areebaAppt->customer_id);
            $this->assertNotNull($zoyaAppt->customer_id);
            $this->assertNotNull(Customer::find($salonAppt->customer_id));
            $this->assertNotNull(Customer::find($areebaAppt->customer_id));
            $this->assertNotNull(Customer::find($zoyaAppt->customer_id));

            // Verify 14-day Ad Lead attribution triggered cleanly for all 3 options
            $this->assertTrue($adSalon->fresh()->isBooked());
            $this->assertSame(150.0, (float) $adSalon->fresh()->ticket_amount);
            $this->assertTrue($adAreeba->fresh()->isBooked());
            $this->assertSame(150.0, (float) $adAreeba->fresh()->ticket_amount);
            $this->assertTrue($adZoya->fresh()->isBooked());
            $this->assertSame(150.0, (float) $adZoya->fresh()->ticket_amount);

            // Verify Agent KPI counts: Areeba = 1, Zoya = 1, Salon Staff = 0 agent credit (total Laden = 2)
            $scorecard = $this->actingAs($this->admin)
                ->get(route('kpi.hub', ['month' => '2026-11']))
                ->viewData('scorecard');

            $this->assertSame(1, $scorecard['areeba']['today_bookings']);
            $this->assertSame(1, $scorecard['areeba']['mtd_bookings']);
            $this->assertSame(1, $scorecard['zoya']['today_bookings']);
            $this->assertSame(1, $scorecard['zoya']['mtd_bookings']);
            $this->assertSame(2, $scorecard['laden']['today_bookings_count']);
            $this->assertSame(2, $scorecard['laden']['mtd_bookings_count']);

            $liveCards = collect(KpiAgentTargetReport::liveAgentScorecards('2026-11'))->keyBy('id');
            $this->assertSame(1, $liveCards[$this->areeba->id]['today_bookings']);
            $this->assertSame(1, $liveCards[$this->zoya->id]['today_bookings']);

            // Verify Bookings list renders "Salon Staff", "Areeba", and "Zoya" cleanly
            $this->actingAs($this->admin)
                ->get(route('appointments.index', ['period' => 'all_time']))
                ->assertOk()
                ->assertSee('Salon Staff')
                ->assertSee('Areeba')
                ->assertSee('Zoya');
        } finally {
            Carbon::setTestNow();
        }
    }
}
