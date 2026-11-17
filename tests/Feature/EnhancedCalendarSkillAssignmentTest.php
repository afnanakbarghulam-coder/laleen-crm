<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnhancedCalendarSkillAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $babita;
    private Staff $putu;
    private Staff $khushi;
    private Staff $wakrahSpecialist;
    private Service $svcManicure;
    private Service $svcMassage;
    private Service $svcShama;
    private Service $svcWax;
    private Combo $fourServiceCombo;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => null,
        ]);

        $this->svcManicure = Service::create([
            'name' => 'Full Manicure',
            'category' => 'Nails',
            'price' => 60,
            'duration' => 45,
        ]);
        $this->svcMassage = Service::create([
            'name' => 'Full Body Massage',
            'category' => 'Spa',
            'price' => 120,
            'duration' => 60,
        ]);
        $this->svcShama = Service::create([
            'name' => 'Full Face Shama',
            'category' => 'Threading & Wax',
            'price' => 50,
            'duration' => 30,
        ]);
        $this->svcWax = Service::create([
            'name' => 'Underarms Wax',
            'category' => 'Threading & Wax',
            'price' => 35,
            'duration' => 20,
        ]);

        $this->babita = Staff::create([
            'name' => 'Babita',
            'role' => 'Nail & Wax Specialist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->babita->services()->sync([$this->svcManicure->id, $this->svcWax->id]);

        $this->putu = Staff::create([
            'name' => 'Putu',
            'role' => 'Massage Therapist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->putu->services()->sync([$this->svcMassage->id]);

        $this->khushi = Staff::create([
            'name' => 'Khushi',
            'role' => 'Beauty Specialist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->khushi->services()->sync([$this->svcShama->id]);

        $this->wakrahSpecialist = Staff::create([
            'name' => 'Layla Wakrah',
            'role' => 'Senior Specialist',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->wakrahSpecialist->services()->sync([
            $this->svcManicure->id,
            $this->svcMassage->id,
            $this->svcShama->id,
            $this->svcWax->id,
        ]);

        $this->fourServiceCombo = Combo::create([
            'name' => '4 Services for 180 QAR',
            'price' => 180,
            'quantity_included' => 4,
            'validity_days' => 7,
            'status' => 'active',
        ]);
        $this->fourServiceCombo->services()->sync([
            $this->svcManicure->id,
            $this->svcMassage->id,
            $this->svcShama->id,
            $this->svcWax->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'Noura Al Thani',
            'phone' => '+97455112233',
        ]);
    }

    private function openWeekday(int $daysAhead = 2): Carbon
    {
        $date = Carbon::today()->addDays($daysAhead)->setTime(15, 0, 0);
        if ($date->isFriday()) {
            $date->addDay();
        }

        return $date;
    }

    public function test_1_skill_filtering_enforces_mapped_skills_and_gracefully_falls_back_when_unmapped_in_branch(): void
    {
        $slot = $this->openWeekday(2);
        $dateStr = $slot->toDateString();

        // Enhanced Calendar view exposes LaleenSliceEngine + skill_ids + Unmapped Skill fallback optgroup
        $calResp = $this->actingAs($this->admin)->get(route('appointments.calendar'));
        $calResp->assertOk()
            ->assertSee('window.LaleenSliceEngine', false)
            ->assertSee('Unmapped Skill — All Branch Staff', false)
            ->assertSee('laleen-specialist-pill', false);

        // Assigning Babita (Manicure/Wax only) to Full Body Massage fails with 422 missing_skill naming Putu
        $badComboResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [$this->svcManicure->id, $this->svcMassage->id],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcMassage->id => $this->babita->id,
                    ],
                ],
            ],
        ]);

        $badComboResp->assertStatus(422)
            ->assertJsonPath('reason', 'missing_skill');
        $this->assertStringContainsString('Putu', (string) $badComboResp->json('message'));

        // Graceful Safety Fallback: a newly created service with 0 mapped staff in Old Airport allows active branch staff
        $unmappedService = Service::create([
            'name' => 'Luxury Gold Facial',
            'category' => 'Facial',
            'price' => 250,
            'duration' => 45,
        ]);

        $availResp = $this->actingAs($this->admin)->getJson(route('appointments.availableStaff', [
            'services' => [$unmappedService->name],
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
        ]));
        $availResp->assertOk();
        $availNames = array_column($availResp->json(), 'name');
        $this->assertContains('Babita', $availNames);
        $this->assertNotContains('Layla Wakrah', $availNames);

        $fallbackBookResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'service_name' => [$unmappedService->name],
            'service_staff' => [$this->babita->id],
        ]);
        $fallbackBookResp->assertStatus(201)->assertJsonPath('success', true);
    }

    public function test_2_multi_specialist_combo_renders_linked_slices_across_columns_and_reschedules_single_slice_safely(): void
    {
        $slot = $this->openWeekday(3);
        $dateStr = $slot->toDateString();

        $storeResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            // Omit top-level staff_id (simulating hidden TEAM MEMBER field)
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [
                        $this->svcManicure->id,
                        $this->svcMassage->id,
                        $this->svcShama->id,
                        $this->svcWax->id,
                    ],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcMassage->id => $this->putu->id,
                        $this->svcShama->id => $this->khushi->id,
                        $this->svcWax->id => $this->babita->id,
                    ],
                ],
            ],
        ]);

        $storeResp->assertStatus(201);
        $appt = Appointment::findOrFail($storeResp->json('appointment.id'));

        // Parent booking preserves exact 180.00 QAR combo price (zero multiplication) & first slice's specialist
        $this->assertEquals(180.0, (float) $appt->price);
        $this->assertSame($this->babita->id, (int) $appt->staff_id);
        $this->assertSame('15:00:00', $appt->start_time);
        $this->assertSame(155, $appt->visit_span_minutes); // 45 + 60 + 30 + 20 = 155 min

        $lines = $appt->appointmentServices()->get();
        $this->assertCount(4, $lines);
        $this->assertSame('15:00', $lines[0]->start_time->format('H:i'));
        $this->assertSame('15:45', $lines[0]->slice_end_at->format('H:i'));
        $this->assertSame('15:45', $lines[1]->start_time->format('H:i'));
        $this->assertSame('16:45', $lines[1]->slice_end_at->format('H:i'));
        $this->assertSame('16:45', $lines[2]->start_time->format('H:i'));
        $this->assertSame('17:15', $lines[2]->slice_end_at->format('H:i'));
        $this->assertSame('17:15', $lines[3]->start_time->format('H:i'));
        $this->assertSame('17:35', $lines[3]->slice_end_at->format('H:i'));

        // Day calendar JSON renders slices in Babita (2), Putu (1), Khushi (1) without repeating price on secondary slices
        $calData = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]))->assertOk()->json();

        $babitaBlocks = $calData['appointments'][$this->babita->id] ?? [];
        $putuBlocks = $calData['appointments'][$this->putu->id] ?? [];
        $khushiBlocks = $calData['appointments'][$this->khushi->id] ?? [];

        $this->assertCount(2, $babitaBlocks);
        $this->assertCount(1, $putuBlocks);
        $this->assertCount(1, $khushiBlocks);

        $this->assertTrue($babitaBlocks[0]['is_split_booking']);
        $this->assertTrue($babitaBlocks[0]['show_price_badge']);
        $this->assertEquals(180.0, (float) $babitaBlocks[0]['price']);

        // Secondary slices carry null price so 180 QAR is never duplicated across columns
        $this->assertFalse($putuBlocks[0]['show_price_badge']);
        $this->assertNull($putuBlocks[0]['price']);
        $this->assertFalse($khushiBlocks[0]['show_price_badge']);
        $this->assertNull($khushiBlocks[0]['price']);
        $this->assertFalse($babitaBlocks[1]['show_price_badge']);
        $this->assertNull($babitaBlocks[1]['price']);

        // Single-slice drag onto unskilled specialist (dragging Putu's Massage onto Babita) fails with 422 missing_skill
        $massageLine = $lines[1];
        $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $appt), [
            'appointment_datetime' => $dateStr . 'T18:00',
            'staff_id' => $this->babita->id,
            'appointment_service_id' => $massageLine->id,
        ])->assertStatus(422)->assertJsonPath('reason', 'missing_skill');

        // Single-slice drag of Putu's Massage to 18:00 on Putu updates ONLY that slice and keeps 180.00 QAR intact!
        $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $appt), [
            'appointment_datetime' => $dateStr . 'T18:00',
            'staff_id' => $this->putu->id,
            'appointment_service_id' => $massageLine->id,
        ])->assertOk()->assertJsonPath('success', true);

        $appt->refresh();
        $this->assertEquals(180.0, (float) $appt->price);
        $this->assertSame('18:00', $massageLine->fresh()->start_time->format('H:i'));
        $this->assertSame('15:00', $lines[0]->fresh()->start_time->format('H:i'));
        $this->assertSame('16:45', $lines[2]->fresh()->start_time->format('H:i'));
    }

    public function test_3_sort_order_service_reordering_avoids_busy_specialist_window(): void
    {
        $slot = $this->openWeekday(4);
        $dateStr = $slot->toDateString();

        // Putu already has an existing Massage booking from 16:00 to 17:00
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => 'Earlier Spa Client',
            'phone' => '+97455990011',
            'appointment_datetime' => $dateStr . 'T16:00',
            'branch' => 'old_airport',
            'staff_id' => $this->putu->id,
            'service_name' => [$this->svcMassage->name],
        ])->assertStatus(201);

        // Default order at 15:00: Manicure (15:00-15:45, Babita) -> Massage (15:45-16:45, Putu) overlaps Putu's 16:00-17:00!
        $conflictResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [$this->svcManicure->id, $this->svcMassage->id],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcMassage->id => $this->putu->id,
                    ],
                    'service_sort_order' => [
                        $this->svcManicure->id => 0,
                        $this->svcMassage->id => 1,
                    ],
                ],
            ],
        ]);
        $conflictResp->assertStatus(422)->assertJsonPath('reason', 'staff_busy');

        // Reordered via sort_order: Massage FIRST (15:00-16:00, Putu) -> Manicure SECOND (16:00-16:45, Babita) -> 201 OK!
        $reorderedResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [$this->svcManicure->id, $this->svcMassage->id],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcMassage->id => $this->putu->id,
                    ],
                    'service_sort_order' => [
                        $this->svcMassage->id => 0,
                        $this->svcManicure->id => 1,
                    ],
                ],
            ],
        ]);

        $reorderedResp->assertStatus(201)->assertJsonPath('success', true);
        $appt = Appointment::findOrFail($reorderedResp->json('appointment.id'));
        $orderedLines = $appt->appointmentServices()->get();

        $this->assertSame('Full Body Massage', $orderedLines[0]->name);
        $this->assertSame(0, (int) $orderedLines[0]->sort_order);
        $this->assertSame('15:00', $orderedLines[0]->start_time->format('H:i'));
        $this->assertSame('16:00', $orderedLines[0]->slice_end_at->format('H:i'));

        $this->assertSame('Full Manicure', $orderedLines[1]->name);
        $this->assertSame(1, (int) $orderedLines[1]->sort_order);
        $this->assertSame('16:00', $orderedLines[1]->start_time->format('H:i'));
        $this->assertSame('16:45', $orderedLines[1]->slice_end_at->format('H:i'));
    }

    public function test_4_parallel_same_time_execution_shortens_visit_span_and_rejects_self_overlap_on_same_staff(): void
    {
        $slot = $this->openWeekday(5);
        $dateStr = $slot->toDateString();

        // 1. Self-overlap guard: Babita cannot perform Full Manicure + Underarms Wax at the exact same time (`is_parallel = true`)
        $selfOverlapResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [$this->svcManicure->id, $this->svcWax->id],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcWax->id => $this->babita->id,
                    ],
                    'service_parallel' => [
                        $this->svcManicure->id => false,
                        $this->svcWax->id => true,
                    ],
                ],
            ],
        ]);
        $selfOverlapResp->assertStatus(422)->assertJsonPath('reason', 'staff_busy');

        // 2. Different specialists running in parallel:
        //    - Babita (Manicure 45m: 15:00-15:45) + Putu (Massage 60m, parallel: 15:00-16:00)
        //    - Khushi (Shama 30m, sequential: 16:00-16:30)
        //    - Babita (Wax 20m, sequential: 16:30-16:50)
        //    Total visit span drops from 155m (2h 35m) to 110m (1h 50m)!
        $parallelResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [
                        $this->svcManicure->id,
                        $this->svcMassage->id,
                        $this->svcShama->id,
                        $this->svcWax->id,
                    ],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcMassage->id => $this->putu->id,
                        $this->svcShama->id => $this->khushi->id,
                        $this->svcWax->id => $this->babita->id,
                    ],
                    'service_parallel' => [
                        $this->svcManicure->id => false,
                        $this->svcMassage->id => true,
                        $this->svcShama->id => false,
                        $this->svcWax->id => false,
                    ],
                ],
            ],
        ]);

        $parallelResp->assertStatus(201)->assertJsonPath('success', true);
        $appt = Appointment::findOrFail($parallelResp->json('appointment.id'));

        $this->assertSame(110, $appt->visit_span_minutes);
        $lines = $appt->appointmentServices()->get();

        $this->assertSame('15:00', $lines[0]->start_time->format('H:i'));
        $this->assertFalse($lines[0]->is_parallel);

        $this->assertSame('15:00', $lines[1]->start_time->format('H:i'));
        $this->assertSame('16:00', $lines[1]->slice_end_at->format('H:i'));
        $this->assertTrue($lines[1]->is_parallel);

        $this->assertSame('16:00', $lines[2]->start_time->format('H:i'));
        $this->assertSame('16:30', $lines[2]->slice_end_at->format('H:i'));
        $this->assertFalse($lines[2]->is_parallel);

        $this->assertSame('16:30', $lines[3]->start_time->format('H:i'));
        $this->assertSame('16:50', $lines[3]->slice_end_at->format('H:i'));
    }

    public function test_5_two_today_plus_two_pending_split_and_zero_of_four_unpicked_combo_team_member_fallback(): void
    {
        $slot = $this->openWeekday(6);
        $dateStr = $slot->toDateString();

        // A) 2 Today + 2 Pending: Pending services add 0 min today & no calendar slices
        $splitResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [$this->svcManicure->id, $this->svcMassage->id],
                    'pending_service_ids' => [$this->svcShama->id, $this->svcWax->id],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcMassage->id => $this->putu->id,
                    ],
                ],
            ],
        ])->assertStatus(201);

        $splitAppt = Appointment::findOrFail($splitResp->json('appointment.id'));
        $this->assertEquals(180.0, (float) $splitAppt->price);
        $this->assertSame(105, $splitAppt->visit_span_minutes); // Only 45m + 60m = 105m today!
        $this->assertCount(2, $splitAppt->appointmentServices()->where('is_pending', false)->get());
        $pendingRows = $splitAppt->appointmentServices()->where('is_pending', true)->get();
        $this->assertCount(2, $pendingRows);
        foreach ($pendingRows as $pRow) {
            $this->assertNull($pRow->staff_id);
            $this->assertNull($pRow->start_time);
            $this->assertNull($pRow->slice_end_at);
        }

        // B) 0/4 Unpicked ("Decide in Salon") Combo uses single TEAM MEMBER fallback
        $unpickedResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => 'Walk-in Combo Guest',
            'phone' => '+97455887766',
            'appointment_datetime' => $dateStr . 'T17:00',
            'branch' => 'old_airport',
            'staff_id' => $this->khushi->id,
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [],
                    'pending_service_ids' => [],
                ],
            ],
        ])->assertStatus(201);

        $unpickedAppt = Appointment::findOrFail($unpickedResp->json('appointment.id'));
        $this->assertEquals(180.0, (float) $unpickedAppt->price);
        $this->assertSame($this->khushi->id, (int) $unpickedAppt->staff_id);
        $this->assertTrue($unpickedAppt->is_combo_tbd);
    }

    public function test_6_checkout_records_180_qar_once_with_fair_prorated_specialist_attribution(): void
    {
        $slot = $this->openWeekday(2);
        $dateStr = $slot->toDateString();

        $storeResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'packages' => [
                1 => [
                    'combo_id' => $this->fourServiceCombo->id,
                    'immediate_service_ids' => [
                        $this->svcManicure->id,
                        $this->svcMassage->id,
                        $this->svcShama->id,
                        $this->svcWax->id,
                    ],
                    'service_staff' => [
                        $this->svcManicure->id => $this->babita->id,
                        $this->svcMassage->id => $this->putu->id,
                        $this->svcShama->id => $this->khushi->id,
                        $this->svcWax->id => $this->babita->id,
                    ],
                ],
            ],
        ])->assertStatus(201);

        $appt = Appointment::findOrFail($storeResp->json('appointment.id'));

        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appt->id), [
            'payments' => ['card' => 180],
        ])->assertRedirect(route('appointments.revenue.index'));

        $sale = Sale::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertEquals(180.0, (float) $sale->total_amount);
        $this->assertEquals(180.0, (float) $sale->packages_total);
        $this->assertEquals(0.0, (float) $sale->services_total);
        $this->assertEquals(0.0, (float) $sale->discount_amount);

        // Verify per-specialist SaleItem attribution links
        $serviceSaleItems = SaleItem::where('sale_id', $sale->id)->where('type', 'service')->get();
        $this->assertCount(4, $serviceSaleItems);
        $this->assertSame(2, $serviceSaleItems->where('staff_id', $this->babita->id)->count());
        $this->assertSame(1, $serviceSaleItems->where('staff_id', $this->putu->id)->count());
        $this->assertSame(1, $serviceSaleItems->where('staff_id', $this->khushi->id)->count());

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        // Overall salon revenue is strictly 180.00 QAR (never inflated by slice count)
        $overallRev = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => $from,
            'to' => $to,
        ]))->assertOk();
        $overallRev->assertViewHas('grossSales', 180.0);

        // Prorated per-specialist attribution: Babita (2/4 = 90 QAR), Putu (1/4 = 45 QAR), Khushi (1/4 = 45 QAR)
        $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => $from,
            'to' => $to,
            'staff_id' => $this->babita->id,
        ]))->assertOk()->assertViewHas('grossSales', 90.0);

        $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => $from,
            'to' => $to,
            'staff_id' => $this->putu->id,
        ]))->assertOk()->assertViewHas('grossSales', 45.0);

        $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => $from,
            'to' => $to,
            'staff_id' => $this->khushi->id,
        ]))->assertOk()->assertViewHas('grossSales', 45.0);
    }

    public function test_7_drawer_combo_services_and_sync_slices_endpoints_support_live_reorder_and_parallel_toggles(): void
    {
        $slot = $this->openWeekday(3);
        $dateStr = $slot->toDateString();

        // Book 2 standalone services sequentially at 15:00 (Manicure 45m Babita -> Massage 60m Putu = 105m)
        $standaloneResp = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'service_name' => [$this->svcManicure->name, $this->svcMassage->name],
            'service_staff' => [$this->babita->id, $this->putu->id],
            'service_sort_order' => [0, 1],
            'service_is_parallel' => [0, 0],
        ])->assertStatus(201);

        $appt = Appointment::findOrFail($standaloneResp->json('appointment.id'));
        $this->assertSame(105, $appt->visit_span_minutes);

        $lines = $appt->appointmentServices()->get();

        // Swap order & enable parallel via PATCH /appointments/{id}/slices: Massage (Putu 15:00-16:00) + Manicure (Babita 15:00-15:45) -> 60m span!
        $syncResp = $this->actingAs($this->admin)->patchJson(route('appointments.slices.sync', $appt), [
            'slices' => [
                [
                    'id' => $lines[1]->id, // Massage
                    'staff_id' => $this->putu->id,
                    'sort_order' => 0,
                    'is_parallel' => false,
                ],
                [
                    'id' => $lines[0]->id, // Manicure
                    'staff_id' => $this->babita->id,
                    'sort_order' => 1,
                    'is_parallel' => true,
                ],
            ],
        ]);

        $syncResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('visit_span_minutes', 60);

        $reloaded = $appt->fresh()->appointmentServices()->get();
        $this->assertSame('Full Body Massage', $reloaded[0]->name);
        $this->assertSame('15:00', $reloaded[0]->start_time->format('H:i'));
        $this->assertSame('Full Manicure', $reloaded[1]->name);
        $this->assertSame('15:00', $reloaded[1]->start_time->format('H:i'));
        $this->assertTrue($reloaded[1]->is_parallel);
    }
}
