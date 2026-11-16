<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsPendingFollowupsBellTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customerServiceLead;
    private User $agentZoya;
    private User $agentAreeba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Owner',
            'email' => 'admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->customerServiceLead = User::factory()->create([
            'name' => 'Laden',
            'email' => 'laden@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);

        $this->agentZoya = User::factory()->create([
            'name' => 'Zoya Khan',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);

        $this->agentAreeba = User::factory()->create([
            'name' => 'Areeba Ali',
            'email' => 'areeba@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);
    }

    public function test_auto_opening_bottom_right_followup_toast_markup_is_completely_removed_and_quiet_zero_state_renders(): void
    {
        $response = $this->actingAs($this->admin)->get(route('leads.index'));

        $response->assertOk()
            ->assertDontSee('id="followupPopup"', false)
            ->assertDontSee('popup-box', false)
            ->assertDontSee('showFollowupNotification', false)
            ->assertDontSee('due for follow-up today', false)
            ->assertSee('id="pendingFollowupsBellBtn"', false)
            ->assertSee('id="pendingFollowupsDropdown"', false)
            ->assertSee('followupOpen: false', false)
            ->assertSee('@click.outside="followupOpen = false"', false)
            ->assertSee('@keydown.escape.window="followupOpen = false"', false)
            ->assertViewHas('pendingFollowupsCount', 0)
            ->assertDontSee('id="sidebarLeadsFollowupBadge"', false);
    }

    public function test_followups_bell_badge_dropdown_and_sidebar_pill_display_accurate_role_scoped_counts(): void
    {
        $c1 = Customer::create(['name' => 'Amina Al-Thani', 'phone' => '55100001']);
        $c2 = Customer::create(['name' => 'Sara Al-Kuwari', 'phone' => '55100002']);
        $c3 = Customer::create(['name' => 'Maha Al-Sulaiti', 'phone' => '55100003']);
        $c4 = Customer::create(['name' => 'Noor Al-Misnad', 'phone' => '55100004']);
        $c5 = Customer::create(['name' => 'Future Client', 'phone' => '55100005']);
        $c6 = Customer::create(['name' => 'Closed Client', 'phone' => '55100006']);

        // 1. Assigned to Zoya: Due Today
        Lead::create([
            'phone' => '55100001',
            'customer_id' => $c1->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'inquiry',
            'service_interest' => ['Hydra Facial'],
            'needful_done' => null,
            'next_followup_date' => today(),
        ]);

        // 2. Assigned to Zoya: Overdue (2 days ago)
        Lead::create([
            'phone' => '55100002',
            'customer_id' => $c2->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'follow_up',
            'service_interest' => ['Balayage', 'Blowdry'],
            'needful_done' => 'yes',
            'next_followup_date' => today()->subDays(2),
        ]);

        // 3. Assigned to Areeba: Due Today
        Lead::create([
            'phone' => '55100003',
            'customer_id' => $c3->id,
            'assigned_agent_id' => $this->agentAreeba->id,
            'category' => 'inquiry',
            'service_interest' => ['Keratin'],
            'needful_done' => null,
            'next_followup_date' => today(),
        ]);

        // 4. Unassigned: Overdue (1 day ago)
        Lead::create([
            'phone' => '55100004',
            'customer_id' => $c4->id,
            'assigned_agent_id' => null,
            'category' => 'no_show',
            'service_interest' => ['Russian Manicure'],
            'needful_done' => null,
            'next_followup_date' => today()->subDay(),
        ]);

        // 5. Excluded: Future follow-up (tomorrow, assigned to Zoya)
        Lead::create([
            'phone' => '55100005',
            'customer_id' => $c5->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'inquiry',
            'service_interest' => ['Pedicure'],
            'needful_done' => null,
            'next_followup_date' => today()->addDay(),
        ]);

        // 6. Excluded: Closed ('booked') lead due today
        Lead::create([
            'phone' => '55100006',
            'customer_id' => $c6->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'inquiry',
            'service_interest' => ['Haircut'],
            'needful_done' => 'booked',
            'next_followup_date' => today(),
        ]);

        // A. Admin sees full queue: 4 total (2 due today, 2 overdue) + sidebar badge = 4
        $adminRes = $this->actingAs($this->admin)->get(route('leads.index'));
        $adminRes->assertOk()
            ->assertViewHas('pendingFollowupsCount', 4)
            ->assertViewHas('pendingDueTodayCount', 2)
            ->assertViewHas('pendingOverdueCount', 2)
            ->assertSee('2 due today &middot; 2 overdue', false)
            ->assertSee('Amina Al-Thani')
            ->assertSee('Sara Al-Kuwari')
            ->assertSee('Maha Al-Sulaiti')
            ->assertSee('Noor Al-Misnad')
            ->assertSee('+974 55100001')
            ->assertSee('Hydra Facial')
            ->assertSee('Due Today')
            ->assertSee('Overdue')
            ->assertSee('Filter Table to Pending Follow-ups')
            ->assertSee('id="sidebarLeadsFollowupBadge"', false);

        // B. Customer Service Lead (Laden) also sees full queue: 4 total (2 due today, 2 overdue)
        $csLeadRes = $this->actingAs($this->customerServiceLead)->get(route('leads.index'));
        $csLeadRes->assertOk()
            ->assertViewHas('pendingFollowupsCount', 4)
            ->assertViewHas('pendingDueTodayCount', 2)
            ->assertViewHas('pendingOverdueCount', 2);

        // C. Individual Chat Agent Zoya sees strictly her assigned leads: 2 total (1 due today, 1 overdue)
        $zoyaRes = $this->actingAs($this->agentZoya)->get(route('leads.index'));
        $zoyaRes->assertOk()
            ->assertViewHas('pendingFollowupsCount', 2)
            ->assertViewHas('pendingDueTodayCount', 1)
            ->assertViewHas('pendingOverdueCount', 1)
            ->assertSee('1 due today &middot; 1 overdue', false);

        // D. Individual Chat Agent Areeba sees strictly her assigned lead: 1 total (1 due today, 0 overdue)
        $areebaRes = $this->actingAs($this->agentAreeba)->get(route('leads.index'));
        $areebaRes->assertOk()
            ->assertViewHas('pendingFollowupsCount', 1)
            ->assertViewHas('pendingDueTodayCount', 1)
            ->assertViewHas('pendingOverdueCount', 0)
            ->assertSee('1 due today &middot; 0 overdue', false);
    }

    public function test_filtering_leads_table_to_pending_followups_returns_exact_matching_leads_per_role(): void
    {
        $cZoyaOverdue = Customer::create(['name' => 'Zoya Overdue Client', 'phone' => '66100001']);
        $cZoyaDueToday = Customer::create(['name' => 'Zoya Today Client', 'phone' => '66100002']);
        $cAreebaDueToday = Customer::create(['name' => 'Areeba Today Client', 'phone' => '66100003']);
        $cFuture = Customer::create(['name' => 'Future Followup Client', 'phone' => '66100004']);
        $cLost = Customer::create(['name' => 'Lost Client', 'phone' => '66100005']);

        $zoyaOverdue = Lead::create([
            'phone' => '66100001',
            'customer_id' => $cZoyaOverdue->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'follow_up',
            'service_interest' => ['Facial'],
            'needful_done' => null,
            'next_followup_date' => today()->subDays(3),
        ]);

        $zoyaToday = Lead::create([
            'phone' => '66100002',
            'customer_id' => $cZoyaDueToday->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'inquiry',
            'service_interest' => ['Haircut'],
            'needful_done' => 'yes',
            'next_followup_date' => today(),
        ]);

        $areebaToday = Lead::create([
            'phone' => '66100003',
            'customer_id' => $cAreebaDueToday->id,
            'assigned_agent_id' => $this->agentAreeba->id,
            'category' => 'inquiry',
            'service_interest' => ['Manicure'],
            'needful_done' => null,
            'next_followup_date' => today(),
        ]);

        Lead::create([
            'phone' => '66100004',
            'customer_id' => $cFuture->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'inquiry',
            'service_interest' => ['Blowdry'],
            'needful_done' => null,
            'next_followup_date' => today()->addDays(4),
        ]);

        Lead::create([
            'phone' => '66100005',
            'customer_id' => $cLost->id,
            'assigned_agent_id' => $this->agentZoya->id,
            'category' => 'inquiry',
            'service_interest' => ['Pedicure'],
            'needful_done' => 'no',
            'next_followup_date' => today()->subDay(),
        ]);

        // 1. Admin filtering by pending_followups receives all 3 pending follow-ups (ordered overdue first) + Clear filter pill
        $adminFilterRes = $this->actingAs($this->admin)->get(route('leads.index', ['alert_filter' => 'pending_followups']));
        $adminFilterRes->assertOk()
            ->assertSee('id="clearPendingFollowupsFilterPill"', false)
            ->assertSee('Clear filter')
            ->assertViewHas('leads', function ($leads) use ($zoyaOverdue, $zoyaToday, $areebaToday) {
                $ids = $leads->pluck('id')->all();
                return count($ids) === 3
                    && $ids[0] === $zoyaOverdue->id
                    && in_array($zoyaToday->id, $ids, true)
                    && in_array($areebaToday->id, $ids, true);
            });

        // 2. Individual Chat Agent Zoya filtering by pending_followups receives strictly her 2 assigned pending follow-ups
        $zoyaFilterRes = $this->actingAs($this->agentZoya)->get(route('leads.index', ['alert_filter' => 'pending_followups']));
        $zoyaFilterRes->assertOk()
            ->assertViewHas('leads', function ($leads) use ($zoyaOverdue, $zoyaToday) {
                return $leads->pluck('id')->all() === [$zoyaOverdue->id, $zoyaToday->id];
            });
    }
}
