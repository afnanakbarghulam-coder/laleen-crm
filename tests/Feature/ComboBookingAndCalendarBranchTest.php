<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComboBookingAndCalendarBranchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $airportStylist;
    private Staff $wakrahStylist;
    private Service $svcHaircut;
    private Service $svcFacial;
    private Service $svcManicure;
    private Service $svcPedicure;
    private Service $svcBlowdry;
    private Combo $vipCombo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->airportStylist = Staff::create([
            'name' => 'Aisha Airport',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->wakrahStylist = Staff::create([
            'name' => 'Layla Wakrah',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->svcHaircut = Service::create([
            'name' => 'Signature Haircut',
            'category' => 'Hair',
            'price' => 120,
            'duration' => 45,
        ]);
        $this->svcFacial = Service::create([
            'name' => 'Hydra Glow Facial',
            'category' => 'Facial',
            'price' => 250,
            'duration' => 60,
        ]);
        $this->svcManicure = Service::create([
            'name' => 'Luxury Manicure',
            'category' => 'Nails',
            'price' => 90,
            'duration' => 30,
        ]);
        $this->svcPedicure = Service::create([
            'name' => 'Spa Pedicure',
            'category' => 'Nails',
            'price' => 110,
            'duration' => 45,
        ]);
        $this->svcBlowdry = Service::create([
            'name' => 'Silk Blowdry',
            'category' => 'Hair',
            'price' => 100,
            'duration' => 30,
        ]);

        foreach ([$this->svcHaircut, $this->svcFacial, $this->svcManicure, $this->svcPedicure, $this->svcBlowdry] as $svc) {
            $svc->staff()->sync([$this->airportStylist->id, $this->wakrahStylist->id]);
        }

        $this->vipCombo = Combo::create([
            'name' => 'Royal 4-in-1 Beauty Combo',
            'price' => 399,
            'quantity_included' => 4,
            'validity_days' => 7,
            'default_duration' => 60,
            'status' => 'active',
        ]);
        $this->vipCombo->services()->sync([
            $this->svcHaircut->id,
            $this->svcFacial->id,
            $this->svcManicure->id,
            $this->svcPedicure->id,
            $this->svcBlowdry->id,
        ]);
    }

    private function nextOpenSlotDate(): Carbon
    {
        $slot = Carbon::tomorrow()->setTime(13, 0, 0);
        if ($slot->isFriday()) {
            $slot->addDay();
        }
        return $slot;
    }

    public function test_enhanced_calendar_auto_assigns_active_branch_without_branch_dropdown(): void
    {
        $slot = $this->nextOpenSlotDate();
        $dateStr = $slot->toDateString();

        // 1. Enhanced Calendar drawer omits redundant '-- Select Branch --' <select> and binds active branch
        $calRes = $this->actingAs($this->admin)->get(route('appointments.calendar', [
            'branch' => 'Al Wakrah',
            'date' => $dateStr,
        ]));
        $calRes->assertOk()
            ->assertDontSee('-- Select Branch --', false)
            ->assertDontSee('<select id="bookBranch"', false)
            ->assertSee('type="hidden" name="branch" id="bookBranch" value="wakrah"', false)
            ->assertSee("Doesn't repeat", false);

        // 2. Creating a booking from Enhanced Calendar with active_branch='Al Wakrah' normalizes & saves as 'wakrah'
        $storeRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Fatima Al-Kuwari',
            'phone' => '+97455001122',
            'appointment_datetime' => $dateStr . 'T13:00',
            'active_branch' => 'Al Wakrah',
            'staff_id' => $this->wakrahStylist->id,
            'service_name' => [$this->svcHaircut->name],
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.branch', 'wakrah')
            ->assertJsonPath('appointment.branch_label', 'Al Wakrah');

        $appt = Appointment::findOrFail($storeRes->json('appointment.id'));
        $this->assertSame('wakrah', $appt->branch);
        $this->assertSame('Al Wakrah', $appt->branch_label);

        // 3. Cross-branch staff assignment is blocked cleanly
        $crossRes = $this->actingAs($this->admin)->post(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Cross Branch Check',
            'phone' => '+97455009988',
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'Al Wakrah',
            'staff_id' => $this->airportStylist->id,
            'service_name' => [$this->svcHaircut->name],
        ]);
        $crossRes->assertRedirect();
        $crossRes->assertSessionHas('error');
        $this->assertDatabaseMissing('appointments', ['customer_name' => 'Cross Branch Check']);
    }

    public function test_booking_combo_without_sub_services_locks_combo_price_and_displays_combo_name_and_choose_in_salon_state(): void
    {
        $slot = $this->nextOpenSlotDate();
        $dateStr = $slot->toDateString();

        // Book Combo ONLY with 0 immediate services selected ("Choose Services in Salon")
        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Noora Al-Mannai',
            'phone' => '+97455223344',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'Old Airport',
            'staff_id' => $this->airportStylist->id,
            'packages' => [
                [
                    'combo_id' => $this->vipCombo->id,
                    'immediate_service_ids' => [],
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.combo_id', $this->vipCombo->id)
            ->assertJsonPath('appointment.combo_name', 'Royal 4-in-1 Beauty Combo')
            ->assertJsonPath('appointment.service_name', 'Choose in Salon')
            ->assertJsonPath('appointment.is_combo_tbd', true);

        $this->assertEquals(399.0, (float) $res->json('appointment.price'));

        $appt = Appointment::findOrFail($res->json('appointment.id'));
        $this->assertEquals(399.0, (float) $appt->price);
        $this->assertSame('Royal 4-in-1 Beauty Combo', $appt->display_combo_name);
        $this->assertSame('Choose in Salon', $appt->service_name);
        $this->assertTrue($appt->is_combo_tbd);

        $pkg = ClientPackage::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertSame(0, $pkg->redeemed_count);
        $this->assertSame(4, $pkg->remaining_count);
        $this->assertEquals(399.0, (float) $pkg->price_paid);

        // Calendar payload displays Combo Name, is_combo_tbd = true, and 60 min hold duration
        $calData = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $calData->assertOk();
        $staffSlices = $calData->json("appointments.{$this->airportStylist->id}");
        $this->assertCount(1, $staffSlices);
        $this->assertSame('Royal 4-in-1 Beauty Combo', $staffSlices[0]['combo_name']);
        $this->assertTrue($staffSlices[0]['is_combo_tbd']);
        $this->assertSame(60, $staffSlices[0]['duration']);

        // Drawer JSON (show) returns combo_packages payload with 0 active, 4 remaining, and locked 399 QAR price
        $showRes = $this->actingAs($this->admin)->getJson(route('appointments.show', $appt));
        $showRes->assertOk()
            ->assertJsonPath('combo_name', 'Royal 4-in-1 Beauty Combo')
            ->assertJsonPath('is_combo_tbd', true)
            ->assertJsonPath('combo_packages.0.combo_name', 'Royal 4-in-1 Beauty Combo')
            ->assertJsonPath('combo_packages.0.active_count', 0)
            ->assertJsonPath('combo_packages.0.remaining_count', 4);
        $this->assertEquals(399.0, (float) $showRes->json('price'));

        // Bookings table renders the Combo Name badge + "Choose in Salon" + 399.00 QAR
        $indexRes = $this->actingAs($this->admin)->get(route('appointments.index', [
            'period' => 'custom',
            'from' => $dateStr,
            'to' => $dateStr,
        ]));
        $indexRes->assertOk()
            ->assertSee('Royal 4-in-1 Beauty Combo')
            ->assertSee('Choose in Salon')
            ->assertSee('399.00');

        // Client profile shows Combo Name, Choose in Salon, and 4 pending open slots
        $customer = Customer::findOrFail($appt->customer_id);
        $profileRes = $this->actingAs($this->admin)->get(route('customers.show', $customer));
        $profileRes->assertOk()
            ->assertSee('Royal 4-in-1 Beauty Combo')
            ->assertSee('Choose in Salon')
            ->assertSee('0 / 4 redeemed')
            ->assertSee('4 more services available');
    }

    public function test_booking_combo_with_sub_services_persists_and_displays_parent_combo_name_everywhere(): void
    {
        $slot = $this->nextOpenSlotDate();
        $dateStr = $slot->toDateString();

        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Sara Al-Sulaiti',
            'phone' => '+97455667788',
            'appointment_datetime' => $dateStr . 'T14:00',
            'branch' => 'old_airport',
            'staff_id' => $this->airportStylist->id,
            'packages' => [
                [
                    'combo_id' => $this->vipCombo->id,
                    'immediate_service_ids' => [$this->svcHaircut->id, $this->svcFacial->id],
                    'pending_service_ids' => [$this->svcManicure->id],
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.combo_name', 'Royal 4-in-1 Beauty Combo')
            ->assertJsonPath('appointment.is_combo_tbd', false);

        $this->assertEquals(399.0, (float) $res->json('appointment.price'));

        $appt = Appointment::findOrFail($res->json('appointment.id'));
        $this->assertSame('Signature Haircut, Hydra Glow Facial', $appt->service_name);
        $this->assertSame('Royal 4-in-1 Beauty Combo', $appt->display_combo_name);

        // Active lines carry combo_id & combo_name; pending line has is_pending = true & status = 'pending'
        $activeLines = AppointmentService::where('appointment_id', $appt->id)->where('is_pending', false)->get();
        $pendingLines = AppointmentService::where('appointment_id', $appt->id)->where('is_pending', true)->get();
        $this->assertCount(2, $activeLines);
        $this->assertCount(1, $pendingLines);
        $this->assertSame('Royal 4-in-1 Beauty Combo', $activeLines->first()->combo_name);
        $this->assertSame('Luxury Manicure', $pendingLines->first()->name);
        $this->assertSame('pending', $pendingLines->first()->status);

        // Calendar Data payload includes parent Combo Name on each scheduled service slice (45m + 60m)
        $calData = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $calData->assertOk();
        $slices = $calData->json("appointments.{$this->airportStylist->id}");
        $this->assertCount(2, $slices);
        $this->assertSame('Royal 4-in-1 Beauty Combo', $slices[0]['combo_name']);
        $this->assertSame('Signature Haircut', $slices[0]['service_name']);
        $this->assertSame(45, $slices[0]['duration']);
        $this->assertSame('Royal 4-in-1 Beauty Combo', $slices[1]['combo_name']);
        $this->assertSame('Hydra Glow Facial', $slices[1]['service_name']);
        $this->assertSame(60, $slices[1]['duration']);

        // Checkout page displays Combo Name badge alongside services and charges exact 399.00 QAR
        $checkoutView = $this->actingAs($this->admin)->get(route('appointments.revenue.payment', $appt));
        $checkoutView->assertOk()
            ->assertSee('Royal 4-in-1 Beauty Combo')
            ->assertSee('Signature Haircut')
            ->assertSee('Hydra Glow Facial')
            ->assertDontSee('Luxury Manicure (30 min)')
            ->assertSee('399.00 QAR');
    }

    public function test_updating_combo_in_salon_picks_services_and_marks_pending_without_corrupting_combo_total_price(): void
    {
        $slot = $this->nextOpenSlotDate();
        $dateStr = $slot->toDateString();

        // Step 1: Book Combo Only (0 sub-services chosen upfront)
        $storeRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Reem Al-Dosari',
            'phone' => '+97455889900',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'staff_id' => $this->airportStylist->id,
            'packages' => [
                [
                    'combo_id' => $this->vipCombo->id,
                    'immediate_service_ids' => [],
                ],
            ],
        ]);
        $storeRes->assertStatus(201);

        $appt = Appointment::findOrFail($storeRes->json('appointment.id'));
        $pkg = ClientPackage::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertEquals(399.0, (float) $appt->price);

        // Step 2: In-Salon Drawer Update (PATCH /appointments/{id}/combo-services):
        // Pick 2 services for Today (Haircut + Manicure) and explicitly mark 2 as Pending (Facial + Pedicure)
        $patchRes = $this->actingAs($this->admin)->patchJson(route('appointments.comboServices.update', $appt), [
            'client_package_id' => $pkg->id,
            'today_service_ids' => [$this->svcHaircut->id, $this->svcManicure->id],
            'pending_service_ids' => [$this->svcFacial->id, $this->svcPedicure->id],
            'service_staff' => [
                $this->svcHaircut->id => $this->airportStylist->id,
                $this->svcManicure->id => $this->airportStylist->id,
            ],
        ]);

        $patchRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('combo_packages.0.active_count', 2)
            ->assertJsonPath('combo_packages.0.remaining_count', 2)
            ->assertJsonPath('combo_packages.0.pending_named_count', 2);

        // Locked Combo QAR price stays strictly 399.00 QAR (zero inflation or double-counting!)
        $appt->refresh();
        $pkg->refresh();
        $this->assertEquals(399.0, (float) $appt->price);
        $this->assertFalse($appt->is_combo_tbd);
        $this->assertSame('Signature Haircut, Luxury Manicure', $appt->service_name);
        $this->assertSame('Royal 4-in-1 Beauty Combo', $appt->display_combo_name);
        $this->assertSame(2, $pkg->redeemed_count);
        $this->assertSame(2, $pkg->remaining_count);

        $this->assertSame(
            2,
            ClientPackageService::where('client_package_id', $pkg->id)->where('status', 'redeemed')->count()
        );
        $this->assertSame(
            2,
            ClientPackageService::where('client_package_id', $pkg->id)->where('status', 'pending')->count()
        );

        // Step 3: Also verify Bookings Modal Update (PUT /appointments/{id}) swaps services & Pending state cleanly
        $putRes = $this->actingAs($this->admin)->put(route('appointments.update', $appt), [
            'customer_name' => 'Reem Al-Dosari',
            'phone' => '+97455889900',
            'appointment_datetime' => $dateStr . 'T14:00',
            'branch' => 'old_airport',
            'staff_id' => $this->airportStylist->id,
            'status' => 'in_progress',
            'price' => 399,
            'combo_packages' => [
                [
                    'client_package_id' => $pkg->id,
                    'today_service_ids' => [$this->svcFacial->id],
                    'pending_service_ids' => [$this->svcHaircut->id, $this->svcPedicure->id, $this->svcBlowdry->id],
                ],
            ],
        ]);
        $putRes->assertRedirect();
        $putRes->assertSessionHasNoErrors();

        $appt->refresh();
        $pkg->refresh();
        $this->assertEquals(399.0, (float) $appt->price);
        $this->assertSame('Hydra Glow Facial', $appt->service_name);
        $this->assertSame(1, $pkg->redeemed_count);
        $this->assertSame(3, $pkg->remaining_count);

        // Step 4: Complete Visit 1 Checkout — charges exact 399.00 QAR once, stamps sale_id ONLY on redeemed row,
        // and leaves the 3 Pending services intact for future redemption!
        $payRes = $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appt), [
            'payments' => ['card' => 399],
        ]);
        $payRes->assertRedirect(route('appointments.revenue.index'));

        $sale = Sale::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertEquals(399.0, (float) $sale->total_amount);
        $this->assertEquals(399.0, (float) $sale->packages_total);
        $this->assertEquals(0.0, (float) $sale->services_total);
        $this->assertEquals(0.0, (float) $sale->discount_amount);

        $pkg->refresh();
        $this->assertSame($sale->id, (int) $pkg->sale_id);
        $this->assertSame(1, $pkg->redeemed_count);
        $this->assertSame(3, $pkg->remaining_count);

        // Pending ClientPackageService rows remain status='pending' with null sale_id so Visit 2 can redeem them
        $pendingPkgSvcs = ClientPackageService::where('client_package_id', $pkg->id)->where('status', 'pending')->get();
        $this->assertCount(3, $pendingPkgSvcs);
        $this->assertTrue($pendingPkgSvcs->every(fn ($row) => $row->sale_id === null));

        $customer = Customer::findOrFail($appt->customer_id);
        $this->assertSame(3, $customer->pending_package_service_count);
    }

    public function test_minimal_combo_subview_ui_and_two_way_salon_selection_lifecycle(): void
    {
        $svcScrub = Service::create([
            'name' => 'Moroccan Body Scrub',
            'category' => 'Body',
            'price' => 180,
            'duration' => 50,
        ]);
        $svcScrub->staff()->sync([$this->airportStylist->id, $this->wakrahStylist->id]);

        $bridalCombo = Combo::create([
            'name' => 'Bridal Glow Package',
            'price' => 200,
            'quantity_included' => 3,
            'validity_days' => 14,
            'default_duration' => 60,
            'status' => 'active',
        ]);
        $bridalCombo->services()->sync([
            $this->svcHaircut->id,
            $this->svcFacial->id,
            $this->svcManicure->id,
            $this->svcPedicure->id,
            $this->svcBlowdry->id,
            $svcScrub->id,
        ]);

        // 1. Enhanced Calendar renders nested Combo Service Picker Sub-Views (New Booking + Saved Drawer) with live search
        $calRes = $this->actingAs($this->admin)->get(route('appointments.calendar'));
        $calRes->assertOk();
        $calHtml = $calRes->getContent();

        $this->assertStringNotContainsString('id="fbDecideInSalonBtn"', $calHtml);
        $this->assertStringNotContainsString('Save Combo Services', $calHtml);
        $this->assertStringContainsString('id="fbComboCatalogSearch"', $calHtml);
        $this->assertStringContainsString('id="fbPanelComboConfig"', $calHtml);
        $this->assertStringContainsString('id="fbComboServiceSearch"', $calHtml);
        $this->assertStringContainsString('id="fbComboSkipSalonBtn"', $calHtml);
        $this->assertStringContainsString('id="fbComboConfigApplyBtn"', $calHtml);
        $this->assertStringContainsString('id="drawerComboSubHeader"', $calHtml);
        $this->assertStringContainsString('id="drawerComboSubView"', $calHtml);
        $this->assertStringContainsString('id="drawerComboSubSearch"', $calHtml);
        $this->assertStringContainsString('id="drawerComboSubSkipBtn"', $calHtml);
        $this->assertStringContainsString('id="drawerComboSubApplyBtn"', $calHtml);
        $this->assertStringContainsString('Search services in this combo...', $calHtml);
        $this->assertStringContainsString('Skip selection — Decide services in salon', $calHtml);

        // 2. Bookings index also renders nested Combo Service Picker Sub-View inside Edit Modal
        $bkRes = $this->actingAs($this->admin)->get(route('appointments.index'));
        $bkRes->assertOk();
        $bkHtml = $bkRes->getContent();
        $this->assertStringContainsString('id="editModalComboSubHeader"', $bkHtml);
        $this->assertStringContainsString('id="editModalComboSubView"', $bkHtml);
        $this->assertStringContainsString('id="editModalComboSearch"', $bkHtml);
        $this->assertStringContainsString('id="editModalComboSkipBtn"', $bkHtml);
        $this->assertStringContainsString('id="editModalComboApplyBtn"', $bkHtml);

        // 3. Scenario 1: Chat Agent adds Bridal Glow Package (3 of 6) via "Skip selection — Decide services in salon" (0/3)
        $slot = $this->nextOpenSlotDate();
        $dateStr = $slot->toDateString();

        $storeRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Maryam Al-Kuwari',
            'phone' => '+97455123456',
            'appointment_datetime' => $dateStr . 'T11:00',
            'branch' => 'old_airport',
            'staff_id' => $this->airportStylist->id,
            'packages' => [
                [
                    'combo_id' => $bridalCombo->id,
                    'immediate_service_ids' => [],
                    'pending_service_ids' => [],
                ],
            ],
        ]);
        $storeRes->assertStatus(201);

        $appt = Appointment::findOrFail($storeRes->json('appointment.id'));
        $pkg = ClientPackage::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertTrue($appt->is_combo_tbd);
        $this->assertEquals(200.0, (float) $appt->price);

        // 4. Scenario 2a: Max allowance guard rejects picking 4 of 6 services (2 Today + 2 Pending > 3 included)
        $overCapRes = $this->actingAs($this->admin)->patchJson(route('appointments.comboServices.update', $appt), [
            'client_package_id' => $pkg->id,
            'today_service_ids' => [$this->svcHaircut->id, $this->svcManicure->id],
            'pending_service_ids' => [$this->svcFacial->id, $this->svcPedicure->id],
        ]);
        $overCapRes->assertStatus(422);

        // 5. Scenario 2b: Salon staff picks 2 Today (45m + 30m) + 1 Pending -> Done · Apply to Booking
        $applyRes = $this->actingAs($this->admin)->patchJson(route('appointments.comboServices.update', $appt), [
            'client_package_id' => $pkg->id,
            'today_service_ids' => [$this->svcHaircut->id, $this->svcManicure->id],
            'pending_service_ids' => [$this->svcFacial->id],
        ]);
        $applyRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('combo_packages.0.active_count', 2)
            ->assertJsonPath('combo_packages.0.pending_named_count', 1);

        $appt->refresh();
        $this->assertFalse($appt->is_combo_tbd);
        $this->assertEquals(200.0, (float) $appt->price);
        $this->assertSame(
            75,
            (int) AppointmentService::where('appointment_id', $appt->id)->where('is_pending', false)->sum('duration')
        );

        // 6. Scenario 2c: Salon staff clicks "Skip selection — Decide services in salon" -> resets to 0/3 & 60m hold
        $skipRes = $this->actingAs($this->admin)->patchJson(route('appointments.comboServices.update', $appt), [
            'client_package_id' => $pkg->id,
            'today_service_ids' => [],
            'pending_service_ids' => [],
        ]);
        $skipRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('combo_packages.0.active_count', 0)
            ->assertJsonPath('combo_packages.0.pending_named_count', 0);

        $appt->refresh();
        $this->assertTrue($appt->is_combo_tbd);
        $this->assertEquals(200.0, (float) $appt->price);
        $this->assertSame(0, AppointmentService::where('appointment_id', $appt->id)->where('is_pending', false)->count());

        $calHoldRes = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $calHoldRes->assertOk();
        $holdSlices = $calHoldRes->json("appointments.{$this->airportStylist->id}");
        $this->assertCount(1, $holdSlices);
        $this->assertTrue($holdSlices[0]['is_combo_tbd']);
        $this->assertSame(60, $holdSlices[0]['duration']);
    }
}
