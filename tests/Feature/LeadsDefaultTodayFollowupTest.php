<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeadsDefaultTodayFollowupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Leads Admin',
            'email' => 'leads-today-admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        Service::create([
            'name' => 'HydraFacial',
            'price' => 300,
            'duration' => 60,
        ]);
    }

    public function test_fresh_leads_index_defaults_to_todays_open_followups_with_prefilled_date_and_dynamic_heading(): void
    {
        // Simulate 01:30 AM Doha time (which is 22:30 UTC previous calendar day!) to verify Asia/Qatar timezone safety
        config(['app.timezone' => 'UTC']);
        $this->travelTo(Carbon::parse('2026-11-21 01:30:00', 'Asia/Qatar'));

        $todayStr = '2026-11-21';

        $cToday = Customer::create(['name' => 'Due Today Client', 'phone' => '+97455100001']);
        $cFuture = Customer::create(['name' => 'Future Dec Client', 'phone' => '+97455100002']);
        $cPast = Customer::create(['name' => 'Past Overdue Client', 'phone' => '+97455100003']);
        $cClosed = Customer::create(['name' => 'Closed Today Client', 'phone' => '+97455100004']);

        $todayLead = Lead::create([
            'phone' => '55100001',
            'customer_id' => $cToday->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => $todayStr,
            'needful_done' => null,
        ]);

        $futureLead = Lead::create([
            'phone' => '55100002',
            'customer_id' => $cFuture->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => '2026-12-02',
            'needful_done' => null,
        ]);

        $pastLead = Lead::create([
            'phone' => '55100003',
            'customer_id' => $cPast->id,
            'category' => 'follow_up',
            'categories' => ['follow_up'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => '2026-11-18',
            'needful_done' => null,
        ]);

        // Closed lead with today's date must be excluded so default view aligns cleanly with Morning Money Brief Due Today
        $closedTodayLead = Lead::create([
            'phone' => '55100004',
            'customer_id' => $cClosed->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => $todayStr,
            'needful_done' => 'no',
        ]);

        $response = $this->actingAs($this->admin)->get(route('leads.index'));

        $response->assertOk()
            ->assertViewHas('activeTab', 'due_today')
            ->assertViewHas('activeFollowupDate', $todayStr)
            ->assertViewHas('dueTodayCount', 1)
            ->assertViewHas('leads', fn ($leads) => $leads->pluck('id')->all() === [$todayLead->id])
            ->assertSee("Today's Follow-ups (21 Nov 2026)")
            ->assertSee('value="' . $todayStr . '"', false)
            ->assertSee('id="dueTodayLeadsPill"', false)
            ->assertSee('id="morningBriefDueTodayLink"', false)
            ->assertSee('Due Today Client')
            ->assertDontSee('Closed Today Client');

        // Confirm table HTML strictly excludes non-today leads (Morning Brief / Follow-ups bell still list top open/overdue leads)
        $html = $response->getContent();
        $tableStart = strpos($html, '<table class="table leads-compact-table');
        $tableEnd = strpos($html, '</table>', (int) $tableStart);
        $tableHtml = substr($html, (int) $tableStart, (int) $tableEnd - (int) $tableStart);
        $this->assertStringContainsString('Due Today Client', $tableHtml);
        $this->assertStringNotContainsString('Future Dec Client', $tableHtml);
        $this->assertStringNotContainsString('Past Overdue Client', $tableHtml);
        $this->assertStringNotContainsString('Closed Today Client', $tableHtml);
    }

    public function test_all_leads_tab_specific_date_picker_and_alert_tabs_bypass_today_default_without_filter_trap(): void
    {
        $this->travelTo(Carbon::parse('2026-11-21 10:00:00', 'Asia/Qatar'));

        $staff = Staff::create([
            'name' => 'Sara Stylist',
            'role' => 'Esthetician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $cToday = Customer::create(['name' => 'Today Lead', 'phone' => '+97455200001']);
        $cFuture = Customer::create(['name' => 'Dec 02 Lead', 'phone' => '+97455200002']);
        $cOverdue = Customer::create(['name' => 'Overdue Lead', 'phone' => '+97455200003']);
        $cUnscheduled = Customer::create(['name' => 'Unscheduled Cancel Lead', 'phone' => '+97455200004']);
        $cBooked = Customer::create(['name' => 'Booked Client Lead', 'phone' => '+97455200005']);

        $todayLead = Lead::create([
            'phone' => '55200001',
            'customer_id' => $cToday->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => '2026-11-21',
        ]);

        $futureLead = Lead::create([
            'phone' => '55200002',
            'customer_id' => $cFuture->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => '2026-12-02',
        ]);

        $overdueLead = Lead::create([
            'phone' => '55200003',
            'customer_id' => $cOverdue->id,
            'category' => 'follow_up',
            'categories' => ['follow_up'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => '2026-11-15',
        ]);

        $unscheduledLead = Lead::create([
            'phone' => '55200004',
            'customer_id' => $cUnscheduled->id,
            'category' => 'cancel',
            'categories' => ['cancel'],
            'service_interest' => ['HydraFacial'],
            'next_followup_date' => null,
        ]);

        $appt = Appointment::create([
            'customer_id' => $cBooked->id,
            'customer_name' => $cBooked->name,
            'phone' => '55200005',
            'service_name' => 'HydraFacial',
            'appointment_datetime' => '2026-11-25 14:00:00',
            'booking_date' => '2026-11-25',
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        $bookedLead = Lead::create([
            'phone' => '55200005',
            'customer_id' => $cBooked->id,
            'appointment_id' => $appt->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['HydraFacial'],
            'needful_done' => 'booked',
            'next_followup_date' => null,
        ]);

        // 1. All Leads (?tab=all) returns leads across all dates with empty date input & heading "All Leads"
        $this->actingAs($this->admin)
            ->get(route('leads.index', ['tab' => 'all']))
            ->assertOk()
            ->assertViewHas('activeTab', 'all')
            ->assertViewHas('activeFollowupDate', null)
            ->assertViewHas('tableHeading', 'All Leads')
            ->assertViewHas('leads', fn ($leads) => $leads->count() === 5);

        // 2. Specific Next Follow-up Date (?followup_date=2026-12-02) returns only that date's leads & dynamic heading
        $dateResp = $this->actingAs($this->admin)
            ->get(route('leads.index', ['followup_date' => '2026-12-02']))
            ->assertOk()
            ->assertViewHas('activeFollowupDate', '2026-12-02')
            ->assertViewHas('tableHeading', 'Follow-ups for 02 Dec 2026')
            ->assertViewHas('leads', fn ($leads) => $leads->pluck('id')->all() === [$futureLead->id])
            ->assertSee('Follow-ups for 02 Dec 2026')
            ->assertSee('Dec 02 Lead');

        $dateHtml = $dateResp->getContent();
        $tStart = strpos($dateHtml, '<table class="table leads-compact-table');
        $tEnd = strpos($dateHtml, '</table>', (int) $tStart);
        $dateTableHtml = substr($dateHtml, (int) $tStart, (int) $tEnd - (int) $tStart);
        $this->assertStringContainsString('Dec 02 Lead', $dateTableHtml);
        $this->assertStringNotContainsString('Today Lead', $dateTableHtml);

        // 3. Overdue Follow-ups tab returns overdue leads (not limited to today)
        $this->actingAs($this->admin)
            ->get(route('leads.index', ['alert_filter' => 'overdue']))
            ->assertOk()
            ->assertViewHas('activeTab', 'overdue')
            ->assertViewHas('tableHeading', 'Overdue Follow-ups')
            ->assertViewHas('leads', fn ($leads) => $leads->pluck('id')->all() === [$overdueLead->id]);

        // 4. Needs Follow-up Date tab returns unscheduled cancel/no_show leads
        $this->actingAs($this->admin)
            ->get(route('leads.index', ['alert_filter' => 'unscheduled']))
            ->assertOk()
            ->assertViewHas('activeTab', 'unscheduled')
            ->assertViewHas('tableHeading', 'Needs Follow-up Date')
            ->assertViewHas('leads', fn ($leads) => $leads->pluck('id')->all() === [$unscheduledLead->id]);

        // 5. Booked tab returns booked leads
        $this->actingAs($this->admin)
            ->get(route('leads.index', ['alert_filter' => 'booked']))
            ->assertOk()
            ->assertViewHas('activeTab', 'booked')
            ->assertViewHas('tableHeading', 'Booked')
            ->assertViewHas('leads', fn ($leads) => $leads->pluck('id')->all() === [$bookedLead->id]);

        // 6. Updating a lead preserves active tab / date filter via return_filters
        $this->actingAs($this->admin)
            ->put(route('leads.update', $futureLead), [
                'country_code' => '974',
                'phone_number' => '55200002',
                'customer_name' => 'Dec 02 Lead Updated',
                'category' => 'follow_up',
                'service_interest' => ['HydraFacial'],
                'next_followup_date' => '2026-12-02',
                'return_filters' => [
                    'followup_date' => '2026-12-02',
                ],
            ])
            ->assertRedirect(route('leads.index', ['followup_date' => '2026-12-02']));
    }
}
