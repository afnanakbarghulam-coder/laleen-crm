<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientsBranchFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'branch-filter-admin@laleen.test',
            'is_active' => true,
        ]);
    }

    public function test_clients_and_customers_routes_render_branch_filter_controls_and_row_badges(): void
    {
        $oaClient1 = Customer::create([
            'name' => 'Mariam OldAirport',
            'phone' => '55200001',
            'branch' => 'old_airport',
        ]);
        $oaClient2 = Customer::create([
            'name' => 'Amina OldAirport',
            'phone' => '55200003',
            'branch' => 'old_airport',
        ]);
        $wkClient = Customer::create([
            'name' => 'Noor AlWakrah',
            'phone' => '55200002',
            'branch' => 'wakrah',
        ]);

        foreach (['/clients', '/customers'] as $uri) {
            $response = $this->actingAs($this->admin)->get($uri);

            $response->assertOk()
                ->assertSee('All Branches')
                ->assertSee('Old Airport')
                ->assertSee('Al Wakrah')
                ->assertDontSee('data-branch-pill="unassigned"', false)
                ->assertSee('data-branch-count="all">3<', false)
                ->assertSee('data-branch-count="old_airport">2<', false)
                ->assertSee('data-branch-count="wakrah">1<', false)
                ->assertSee($oaClient1->name)
                ->assertSee($oaClient2->name)
                ->assertSee($wkClient->name);
        }
    }

    public function test_clicking_al_wakrah_keeps_all_branches_old_airport_and_unassigned_count_badges_accurate(): void
    {
        // 3 Old Airport clients
        Customer::create(['name' => 'OA One', 'phone' => '55700001', 'branch' => 'old_airport']);
        Customer::create(['name' => 'OA Two', 'phone' => '55700002', 'branch' => 'old_airport']);
        Customer::create(['name' => 'OA Three', 'phone' => '55700003', 'branch' => 'old_airport']);

        // 2 Al Wakrah clients
        $wkOne = Customer::create(['name' => 'WK One', 'phone' => '55700004', 'branch' => 'wakrah']);
        $wkTwo = Customer::create(['name' => 'WK Two', 'phone' => '55700005', 'branch' => 'wakrah']);

        // 1 Unassigned legacy client
        $unassigned = Customer::create(['name' => 'Legacy NoBranch', 'phone' => '55700006', 'branch' => null]);

        // 1. Default GET /clients shows exact live counts across all 4 pills simultaneously
        $allRes = $this->actingAs($this->admin)->get('/clients');
        $allRes->assertOk()
            ->assertSee('data-branch-count="all">6<', false)
            ->assertSee('data-branch-count="old_airport">3<', false)
            ->assertSee('data-branch-count="wakrah">2<', false)
            ->assertSee('data-branch-count="unassigned">1<', false);

        // 2. Clicking ?branch=Al+Wakrah filters rows to Al Wakrah, while keeping All Branches (6), Old Airport (3), Al Wakrah (2), and Unassigned (1) badges intact!
        $wkRes = $this->actingAs($this->admin)->get('/clients?branch=Al+Wakrah');
        $wkRes->assertOk()
            ->assertSee($wkOne->name)
            ->assertSee($wkTwo->name)
            ->assertDontSee('OA One')
            ->assertDontSee($unassigned->name)
            ->assertSee('data-branch-count="all">6<', false)
            ->assertSee('data-branch-count="old_airport">3<', false)
            ->assertSee('data-branch-count="wakrah">2<', false)
            ->assertSee('data-branch-count="unassigned">1<', false);

        // 3. Clicking ?branch=unassigned filters cleanly to unassigned clients while keeping all pill counts visible
        $unassignedRes = $this->actingAs($this->admin)->get('/clients?branch=unassigned');
        $unassignedRes->assertOk()
            ->assertSee($unassigned->name)
            ->assertDontSee('OA One')
            ->assertDontSee($wkOne->name)
            ->assertSee('data-branch-count="all">6<', false)
            ->assertSee('data-branch-count="old_airport">3<', false)
            ->assertSee('data-branch-count="wakrah">2<', false)
            ->assertSee('data-branch-count="unassigned">1<', false);

        // 4. Combining ?branch=Al+Wakrah with ?search=One updates branch counts to match the search ('OA One' + 'WK One' = 2 total: 1 OA, 1 WK, 0 Unassigned)
        $searchRes = $this->actingAs($this->admin)->get('/clients?branch=Al+Wakrah&search=One');
        $searchRes->assertOk()
            ->assertSee($wkOne->name)
            ->assertDontSee($wkTwo->name)
            ->assertSee('data-branch-count="all">2<', false)
            ->assertSee('data-branch-count="old_airport">1<', false)
            ->assertSee('data-branch-count="wakrah">1<', false);
    }

    public function test_branch_filter_includes_primary_branch_and_cross_branch_visitors_while_excluding_other_branch(): void
    {
        // Pure Old Airport client
        $oaOnly = Customer::create([
            'name' => 'Fatima OA Only',
            'phone' => '55300001',
            'branch' => 'old_airport',
        ]);

        // Pure Al Wakrah client
        $wkOnly = Customer::create([
            'name' => 'Huda Wakrah Only',
            'phone' => '55300002',
            'branch' => 'wakrah',
        ]);

        // Multi-branch VIP: primary is Al Wakrah, but she also completed a booking at Old Airport
        $crossBranchGuest = Customer::create([
            'name' => 'Dana CrossBranch VIP',
            'phone' => '55300003',
            'branch' => 'wakrah',
        ]);
        Appointment::create([
            'customer_id' => $crossBranchGuest->id,
            'customer_name' => $crossBranchGuest->name,
            'phone' => $crossBranchGuest->phone,
            'service_name' => 'Luxury Hydrafacial',
            'appointment_datetime' => now()->subDays(10),
            'booking_date' => now()->subDays(10)->toDateString(),
            'start_time' => '15:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 450,
        ]);

        // Filter by Old Airport (both canonical slug and human label)
        foreach (['old_airport', 'Old Airport'] as $param) {
            $oaRes = $this->actingAs($this->admin)->get('/customers?branch=' . urlencode($param));
            $oaRes->assertOk()
                ->assertSee($oaOnly->name)
                ->assertSee($crossBranchGuest->name)
                ->assertDontSee($wkOnly->name);
        }

        // Filter by Al Wakrah (both canonical slug and human label)
        foreach (['wakrah', 'Al Wakrah'] as $param) {
            $wkRes = $this->actingAs($this->admin)->get('/clients?branch=' . urlencode($param));
            $wkRes->assertOk()
                ->assertSee($wkOnly->name)
                ->assertSee($crossBranchGuest->name)
                ->assertDontSee($oaOnly->name);
        }
    }

    public function test_segment_counts_search_pagination_and_retention_sync_combine_accurately_with_branch_filter(): void
    {
        $nailService = Service::create([
            'name' => 'BIAB Gel Refill',
            'price' => 220,
            'duration' => 60,
            'rebooking_interval_days' => 21,
        ]);

        // 1. Old Airport Due for Refill client (visited 25 days ago for 21d service)
        $oaRefill = Customer::create([
            'name' => 'Sara OA Refill',
            'phone' => '55400001',
            'branch' => 'old_airport',
        ]);
        Appointment::create([
            'customer_id' => $oaRefill->id,
            'customer_name' => $oaRefill->name,
            'phone' => $oaRefill->phone,
            'service_name' => $nailService->name,
            'appointment_datetime' => now()->subDays(25),
            'booking_date' => now()->subDays(25)->toDateString(),
            'start_time' => '12:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 220,
        ]);

        // 2. Al Wakrah Due for Refill client
        $wkRefill = Customer::create([
            'name' => 'Layla WK Refill',
            'phone' => '55400002',
            'branch' => 'wakrah',
        ]);
        Appointment::create([
            'customer_id' => $wkRefill->id,
            'customer_name' => $wkRefill->name,
            'phone' => $wkRefill->phone,
            'service_name' => $nailService->name,
            'appointment_datetime' => now()->subDays(26),
            'booking_date' => now()->subDays(26)->toDateString(),
            'start_time' => '13:00:00',
            'status' => 'completed',
            'branch' => 'wakrah',
            'price' => 220,
        ]);

        // 3. Old Airport Package Ending client (1 session remaining)
        $oaPkgClient = Customer::create([
            'name' => 'Reem OA Package',
            'phone' => '55400003',
            'branch' => 'old_airport',
        ]);
        $combo = Combo::create([
            'name' => 'Bridal Glow 3x',
            'price' => 900,
            'quantity_included' => 1,
            'validity_days' => 30,
            'status' => 'active',
        ]);
        $sale = Sale::create([
            'customer_id' => $oaPkgClient->id,
            'branch' => 'old_airport',
            'services_total' => 0,
            'products_total' => 0,
            'packages_total' => 900,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 900,
        ]);
        ClientPackage::create([
            'customer_id' => $oaPkgClient->id,
            'combo_id' => $combo->id,
            'sale_id' => $sale->id,
            'combo_name' => $combo->name,
            'price_paid' => 900,
            'quantity_included' => 1,
            'validity_days' => 30,
            'status' => 'active',
            'purchased_at' => now()->subDays(5),
            'expires_at' => now()->addDays(10),
        ]);

        // 4. Al Wakrah Missing VIP (> 45 days away, 600 QAR spent, generic 28d service)
        $wkVip = Customer::create([
            'name' => 'Mona WK MissingVIP',
            'phone' => '55400004',
            'branch' => 'wakrah',
        ]);
        Appointment::create([
            'customer_id' => $wkVip->id,
            'customer_name' => $wkVip->name,
            'phone' => $wkVip->phone,
            'service_name' => 'Luxury Caviar Ritual',
            'appointment_datetime' => now()->subDays(60),
            'booking_date' => now()->subDays(60)->toDateString(),
            'start_time' => '16:00:00',
            'status' => 'completed',
            'branch' => 'wakrah',
            'price' => 600,
        ]);
        Appointment::create([
            'customer_id' => $wkVip->id,
            'customer_name' => $wkVip->name,
            'phone' => $wkVip->phone,
            'service_name' => 'Luxury Caviar Ritual',
            'appointment_datetime' => now()->subDays(95),
            'booking_date' => now()->subDays(95)->toDateString(),
            'start_time' => '16:00:00',
            'status' => 'completed',
            'branch' => 'wakrah',
            'price' => 600,
        ]);

        // Verify Old Airport segment counts
        $oaIndex = $this->actingAs($this->admin)->get('/customers?branch=old_airport');
        $oaIndex->assertOk();
        $oaCounts = $oaIndex->viewData('segmentCounts');
        $this->assertSame(2, $oaCounts['all']);
        $this->assertSame(2, $oaCounts['ltv']);
        $this->assertSame(1, $oaCounts['due_for_refill']);
        $this->assertSame(1, $oaCounts['package_renewal']);
        $this->assertSame(0, $oaCounts['bleeding_vips']);

        // Verify Al Wakrah segment counts
        $wkIndex = $this->actingAs($this->admin)->get('/customers?branch=wakrah');
        $wkIndex->assertOk();
        $wkCounts = $wkIndex->viewData('segmentCounts');
        $this->assertSame(2, $wkCounts['all']);
        $this->assertSame(1, $wkCounts['due_for_refill']);
        $this->assertSame(0, $wkCounts['package_renewal']);
        $this->assertSame(1, $wkCounts['bleeding_vips']);

        // Combined segment + branch filter
        $oaDueRes = $this->actingAs($this->admin)->get('/customers?branch=old_airport&segment=due_for_refill');
        $oaDueRes->assertOk()
            ->assertSee($oaRefill->name)
            ->assertDontSee($wkRefill->name)
            ->assertDontSee($oaPkgClient->name);

        // Combined search + branch filter
        $searchMatch = $this->actingAs($this->admin)->get('/customers?branch=old_airport&search=Reem');
        $searchMatch->assertOk()
            ->assertSee($oaPkgClient->name)
            ->assertDontSee($oaRefill->name);

        $searchExcludedByBranch = $this->actingAs($this->admin)->get('/customers?branch=wakrah&search=Reem');
        $searchExcludedByBranch->assertOk()
            ->assertDontSee($oaPkgClient->name)
            ->assertSee('No clients found');

        // Send Overdue to Leads scoped to Old Airport ONLY
        $syncRes = $this->actingAs($this->admin)
            ->postJson('/customers/sync-retention-leads', ['branch' => 'old_airport']);
        $syncRes->assertOk()->assertJsonPath('pushed_count', 2);

        $this->assertDatabaseHas('leads', [
            'customer_id' => $oaRefill->id,
            'branch' => 'old_airport',
        ]);
        $this->assertDatabaseHas('leads', [
            'customer_id' => $oaPkgClient->id,
            'branch' => 'old_airport',
        ]);
        $this->assertDatabaseMissing('leads', [
            'customer_id' => $wkRefill->id,
        ]);
        $this->assertDatabaseMissing('leads', [
            'customer_id' => $wkVip->id,
        ]);

        // Pagination preserves branch & segment query parameters across pages (> 20 rows)
        for ($i = 1; $i <= 22; $i++) {
            Customer::create([
                'name' => sprintf('Paginated OA Client %02d', $i),
                'phone' => sprintf('559900%02d', $i),
                'branch' => 'old_airport',
            ]);
        }

        $page1 = $this->actingAs($this->admin)->get('/customers?branch=old_airport&segment=ltv');
        $page1->assertOk()
            ->assertSee('branch=old_airport')
            ->assertSee('segment=ltv')
            ->assertSee('page=2');
    }

    public function test_backfill_auto_attribution_and_client_profile_branch_editing(): void
    {
        // 1. Auto-attribution from Appointment creation when customer->branch is null
        $bookingClient = Customer::create([
            'name' => 'Salma BookingAuto',
            'phone' => '55600001',
        ]);
        $this->assertNull($bookingClient->fresh()->branch);

        Appointment::create([
            'customer_id' => $bookingClient->id,
            'customer_name' => $bookingClient->name,
            'phone' => $bookingClient->phone,
            'service_name' => 'Root Touch-Up',
            'appointment_datetime' => now()->addDay(),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00:00',
            'status' => 'pending',
            'branch' => 'wakrah',
            'price' => 250,
        ]);
        $this->assertSame('wakrah', $bookingClient->fresh()->branch);

        // 2. Auto-attribution from Lead creation when customer->branch is null
        $leadClient = Customer::create([
            'name' => 'Amna LeadAuto',
            'phone' => '55600002',
        ]);
        Lead::create([
            'customer_id' => $leadClient->id,
            'phone' => $leadClient->phone,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'branch' => 'Old Airport',
            'service_interest' => ['Keratin Treatment'],
            'next_followup_date' => today(),
        ]);
        $this->assertSame('old_airport', $leadClient->fresh()->branch);

        // 3. Idempotent backfill for legacy null rows (most recent booking wins over older booking or lead)
        $legacyApptClient = Customer::create([
            'name' => 'Legacy Appt Client',
            'phone' => '55600003',
        ]);
        $legacyLeadClient = Customer::create([
            'name' => 'Legacy Lead Client',
            'phone' => '55600004',
        ]);
        $legacyAdLeadClient = Customer::create([
            'name' => 'Legacy AdLead Client',
            'phone' => '55600005',
        ]);

        // Insert raw rows bypassing Eloquent events to simulate pre-migration legacy data
        DB::table('appointments')->insert([
            [
                'customer_id' => $legacyApptClient->id,
                'customer_name' => $legacyApptClient->name,
                'phone' => $legacyApptClient->phone,
                'service_name' => 'Older Visit',
                'appointment_datetime' => now()->subDays(40),
                'status' => 'completed',
                'branch' => 'old_airport',
                'price' => 150,
                'created_at' => now()->subDays(40),
                'updated_at' => now()->subDays(40),
            ],
            [
                'customer_id' => $legacyApptClient->id,
                'customer_name' => $legacyApptClient->name,
                'phone' => $legacyApptClient->phone,
                'service_name' => 'Latest Visit',
                'appointment_datetime' => now()->subDays(5),
                'status' => 'completed',
                'branch' => 'wakrah',
                'price' => 300,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
        ]);

        DB::table('leads')->insert([
            'customer_id' => null,
            'phone' => '97455600004',
            'category' => 'inquiry',
            'branch' => 'old_airport',
            'customer_remarks' => 'Interested in Old Airport makeover',
            'service_interest' => json_encode(['Makeover']),
            'categories' => json_encode(['inquiry']),
            'next_followup_date' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ad_lead_entries')->insert([
            'date' => today()->toDateString(),
            'customer_id' => null,
            'customer_name' => $legacyAdLeadClient->name,
            'phone' => '+974 5560 0005',
            'category' => 'Hair Color',
            'ticket_amount' => 350,
            'branch' => 'wakrah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $backfilledCount = Customer::backfillBranches();
        $this->assertSame(3, $backfilledCount);
        $this->assertSame('wakrah', $legacyApptClient->fresh()->branch);
        $this->assertSame('old_airport', $legacyLeadClient->fresh()->branch);
        $this->assertSame('wakrah', $legacyAdLeadClient->fresh()->branch);

        // Running backfill a second time is a safe no-op
        $this->assertSame(0, Customer::backfillBranches());

        // 4. Editing branch on Client Profile works AND omitting branch preserves existing value
        $this->actingAs($this->admin)
            ->put(route('customers.update', $legacyLeadClient->id), [
                'name' => 'Legacy Lead Client Updated',
                'phone' => '55600004',
                'branch' => 'wakrah',
            ])
            ->assertRedirect();

        $this->assertSame('wakrah', $legacyLeadClient->fresh()->branch);

        // Omitting branch on a standard name/phone update does NOT wipe existing branch
        $this->actingAs($this->admin)
            ->put(route('customers.update', $legacyLeadClient->id), [
                'name' => 'Legacy Lead Client Renamed',
                'phone' => '55600004',
            ])
            ->assertRedirect();

        $this->assertSame('wakrah', $legacyLeadClient->fresh()->branch);
    }
}
