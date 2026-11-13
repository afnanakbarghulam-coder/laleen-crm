<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\LaleenCommandCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CrmPhaseTwoHandshakeAndAttributionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $primaryStylist;
    private Staff $secondaryStylist;
    private Staff $replacementStylist;
    private Staff $wakrahStylist;
    private Service $facialService;
    private Service $nailsService;
    private Product $retailSerum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Phase Two Admin',
            'email' => 'phase2-admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->facialService = Service::create([
            'name' => 'Signature HydraFacial',
            'price' => 300,
            'duration' => 60,
        ]);

        $this->nailsService = Service::create([
            'name' => 'Gel BIAB Manicure',
            'price' => 250,
            'duration' => 45,
        ]);

        $this->retailSerum = Product::create([
            'name' => 'Peptide Barrier Serum',
            'price' => 80,
            'stock_quantity' => 20,
            'status' => 'active',
        ]);

        $this->primaryStylist = Staff::create([
            'name' => 'Sara Facialist',
            'role' => 'Esthetician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->primaryStylist->services()->sync([$this->facialService->id]);

        $this->secondaryStylist = Staff::create([
            'name' => 'Elena Nail Artist',
            'role' => 'Nail Technician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->secondaryStylist->services()->sync([$this->nailsService->id]);

        $this->replacementStylist = Staff::create([
            'name' => 'Maya Senior Facialist',
            'role' => 'Esthetician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->replacementStylist->services()->sync([$this->facialService->id]);

        $this->wakrahStylist = Staff::create([
            'name' => 'Huda Wakrah Specialist',
            'role' => 'Nail Technician',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->wakrahStylist->services()->sync([$this->nailsService->id, $this->facialService->id]);
    }

    public function test_cancelled_and_no_show_reverse_handshake_updates_lead_and_prevents_kpi_inflation(): void
    {
        $customer = Customer::create([
            'name' => 'Noor Al-Kuwari',
            'phone' => '55112233',
        ]);

        $lead = Lead::create([
            'phone' => '55112233',
            'customer_id' => $customer->id,
            'assigned_agent_id' => $this->admin->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => [$this->facialService->name],
            'next_followup_date' => now()->addDay()->toDateString(),
        ]);

        // Book first appointment -> lead becomes 'booked' with appointment_id set
        $slotOne = now()->addDays(2)->setTime(13, 0, 0);
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Noor Al-Kuwari',
                'phone' => '55112233',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => $slotOne->format('Y-m-d H:i:s'),
                'branch' => 'old_airport',
                'staff_id' => $this->primaryStylist->id,
                'status' => 'confirmed',
                'price' => 300,
            ])
            ->assertRedirect();

        $apptOne = Appointment::where('customer_id', $customer->id)->firstOrFail();
        $lead->refresh();
        $this->assertSame('booked', $lead->needful_done);
        $this->assertSame($apptOne->id, $lead->appointment_id);

        // Executive KPI scorecard & Leads Analytics both count 1 booked conversion
        $kpiBookedResp = $this->actingAs($this->admin)->get(route('kpi.hub'));
        $kpiBookedResp->assertOk();
        $this->assertSame(1, $kpiBookedResp->viewData('scorecard')['laden']['booked_leads_count']);

        $analyticsBookedResp = $this->actingAs($this->admin)->get(route('leads.analytics', ['period' => 'this_month']));
        $analyticsBookedResp->assertOk()->assertViewHas('bookedLeadsCount', 1);

        // Book a second active appointment for the same customer on another day
        $slotTwo = now()->addDays(4)->setTime(14, 0, 0);
        $apptTwo = Appointment::create([
            'customer_name' => 'Noor Al-Kuwari',
            'phone' => '55112233',
            'customer_id' => $customer->id,
            'service_name' => $this->facialService->name,
            'appointment_datetime' => $slotTwo,
            'booking_date' => $slotTwo->toDateString(),
            'start_time' => '14:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->primaryStylist->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        // Cancel apptOne while apptTwo is still active -> lead re-links to apptTwo and stays 'booked'
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $apptOne->id), ['status' => 'cancelled'])
            ->assertOk();

        $lead->refresh();
        $this->assertSame('booked', $lead->needful_done);
        $this->assertSame($apptTwo->id, $lead->appointment_id);
        $this->assertContains('cancel', $lead->categories_list);

        // Now mark apptTwo as 'no_show' (no active bookings left!) -> lead clears appointment_id & needful_done and sets follow-up today
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $apptTwo->id), ['status' => 'no_show'])
            ->assertOk();

        $lead->refresh();
        $this->assertNull($lead->appointment_id);
        $this->assertNull($lead->needful_done);
        $this->assertSame(now()->toDateString(), $lead->next_followup_date?->toDateString());
        $this->assertContains('no_show', $lead->categories_list);

        // KPI scorecard & Leads Analytics drop booked conversion count to 0
        $kpiAfterCancelResp = $this->actingAs($this->admin)->get(route('kpi.hub'));
        $kpiAfterCancelResp->assertOk();
        $this->assertSame(0, $kpiAfterCancelResp->viewData('scorecard')['laden']['booked_leads_count']);

        $analyticsAfterCancelResp = $this->actingAs($this->admin)->get(route('leads.analytics', ['period' => 'this_month']));
        $analyticsAfterCancelResp->assertOk()->assertViewHas('bookedLeadsCount', 0);

        // Walk-in cancellation with no prior Lead creates a follow-up lead with appointment_id = null & next_followup_date = today
        $walkInAppt = Appointment::create([
            'customer_name' => 'Walk-in Cancel Client',
            'phone' => '66998877',
            'service_name' => $this->facialService->name,
            'appointment_datetime' => now()->addDays(3)->setTime(15, 0, 0),
            'booking_date' => now()->addDays(3)->toDateString(),
            'start_time' => '15:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->primaryStylist->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $walkInAppt->id), ['status' => 'cancelled'])
            ->assertOk();

        $walkInLead = Lead::where('phone', '66998877')->firstOrFail();
        $this->assertNull($walkInLead->appointment_id);
        $this->assertNull($walkInLead->needful_done);
        $this->assertSame(now()->toDateString(), $walkInLead->next_followup_date?->toDateString());
        $this->assertContains('cancel', $walkInLead->categories_list);
    }

    public function test_phantom_booked_leads_eliminated_and_unverified_booked_lead_can_be_reverted(): void
    {
        // 1. Logging an AdLeadEntry with manual branch + ticket_amount and ZERO calendar/POS activity must NOT mark CRM Lead as 'booked'
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => now()->toDateString(),
                'customer_name' => 'Phantom Test Client',
                'country_code' => '+974',
                'phone_number' => '7744 5566',
                'category' => 'Facial',
                'branch' => 'old_airport',
                'ticket_amount' => 400,
                'remarks' => 'Manual entry before calendar booking',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $manualEntry = AdLeadEntry::where('phone', '77445566')->firstOrFail();
        $this->assertNull($manualEntry->lead_id);
        $this->assertSame(0, Lead::where('phone', '77445566')->count(), 'AdLeadEntry store must never create a row in leads');

        // 2. Legacy phantom-booked lead (needful_done = 'booked', appointment_id = null, no active booking/sale) can be manually unlocked
        $standaloneLead = Lead::create([
            'phone' => '77445566',
            'customer_id' => $manualEntry->customer_id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => [$this->facialService->name],
            'needful_done' => 'booked',
            'next_followup_date' => null,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('leads.needful-done', $standaloneLead), ['needful_done' => 'yes'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $standaloneLead->refresh();
        $this->assertSame('yes', $standaloneLead->needful_done);
        $this->assertSame(now()->toDateString(), $standaloneLead->next_followup_date?->toDateString());

        // 3. Deleting an AdLeadEntry NEVER mutates or reopens rows in the separate `leads` table
        $standaloneLead->update(['needful_done' => 'booked', 'next_followup_date' => null]);

        $this->actingAs($this->admin)
            ->delete(route('kpi.ad-leads.destroy', $manualEntry))
            ->assertRedirect(route('kpi.ads.index'));

        $standaloneLead->refresh();
        $this->assertSame('booked', $standaloneLead->needful_done);
        $this->assertNull($standaloneLead->next_followup_date);

        // 4. Command Center marketing pulse ignores 0 QAR row with only a branch set
        AdLeadEntry::create([
            'date' => now()->toDateString(),
            'customer_name' => 'Unpaid Branch Row',
            'phone' => '55009988',
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 0,
        ]);

        $pulse = app(LaleenCommandCenterService::class)->read_marketing_and_ads_pulse();
        $this->assertSame(1, $pulse['ad_leads_total']);
        $this->assertSame(0, $pulse['ad_leads_booked']);
    }

    public function test_reschedule_reactivates_cancelled_booking_and_preserves_multi_stylist_service_splits(): void
    {
        $customer = Customer::create([
            'name' => 'Layla Multi-Stylist',
            'phone' => '55223344',
        ]);

        $oldDate = Carbon::parse('2026-11-14 13:00:00');
        $appointment = Appointment::create([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'service_name' => $this->facialService->name . ', ' . $this->nailsService->name,
            'appointment_datetime' => $oldDate,
            'booking_date' => $oldDate->toDateString(),
            'start_time' => '13:00:00',
            'branch' => 'old_airport',
            'staff_id' => $this->primaryStylist->id,
            'status' => 'cancelled',
            'price' => 550,
        ]);

        $line1 = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $this->facialService->id,
            'staff_id' => $this->primaryStylist->id,
            'name' => $this->facialService->name,
            'price' => 300,
            'original_price' => 300,
            'duration' => 60,
            'start_time' => $oldDate->copy(),
        ]);

        $line2 = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $this->nailsService->id,
            'staff_id' => $this->secondaryStylist->id,
            'name' => $this->nailsService->name,
            'price' => 250,
            'original_price' => 250,
            'duration' => 45,
            'start_time' => $oldDate->copy()->addMinutes(60),
        ]);

        $lead = Lead::create([
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'category' => 'cancel',
            'categories' => ['cancel'],
            'service_interest' => [$this->facialService->name],
            'needful_done' => null,
            'next_followup_date' => now()->toDateString(),
        ]);

        // Reschedule cancelled booking to replacementStylist (who ONLY has facialService skill, NOT nailsService!)
        $newDate = Carbon::parse('2026-11-16 15:00:00');
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.reschedule', $appointment->id), [
                'appointment_datetime' => $newDate->format('Y-m-d H:i:s'),
                'staff_id' => $this->replacementStylist->id,
                'branch' => 'old_airport',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $appointment->refresh();
        $line1->refresh();
        $line2->refresh();
        $lead->refresh();

        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame($this->replacementStylist->id, (int) $appointment->staff_id);
        $this->assertSame($this->replacementStylist->id, (int) $line1->staff_id);
        $this->assertSame($this->secondaryStylist->id, (int) $line2->staff_id, 'Secondary stylist on line 2 must be preserved!');
        $this->assertSame('2026-11-16 15:00:00', $line1->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-16 16:00:00', $line2->start_time->format('Y-m-d H:i:s'));

        $this->assertSame('booked', $lead->needful_done);
        $this->assertSame($appointment->id, $lead->appointment_id);
    }

    public function test_update_service_date_shift_and_appointment_destroy_cleanly_recalculate_leads_and_ad_attribution(): void
    {
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-02',
                'customer_name' => 'Aisha Service Shift',
                'country_code' => '+974',
                'phone_number' => '5566 7788',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $adEntry = AdLeadEntry::where('phone', '55667788')->firstOrFail();

        $explicitLead = Lead::create([
            'phone' => '55667788',
            'customer_id' => $adEntry->customer_id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => [$this->facialService->name],
            'next_followup_date' => '2026-11-03',
        ]);

        // Book paid/completed standalone appointment on 2026-11-05 -> attributes 300 QAR to adEntry and marks Lead booked
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Aisha Service Shift',
                'phone' => '55667788',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-11-05 13:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->primaryStylist->id,
                'status' => 'completed',
                'price' => 300,
            ])
            ->assertRedirect();

        $appointment = Appointment::where('customer_id', $adEntry->customer_id)->firstOrFail();
        $serviceLine = $appointment->appointmentServices()->firstOrFail();

        $adEntry->refresh();
        $this->assertSame('old_airport', $adEntry->branch);
        $this->assertSame(300.0, (float) $adEntry->ticket_amount);

        // Move single service line via updateService outside 30-day window (2026-12-20) -> $previousDate resets adEntry!
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.services.update', [$appointment->id, $serviceLine->id]), [
                'service_id' => $this->facialService->id,
                'price' => 300,
                'duration' => 60,
                'start_time' => '2026-12-20 13:00:00',
                'staff_id' => $this->primaryStylist->id,
            ])
            ->assertOk();

        $adEntry->refresh();
        $this->assertNull($adEntry->branch);
        $this->assertSame(0.0, (float) $adEntry->ticket_amount);

        // Move back inside window (2026-11-10) and record full POS checkout with retail product
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.services.update', [$appointment->id, $serviceLine->id]), [
                'service_id' => $this->facialService->id,
                'price' => 300,
                'duration' => 60,
                'start_time' => '2026-11-10 13:00:00',
                'staff_id' => $this->primaryStylist->id,
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $appointment->id), [
                'products' => [
                    [
                        'product_id' => $this->retailSerum->id,
                        'quantity' => 1,
                        'staff_id' => $this->primaryStylist->id,
                    ],
                ],
                'payments' => ['card' => 380],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $adEntry->refresh();
        $this->assertSame('old_airport', $adEntry->branch);
        $this->assertSame(380.0, (float) $adEntry->ticket_amount);
        $this->assertSame(19, $this->retailSerum->fresh()->stock_quantity);

        // Delete appointment -> reverses Sale & product stock, reopens Lead with follow-up today, resets AdLeadEntry to 0
        $this->actingAs($this->admin)
            ->delete(route('appointments.destroy', $appointment->id))
            ->assertRedirect();

        $this->assertNull(Appointment::find($appointment->id));
        $this->assertSame(20, $this->retailSerum->fresh()->stock_quantity);

        $lead = Lead::where('customer_id', $adEntry->customer_id)->firstOrFail();
        $this->assertNull($lead->appointment_id);
        $this->assertNull($lead->needful_done);
        $this->assertSame(now()->toDateString(), $lead->next_followup_date?->toDateString());
        $this->assertContains('cancel', $lead->categories_list);

        $adEntry->refresh();
        $this->assertNull($adEntry->branch);
        $this->assertSame(0.0, (float) $adEntry->ticket_amount);
    }

    public function test_same_calendar_month_window_and_last_touch_single_winner_prevent_double_counting_and_branch_hijack(): void
    {
        // Scenario A: Strict same-month boundary (Nov 29 Ad Lead -> Dec 02 Checkout stays isolated; Nov 30 Checkout attributes)
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-29',
                'customer_name' => 'Cross Month Client',
                'country_code' => '+974',
                'phone_number' => '5599 1100',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
            ]);

        $nov29Entry = AdLeadEntry::where('phone', '55991100')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Cross Month Client',
                'phone' => '55991100',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-12-02 14:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->primaryStylist->id,
                'status' => 'confirmed',
                'price' => 300,
            ]);

        $dec2Appt = Appointment::where('customer_id', $nov29Entry->customer_id)->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $dec2Appt->id), [
                'payments' => ['card' => 300],
            ]);

        $nov29Entry->refresh();
        $this->assertNull($nov29Entry->branch, 'December checkout must NOT bleed back into November AdLeadEntry');
        $this->assertSame(0.0, (float) $nov29Entry->ticket_amount);

        // Same-month checkout on Nov 30 DOES attribute to Nov 29 AdLeadEntry
        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Cross Month Client',
                'phone' => '55991100',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-11-30 14:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->primaryStylist->id,
                'status' => 'confirmed',
                'price' => 300,
            ]);

        $nov30Appt = Appointment::where('customer_id', $nov29Entry->customer_id)
            ->whereDate('booking_date', '2026-11-30')
            ->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $nov30Appt->id), [
                'payments' => ['card' => 300],
            ]);

        $nov29Entry->refresh();
        $this->assertSame('old_airport', $nov29Entry->branch);
        $this->assertSame(300.0, (float) $nov29Entry->ticket_amount);

        // Scenario B: Two AdLeadEntries in the same month across Old Airport & Al Wakrah (zero double-counting, zero branch hijack!)
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-02',
                'customer_name' => 'Dual Branch Client',
                'country_code' => '+974',
                'phone_number' => '6611 2299',
                'category' => 'Facial',
                'ticket_amount' => 0,
                'branch' => null,
            ]);

        $oaEntry = AdLeadEntry::where('phone', '66112299')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Dual Branch Client',
                'phone' => '66112299',
                'service_name' => [$this->facialService->name],
                'appointment_datetime' => '2026-11-04 13:00:00',
                'branch' => 'old_airport',
                'staff_id' => $this->primaryStylist->id,
                'status' => 'confirmed',
                'price' => 300,
            ]);

        $oaAppt = Appointment::where('customer_id', $oaEntry->customer_id)->latest('id')->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $oaAppt->id), [
                'products' => [
                    [
                        'product_id' => $this->retailSerum->id,
                        'quantity' => 1,
                        'staff_id' => $this->primaryStylist->id,
                    ],
                ],
                'payments' => ['card' => 380],
            ]);

        $oaEntry->refresh();
        $this->assertSame('old_airport', $oaEntry->branch);
        $this->assertSame(380.0, (float) $oaEntry->ticket_amount);

        // Client clicks a Nails ad on Nov 15 and visits Al Wakrah on Nov 18 for 250 QAR
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-11-15',
                'customer_name' => 'Dual Branch Client',
                'country_code' => '+974',
                'phone_number' => '6611 2299',
                'category' => 'Nails',
                'ticket_amount' => 0,
                'branch' => null,
            ]);

        $wkEntry = AdLeadEntry::where('phone', '66112299')->whereDate('date', '2026-11-15')->firstOrFail();
        $this->assertNull($wkEntry->branch);
        $this->assertSame(0.0, (float) $wkEntry->ticket_amount);

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => 'Dual Branch Client',
                'phone' => '66112299',
                'service_name' => [$this->nailsService->name],
                'appointment_datetime' => '2026-11-18 14:00:00',
                'branch' => 'wakrah',
                'staff_id' => $this->wakrahStylist->id,
                'status' => 'confirmed',
                'price' => 250,
            ]);

        $wkAppt = Appointment::where('customer_id', $oaEntry->customer_id)->latest('id')->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $wkAppt->id), [
                'payments' => ['cash' => 250],
            ]);

        $oaEntry->refresh();
        $wkEntry->refresh();

        // First ad strictly keeps Old Airport 380 QAR; second ad strictly gets Al Wakrah 250 QAR!
        $this->assertSame('old_airport', $oaEntry->branch);
        $this->assertSame(380.0, (float) $oaEntry->ticket_amount);
        $this->assertSame('wakrah', $wkEntry->branch);
        $this->assertSame(250.0, (float) $wkEntry->ticket_amount);
    }

    public function test_lead_deletion_nulls_ad_lead_entry_fk_and_phone_normalization_preserves_customer_identity(): void
    {
        // 1. +1 USA/Canada, trunk leading zero stripping, and Doha landline preservation
        $this->assertArrayHasKey('1', Lead::COUNTRY_CODES);
        $this->assertSame('12125551234', Lead::normalizePhone('1', '(212) 555-1234'));
        $this->assertSame('12125551234', Customer::normalizePhone('+1 212-555-1234'));
        $this->assertSame('447700900123', Lead::normalizePhone('44', '07700 900123'));
        $this->assertSame('447700900123', Customer::normalizePhone('+44 07700 900123'));
        $this->assertSame('55123456', Lead::normalizePhone('974', '05512 3456'));
        $this->assertSame('55123456', Customer::normalizePhone('+974 05512 3456'));
        $this->assertSame('44123456', Customer::normalizePhone('44123456')); // Doha 8-digit landline preserved

        // 2. Customer profile lookup & calendar prefill normalize pasted +974 numbers, and LeadController preserves existing full name
        $vipCustomer = Customer::create([
            'name' => 'Sheikha Al-Thani',
            'phone' => '55881122',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('customers.lookup', ['phone' => '+974 5588 1122']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'id' => $vipCustomer->id,
                'name' => 'Sheikha Al-Thani',
            ]);

        $calendarResp = $this->actingAs($this->admin)
            ->get(route('appointments.calendar', ['phone' => '+974 5588 1122', 'open_booking' => 1]));
        $calendarResp->assertOk();
        $this->assertSame($vipCustomer->id, $calendarResp->viewData('bookingPrefill')['customer_id']);
        $this->assertSame('55881122', $calendarResp->viewData('bookingPrefill')['phone']);

        // Creating a lead with a short nickname must NOT overwrite 'Sheikha Al-Thani'
        $this->actingAs($this->admin)
            ->post(route('leads.store'), [
                'country_code' => '974',
                'phone_number' => '5588 1122',
                'customer_name' => 'Sheikha',
                'category' => 'inquiry',
                'service_interest' => [$this->facialService->name],
                'next_followup_date' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('Sheikha Al-Thani', $vipCustomer->fresh()->name);

        // 3. Deleting a Lead sets ad_lead_entries.lead_id to null cleanly via FK / deleting hook
        $lead = Lead::where('customer_id', $vipCustomer->id)->firstOrFail();
        $adEntry = AdLeadEntry::create([
            'date' => now()->toDateString(),
            'customer_id' => $vipCustomer->id,
            'lead_id' => $lead->id,
            'customer_name' => $vipCustomer->name,
            'phone' => $vipCustomer->phone,
            'category' => 'Facial',
            'ticket_amount' => 0,
            'branch' => null,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('leads.destroy', $lead))
            ->assertRedirect();

        $this->assertNull(Lead::find($lead->id));
        $this->assertNull($adEntry->fresh()->lead_id);
    }
}
