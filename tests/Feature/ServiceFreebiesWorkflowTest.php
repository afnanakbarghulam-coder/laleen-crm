<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientFreebieCredit;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceFreebiePackage;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceFreebiesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-freebies@laleen.test',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createFreebiesCatalogFixture(): array
    {
        $hairCat = ServiceCategory::create(['name' => 'Hair Color', 'color' => '#d98f83']);
        $miniCat = ServiceCategory::create(['name' => 'Express Beauty', 'color' => '#8ea88a']);

        $anchorService = Service::create([
            'name' => 'Full Hair Color',
            'category_id' => $hairCat->id,
            'price' => 350,
            'duration' => 90,
        ]);

        $eligibleNames = [
            ['name' => 'Eyebrow Threading', 'price' => 30, 'duration' => 15],
            ['name' => 'Upper Lip Threading', 'price' => 25, 'duration' => 10],
            ['name' => 'Express Manicure', 'price' => 50, 'duration' => 25],
            ['name' => 'Express Pedicure', 'price' => 60, 'duration' => 30],
            ['name' => 'Hydrating Hair Mask', 'price' => 70, 'duration' => 20],
            ['name' => 'Express Blowdry', 'price' => 80, 'duration' => 30],
        ];

        $eligibleServices = collect($eligibleNames)->map(fn (array $row) => Service::create([
            'name' => $row['name'],
            'category_id' => $miniCat->id,
            'price' => $row['price'],
            'duration' => $row['duration'],
        ]))->values();

        $nonEligibleService = Service::create([
            'name' => 'Royal Keratin Treatment',
            'category_id' => $hairCat->id,
            'price' => 550,
            'duration' => 150,
        ]);

        $seniorColorist = Staff::create([
            'name' => 'Senior Colorist Layla',
            'role' => 'Senior Colorist',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);
        $seniorColorist->services()->sync([$anchorService->id, $nonEligibleService->id]);

        $juniorBeautician = Staff::create([
            'name' => 'Beautician Sara',
            'role' => 'Junior Beautician',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);
        $juniorBeautician->services()->sync($eligibleServices->pluck('id')->all());

        $customer = Customer::create([
            'name' => 'Noor Al Thani',
            'phone' => '+97455889900',
            'branch' => 'old_airport',
        ]);

        return compact(
            'anchorService',
            'eligibleServices',
            'nonEligibleService',
            'seniorColorist',
            'juniorBeautician',
            'customer'
        );
    }

    public function test_creating_service_plus_freebies_package_renders_cleanly_in_service_catalog(): void
    {
        $f = $this->createFreebiesCatalogFixture();

        $createResp = $this->actingAs($this->admin)->postJson(route('service-freebies.store'), [
            'name' => 'Hair Color + 2 Free Services',
            'anchor_service_id' => $f['anchorService']->id,
            'price' => 299.00,
            'free_choices_count' => 2,
            'validity_days' => 7,
            'is_active' => true,
            'eligible_service_ids' => $f['eligibleServices']->pluck('id')->all(),
        ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('package.name', 'Hair Color + 2 Free Services')
            ->assertJsonPath('package.free_choices_count', 2)
            ->assertJsonPath('package.validity_days', 7);

        $package = ServiceFreebiePackage::with('eligibleServices')->firstOrFail();
        $this->assertSame(299.00, (float) $package->price);
        $this->assertCount(6, $package->eligibleServices);

        $catalogResp = $this->actingAs($this->admin)->get(route('services.index', ['tab' => 'freebies']));
        $catalogResp->assertOk()
            ->assertSee('Service + Freebies')
            ->assertSee('Hair Color + 2 Free Services')
            ->assertSee('Anchor: Full Hair Color')
            ->assertSee('Choose 2 Free (from 6 eligible)')
            ->assertSee('Valid 7 days')
            ->assertSee('299.00 QAR');
    }

    public function test_multi_staff_booking_with_same_day_freebie_and_save_for_later_unlocks_on_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00', 'Asia/Qatar'));
        $f = $this->createFreebiesCatalogFixture();

        $package = ServiceFreebiePackage::create([
            'name' => 'Hair Color + 2 Free Services',
            'anchor_service_id' => $f['anchorService']->id,
            'price' => 299.00,
            'free_choices_count' => 2,
            'validity_days' => 7,
            'is_active' => true,
        ]);
        $package->eligibleServices()->sync($f['eligibleServices']->pluck('id')->all());

        $sameDayMini = $f['eligibleServices'][0]; // Eyebrow Threading (15 min)

        $bookResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-20T12:00',
            'booking_date' => '2026-11-20',
            'branch' => 'old_airport',
            'freebie_packages' => [
                [
                    'package_id' => $package->id,
                    'anchor_staff_id' => $f['seniorColorist']->id,
                    'slots' => [
                        [
                            'mode' => 'same_day',
                            'service_id' => $sameDayMini->id,
                            'staff_id' => $f['juniorBeautician']->id,
                        ],
                        [
                            'mode' => 'save_for_later',
                        ],
                    ],
                ],
            ],
        ]);

        $bookResp->assertStatus(201)->assertJsonPath('success', true);

        $anchorAppt = Appointment::with('appointmentServices')->findOrFail($bookResp->json('appointment.id'));
        $this->assertSame(299.00, (float) $anchorAppt->price);
        $this->assertSame($package->id, (int) $anchorAppt->freebie_package_id);
        $this->assertFalse((bool) $anchorAppt->is_freebie_redemption);
        $this->assertCount(2, $anchorAppt->appointmentServices);

        $anchorSlice = $anchorAppt->appointmentServices->firstWhere('is_freebie_redemption', false);
        $freebieSlice = $anchorAppt->appointmentServices->firstWhere('is_freebie_redemption', true);

        $this->assertNotNull($anchorSlice);
        $this->assertSame($f['seniorColorist']->id, (int) $anchorSlice->staff_id);
        $this->assertSame(299.00, (float) $anchorSlice->final_price);
        $this->assertSame(90, (int) $anchorSlice->duration);

        $this->assertNotNull($freebieSlice);
        $this->assertSame($f['juniorBeautician']->id, (int) $freebieSlice->staff_id);
        $this->assertSame(0.00, (float) $freebieSlice->final_price);
        $this->assertSame(0.00, (float) $freebieSlice->discount_amount);
        $this->assertSame(15, (int) $freebieSlice->duration);
        $this->assertSame('13:30', $freebieSlice->start_time->format('H:i'));

        $credits = ClientFreebieCredit::where('anchor_appointment_id', $anchorAppt->id)->orderBy('slot_number')->get();
        $this->assertCount(2, $credits);
        $this->assertSame('scheduled', $credits[0]->status);
        $this->assertSame($sameDayMini->id, (int) $credits[0]->redeemed_service_id);
        $this->assertSame('locked_until_paid', $credits[1]->status);
        $this->assertSame('2026-11-27 23:59:59', $credits[1]->expires_at->copy()->timezone('Asia/Qatar')->format('Y-m-d H:i:s'));

        // Cannot redeem Slot #2 while anchor booking is still unpaid (locked_until_paid)
        $prematureRedeem = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-22T14:00',
            'booking_date' => '2026-11-22',
            'branch' => 'old_airport',
            'staff_id' => $f['juniorBeautician']->id,
            'redeem_freebies' => [
                ['credit_id' => $credits[1]->id, 'service_id' => $f['eligibleServices'][2]->id, 'staff_id' => $f['juniorBeautician']->id],
            ],
        ]);
        $prematureRedeem->assertStatus(422);

        // Rescheduling anchor appointment date updates expires_at to end of Day 7 from new date
        $reschedResp = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $anchorAppt->id), [
            'appointment_datetime' => '2026-11-21T12:00',
            'staff_id' => $f['seniorColorist']->id,
            'branch' => 'old_airport',
        ]);
        $reschedResp->assertOk();
        $this->assertSame(
            '2026-11-28 23:59:59',
            $credits[1]->fresh()->expires_at->copy()->timezone('Asia/Qatar')->format('Y-m-d H:i:s')
        );

        // Mark anchor 299 QAR booking Paid / Completed via Checkout
        Carbon::setTestNow(Carbon::parse('2026-11-21 14:00:00', 'Asia/Qatar'));
        $payResp = $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $anchorAppt->id), [
            'payments' => ['cash' => 299.00],
        ]);
        $payResp->assertRedirect(route('appointments.revenue.index'));

        $anchorAppt->refresh();
        $this->assertSame('completed', $anchorAppt->status);
        $this->assertSame(299.00, (float) $anchorAppt->price);

        $sale = Sale::where('appointment_id', $anchorAppt->id)->firstOrFail();
        $this->assertSame(299.00, (float) $sale->total_amount);
        $this->assertSame(0.00, (float) $sale->discount_amount);

        // Slot #1 (same-day) flipped to 'redeemed'; Slot #2 (Save for Later) unlocked to 'available'
        $this->assertSame('redeemed', $credits[0]->fresh()->status);
        $this->assertNotNull($credits[0]->fresh()->redeemed_at);

        $slot2 = $credits[1]->fresh();
        $this->assertSame('available', $slot2->status);
        $this->assertNotNull($slot2->unlocked_at);
        $this->assertSame('2026-11-28 23:59:59', $slot2->expires_at->copy()->timezone('Asia/Qatar')->format('Y-m-d H:i:s'));

        // Live client lookup & Customer Profile show 1 available freebie
        $lookupResp = $this->actingAs($this->admin)->getJson(route('customers.lookup', ['phone' => $f['customer']->phone]));
        $lookupResp->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonCount(1, 'available_freebie_credits');

        $profileResp = $this->actingAs($this->admin)->get(route('customers.show', $f['customer']->id));
        $profileResp->assertOk()
            ->assertSee('Service + Freebies')
            ->assertSee('✓ Redeemed: Eyebrow Threading')
            ->assertSee('Valid until 28 Nov')
            ->assertSee('Redeem');
    }

    public function test_redeeming_available_slot_enforces_eligible_pool_succeeds_day_6_at_zero_qar_and_rejects_day_8(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 13:00:00', 'Asia/Qatar'));
        $f = $this->createFreebiesCatalogFixture();

        $package = ServiceFreebiePackage::create([
            'name' => 'Hair Color + 2 Free Services',
            'anchor_service_id' => $f['anchorService']->id,
            'price' => 299.00,
            'free_choices_count' => 2,
            'validity_days' => 7,
            'is_active' => true,
        ]);
        $package->eligibleServices()->sync($f['eligibleServices']->pluck('id')->all());

        // Book with both slots Saved for Later and pay 299 QAR on Anchor Date 2026-11-20 (expires 2026-11-27 23:59:59)
        $bookResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-20T13:00',
            'booking_date' => '2026-11-20',
            'branch' => 'old_airport',
            'freebie_package_id' => $package->id,
            'anchor_staff_id' => $f['seniorColorist']->id,
        ]);
        $bookResp->assertStatus(201);
        $anchorApptId = $bookResp->json('appointment.id');

        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $anchorApptId), [
            'payments' => ['card' => 299.00],
        ])->assertRedirect();

        $credits = ClientFreebieCredit::where('anchor_appointment_id', $anchorApptId)->orderBy('slot_number')->get();
        $this->assertSame('available', $credits[0]->status);
        $this->assertSame('available', $credits[1]->status);

        // Travel to Day 6 (2026-11-26 14:00 Asia/Qatar)
        Carbon::setTestNow(Carbon::parse('2026-11-26 14:00:00', 'Asia/Qatar'));

        // 1) Reject service NOT in the 6 eligible mini-services with 422
        $ineligibleResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-26T15:00',
            'booking_date' => '2026-11-26',
            'branch' => 'old_airport',
            'redeem_freebies' => [
                [
                    'credit_id' => $credits[0]->id,
                    'service_id' => $f['nonEligibleService']->id,
                    'staff_id' => $f['seniorColorist']->id,
                ],
            ],
        ]);
        $ineligibleResp->assertStatus(422);
        $this->assertSame('available', $credits[0]->fresh()->status);

        // 2) Succeed on Day 6 at 0.00 QAR for an eligible mini-service
        $eligibleMini = $f['eligibleServices'][2]; // Express Manicure (25 min)
        $validRedeemResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-26T15:00',
            'booking_date' => '2026-11-26',
            'branch' => 'old_airport',
            'redeem_freebies' => [
                [
                    'credit_id' => $credits[0]->id,
                    'service_id' => $eligibleMini->id,
                    'staff_id' => $f['juniorBeautician']->id,
                ],
            ],
        ]);
        $validRedeemResp->assertStatus(201)
            ->assertJsonPath('appointment.price', 0)
            ->assertJsonPath('appointment.is_freebie_redemption', true);

        $redemptionAppt = Appointment::findOrFail($validRedeemResp->json('appointment.id'));
        $this->assertSame(0.00, (float) $redemptionAppt->price);
        $this->assertSame('scheduled', $credits[0]->fresh()->status);
        $this->assertSame($redemptionAppt->id, (int) $credits[0]->fresh()->redemption_appointment_id);

        // Cancelling a future scheduled redemption before expiry releases slot back to 'available'
        $this->actingAs($this->admin)->patchJson(route('appointments.updateStatus', $redemptionAppt->id), [
            'status' => 'cancelled',
        ])->assertOk();
        $this->assertSame('available', $credits[0]->fresh()->status);
        $this->assertNull($credits[0]->fresh()->redemption_appointment_id);

        // Re-book Slot #1 on Day 6 and mark Completed -> flips to 'redeemed' with 0.00 QAR Finance impact
        $rebookRedeemResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-26T16:00',
            'booking_date' => '2026-11-26',
            'branch' => 'old_airport',
            'redeem_freebies' => [
                [
                    'credit_id' => $credits[0]->id,
                    'service_id' => $eligibleMini->id,
                    'staff_id' => $f['juniorBeautician']->id,
                ],
            ],
        ]);
        $rebookRedeemResp->assertStatus(201);
        $completedRedeemId = $rebookRedeemResp->json('appointment.id');

        $this->actingAs($this->admin)->patchJson(route('appointments.updateStatus', $completedRedeemId), [
            'status' => 'completed',
        ])->assertOk();
        $this->assertSame('redeemed', $credits[0]->fresh()->status);
        $this->assertNotNull($credits[0]->fresh()->redeemed_at);

        // Finance ledger check: strictly 299.00 QAR total net sales across the entire flow
        $financeResp = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]));
        $financeResp->assertOk();
        $this->assertSame(299.00, (float) Sale::sum('total_amount'));

        // 3) Travel to Day 8 (2026-11-28 09:00 Asia/Qatar, past 2026-11-27 23:59:59) -> Slot #2 evaluates as Expired and rejects redemption (422)
        Carbon::setTestNow(Carbon::parse('2026-11-28 09:00:00', 'Asia/Qatar'));

        $slot2 = $credits[1]->fresh();
        $this->assertSame('available', $slot2->status); // raw DB status untouched (no cron needed)
        $this->assertTrue($slot2->isDynamicallyExpired());
        $this->assertSame('expired', $slot2->effective_status);
        $this->assertFalse($slot2->canRedeem());

        $day8Resp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-28T13:00',
            'booking_date' => '2026-11-28',
            'branch' => 'old_airport',
            'redeem_freebies' => [
                [
                    'credit_id' => $slot2->id,
                    'service_id' => $f['eligibleServices'][1]->id,
                    'staff_id' => $f['juniorBeautician']->id,
                ],
            ],
        ]);
        $day8Resp->assertStatus(422);
    }

    public function test_cancelling_anchor_booking_voids_all_linked_freebie_credits(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 11:00:00', 'Asia/Qatar'));
        $f = $this->createFreebiesCatalogFixture();

        $package = ServiceFreebiePackage::create([
            'name' => 'Highlights + 2 Free Services',
            'anchor_service_id' => $f['anchorService']->id,
            'price' => 299.00,
            'free_choices_count' => 2,
            'validity_days' => 7,
            'is_active' => true,
        ]);
        $package->eligibleServices()->sync($f['eligibleServices']->pluck('id')->all());

        $bookResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $f['customer']->name,
            'phone' => $f['customer']->phone,
            'appointment_datetime' => '2026-11-20T14:00',
            'booking_date' => '2026-11-20',
            'branch' => 'old_airport',
            'freebie_packages' => [
                [
                    'package_id' => $package->id,
                    'anchor_staff_id' => $f['seniorColorist']->id,
                    'slots' => [
                        ['mode' => 'same_day', 'service_id' => $f['eligibleServices'][0]->id, 'staff_id' => $f['juniorBeautician']->id],
                        ['mode' => 'save_for_later'],
                    ],
                ],
            ],
        ]);
        $bookResp->assertStatus(201);
        $anchorId = $bookResp->json('appointment.id');

        $cancelResp = $this->actingAs($this->admin)->patchJson(route('appointments.updateStatus', $anchorId), [
            'status' => 'cancelled',
        ]);
        $cancelResp->assertOk();

        $credits = ClientFreebieCredit::where('anchor_appointment_id', $anchorId)->get();
        $this->assertCount(2, $credits);
        foreach ($credits as $credit) {
            $this->assertSame('voided', $credit->status);
            $this->assertFalse($credit->canRedeem());
        }

        $sameDayFreebieSlice = AppointmentService::where('appointment_id', $anchorId)
            ->where('is_freebie_redemption', true)
            ->firstOrFail();
        $this->assertSame('cancelled', $sameDayFreebieSlice->status);
    }

    public function test_package_links_master_services_rejects_anchor_in_pool_and_reflects_live_service_updates(): void
    {
        $f = $this->createFreebiesCatalogFixture();
        $anchor = $f['anchorService'];
        $mini1 = $f['eligibleServices'][0];
        $mini2 = $f['eligibleServices'][1];
        $mini3 = $f['eligibleServices'][2];

        // 1) Reject selecting the Main Anchor Service inside the Eligible Mini-Services Pool
        $overlapResp = $this->actingAs($this->admin)->postJson(route('service-freebies.store'), [
            'name' => 'Invalid Overlap Package',
            'anchor_service_id' => $anchor->id,
            'price' => 299.00,
            'free_choices_count' => 2,
            'validity_days' => 7,
            'is_active' => true,
            'eligible_service_ids' => [$anchor->id, $mini1->id, $mini2->id],
        ]);
        $overlapResp->assertStatus(422)->assertJsonValidationErrors(['eligible_service_ids']);

        // 2) Create package linked via master services.id FKs
        $createResp = $this->actingAs($this->admin)->postJson(route('service-freebies.store'), [
            'name' => 'Color Glow + 2 Freebies',
            'anchor_service_id' => $anchor->id,
            'price' => 299.00,
            'free_choices_count' => 2,
            'validity_days' => 7,
            'is_active' => true,
            'eligible_service_ids' => [$mini1->id, $mini2->id],
        ]);
        $createResp->assertStatus(201)
            ->assertJsonPath('package.anchor_service_id', $anchor->id)
            ->assertJsonPath('package.anchor_category_name', 'Hair Color')
            ->assertJsonPath('package.anchor_price', 350);

        $pkgId = $createResp->json('package.id');

        // 3) Edit package to swap eligible pool & anchor (still rejecting anchor inside pool)
        $this->actingAs($this->admin)->putJson(route('service-freebies.update', $pkgId), [
            'name' => 'Color Glow + 2 Freebies Updated',
            'anchor_service_id' => $anchor->id,
            'price' => 310.00,
            'free_choices_count' => 2,
            'validity_days' => 10,
            'is_active' => true,
            'eligible_service_ids' => [$mini1->id, $mini3->id],
        ])->assertOk()
            ->assertJsonPath('package.name', 'Color Glow + 2 Freebies Updated');

        $package = ServiceFreebiePackage::with(['anchorService', 'eligibleServices'])->findOrFail($pkgId);
        $this->assertSame($anchor->id, (int) $package->anchor_service_id);
        $this->assertEqualsCanonicalizing([$mini1->id, $mini3->id], $package->eligibleServices->pluck('id')->all());

        // 4) Modal uses custom searchable combobox (not native <select>) bound to master Services catalog
        $pageResp = $this->actingAs($this->admin)->get(route('services.index', ['tab' => 'freebies']));
        $pageResp->assertOk()
            ->assertSee('id="freebieAnchorSearch"', false)
            ->assertSee('id="freebieAnchorDropdown"', false)
            ->assertSee('id="freebieAnchorClearBtn"', false)
            ->assertDontSee('<select id="freebieAnchorService"', false);

        // 5) Updating Anchor Service & Mini-Service name/price in Services module immediately reflects in the package
        $this->actingAs($this->admin)->put(route('services.update', $anchor->id), [
            'name' => 'Signature Gloss Hair Color',
            'category_id' => $anchor->category_id,
            'price' => 420.00,
            'duration' => 105,
        ])->assertRedirect();

        $this->actingAs($this->admin)->put(route('services.update', $mini1->id), [
            'name' => 'Precision Brow Shaping',
            'category_id' => $mini1->category_id,
            'price' => 45.00,
            'duration' => 20,
        ])->assertRedirect();

        $this->actingAs($this->admin)->getJson(route('service-freebies.index'))
            ->assertOk()
            ->assertJsonPath('0.anchor_service_name', 'Signature Gloss Hair Color')
            ->assertJsonPath('0.anchor_price', 420)
            ->assertJsonPath('0.anchor_duration', 105)
            ->assertJsonFragment([
                'id' => $mini1->id,
                'name' => 'Precision Brow Shaping',
                'price' => 45,
                'duration' => 20,
                'category_name' => 'Express Beauty',
            ]);

        $this->actingAs($this->admin)->get(route('services.index', ['tab' => 'freebies']))
            ->assertOk()
            ->assertSee('Signature Gloss Hair Color')
            ->assertSee('420.00 QAR')
            ->assertSee('Precision Brow Shaping')
            ->assertSee('45.00 QAR');
    }
}
