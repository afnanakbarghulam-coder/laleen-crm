<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\AgentShiftLog;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\KpiAgentTargetReport;
use App\Models\Lead;
use App\Models\LegalTask;
use App\Models\OpsActionItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\User;
use App\Services\LaleenCommandCenterService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmPhaseOneHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'Afnan Super Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_non_admin_with_staff_management_edit_cannot_escalate_to_admin_or_modify_existing_admin_in_sync_access(): void
    {
        $manager = User::factory()->create([
            'name' => 'Branch Manager',
            'email' => 'manager@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff_management' => 'edit'],
        ]);

        // 1. UI hides <option value="admin"> for non-admins, shows for admins
        $this->actingAs($manager)
            ->get(route('staffs.index'))
            ->assertOk()
            ->assertDontSee('<option value="admin">', false);

        $this->actingAs($this->superAdmin)
            ->get(route('staffs.index'))
            ->assertOk()
            ->assertSee('<option value="admin">', false);

        // 2. Non-admin attempting to create a staff member with access_role='admin' gets 403
        $this->actingAs($manager)
            ->post(route('staffs.store'), [
                'first_name' => 'Rogue',
                'last_name' => 'Escalator',
                'has_access' => '1',
                'access_role' => 'admin',
                'access_email' => 'rogue-admin@laleen.qa',
                'access_password' => 'SecretPass123!',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'rogue-admin@laleen.qa']);
        $this->assertDatabaseMissing('staff', ['first_name' => 'Rogue']);

        // 3. Non-admin attempting to modify or revoke an existing admin linked to a Staff record gets 403
        $existingAdmin = User::factory()->create([
            'name' => 'Existing Admin',
            'email' => 'existing-admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $adminStaff = Staff::create([
            'first_name' => 'Existing',
            'last_name' => 'Admin',
            'name' => 'Existing Admin',
            'role' => 'Owner',
            'branch' => 'old_airport',
            'user_id' => $existingAdmin->id,
        ]);

        $this->actingAs($manager)
            ->put(route('staffs.update', $adminStaff), [
                'first_name' => 'Existing',
                'last_name' => 'Admin',
                'has_access' => '0',
            ])
            ->assertForbidden();

        $this->assertTrue($existingAdmin->fresh()->is_active);
        $this->assertSame('admin', $existingAdmin->fresh()->role);

        // 4. Non-admin CAN still provision a regular 'agent' or 'staff' login
        $this->actingAs($manager)
            ->post(route('staffs.store'), [
                'first_name' => 'Nadia',
                'last_name' => 'Stylist',
                'has_access' => '1',
                'access_role' => 'staff',
                'access_email' => 'nadia@laleen.qa',
                'access_password' => 'SecretPass123!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'nadia@laleen.qa',
            'role' => 'staff',
            'is_active' => 1,
        ]);
    }

    public function test_non_admin_agent_gets_403_on_command_center_legal_and_write_gates_and_cannot_spoof_profile_name(): void
    {
        $agent = User::factory()->create([
            'name' => 'Regular Agent',
            'email' => 'agent@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'view',
                'clients' => 'view',
                'kpis' => 'view',
                'staff_management' => 'view',
                'finance' => 'view',
            ],
        ]);

        $legalTask = LegalTask::create([
            'title' => 'Commercial Registration Renewal',
            'deadline' => today()->addDays(10)->toDateString(),
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
        ]);

        // 1. Dashboard sidebar hides Command Center and Legal Tasks for non-admins
        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('command-center.index'), false)
            ->assertDontSee(route('legal.tasks.index'), false);

        // 2. Direct access to /command-center/* and /legal/* returns 403 for non-admin
        $this->actingAs($agent)->get(route('command-center.index'))->assertForbidden();
        $this->actingAs($agent)->getJson(route('command-center.telemetry'))->assertForbidden();
        $this->actingAs($agent)->postJson(route('command-center.ask'), ['prompt' => 'Show revenue'])->assertForbidden();

        $this->actingAs($agent)->get(route('legal.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('legal.tasks.index'))->assertForbidden();
        $this->actingAs($agent)->post(route('legal.tasks.store'), [
            'title' => 'Unauthorized Task',
            'deadline' => today()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($agent)->delete(route('legal.tasks.destroy', $legalTask))->assertForbidden();

        // Admin retains full access to Command Center & Legal
        $this->actingAs($this->superAdmin)->get(route('command-center.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('legal.tasks.index'))->assertOk();

        // 3. Non-admin cannot rename themselves (e.g. spoofing 'Arbab') via PUT /profile
        $this->actingAs($agent)
            ->put(route('profile.update'), [
                'name' => 'Arbab',
                'email' => $agent->email,
            ])
            ->assertForbidden();

        $this->assertSame('Regular Agent', $agent->fresh()->name);
        $this->assertFalse(OpsActionItem::isArbab($agent->fresh()));

        // Updating password/email while keeping name unchanged still works for non-admins
        $this->actingAs($agent)
            ->put(route('profile.update'), [
                'name' => 'Regular Agent',
                'email' => 'agent.updated@laleen.qa',
                'password' => 'UpdatedPass123!',
                'password_confirmation' => 'UpdatedPass123!',
            ])
            ->assertRedirect();

        $this->assertSame('Regular Agent', $agent->fresh()->name);
        $this->assertSame('agent.updated@laleen.qa', $agent->fresh()->email);

        // 4. View-only / unauthorized write endpoints reject with 403
        $opsItem = OpsActionItem::create([
            'title' => 'Weekly Branch Audit',
            'scope' => 'Old Airport',
            'pending_with_type' => 'staff',
            'pending_with_name' => 'Regular Agent',
            'sla_hours' => 48,
        ]);

        $this->actingAs($agent)->post(route('staff.ops-tracker.toggle', $opsItem))->assertForbidden();
        $this->actingAs($agent)->post(route('kpi.late-replies.update'), ['action' => 'set', 'count' => 0])->assertForbidden();
        $this->actingAs($agent)->post(route('customers.sync-retention-leads'))->assertForbidden();

        $staffManagerNoFinance = User::factory()->create([
            'name' => 'HR Manager No Finance',
            'email' => 'hr-nofin@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff_management' => 'edit', 'finance' => 'none'],
        ]);
        $this->actingAs($staffManagerNoFinance)->post(route('staff.payroll.post-to-finance'))->assertForbidden();

        $noLeadsOrKpisUser = User::factory()->create([
            'name' => 'Catalog Only User',
            'email' => 'catalog@laleen.qa',
            'role' => 'staff',
            'is_active' => true,
            'permissions' => ['services' => 'view'],
        ]);
        $lead = Lead::create([
            'phone' => '55123456',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
        ]);
        $this->actingAs($noLeadsOrKpisUser)->postJson(route('ai-script.generate', $lead->id))->assertForbidden();
    }

    public function test_deactivated_user_is_logged_out_on_next_request_and_blocked_from_password_reset_auto_login(): void
    {
        $agent = User::factory()->create([
            'name' => 'Arbab',
            'email' => 'arbab-deactivated@laleen.qa',
            'password' => Hash::make('OldPassword123!'),
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['leads' => 'edit', 'staff_management' => 'edit'],
        ]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->insert([
                'id' => 'sess_deactivated_test_1',
                'user_id' => $agent->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('test'),
                'last_activity' => time(),
            ]);
        }

        // Super-admin deactivates user -> purges sessions table row
        $this->actingAs($this->superAdmin)
            ->patch(route('users.toggle-active', $agent))
            ->assertRedirect(route('users.index'));

        $agent->refresh();
        $this->assertFalse($agent->is_active);
        $this->assertFalse(OpsActionItem::isArbab($agent));

        if (Schema::hasTable('sessions')) {
            $this->assertDatabaseMissing('sessions', ['user_id' => $agent->id]);
        }

        // Deactivated user with an active session hits any authenticated route -> logged out & redirected to /login
        $response = $this->actingAs($agent)->get(route('dashboard'));
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Your account has been deactivated.');
        $this->assertGuest();

        // Also test hitting a module-protected route while deactivated
        $moduleResponse = $this->actingAs($agent)->get(route('leads.index'));
        $moduleResponse->assertRedirect('/login');
        $this->assertGuest();

        // Deactivated user attempting Fortify password reset is blocked with ValidationException and NOT auto-logged in
        $token = Password::broker()->createToken($agent);

        $resetResponse = $this->from('/reset-password/' . $token)->post('/reset-password', [
            'token' => $token,
            'email' => $agent->email,
            'password' => 'BrandNewPass123!',
            'password_confirmation' => 'BrandNewPass123!',
        ]);

        $resetResponse->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(Hash::check('OldPassword123!', $agent->fresh()->password));
    }

    public function test_command_center_build_live_telemetry_snapshot_executes_without_sql_errors_and_returns_real_counts(): void
    {
        $category = ServiceCategory::create(['name' => 'Hair Treatments']);
        $service = Service::create([
            'name' => 'Royal Caviar Keratin',
            'category_id' => $category->id,
            'price' => 600,
            'duration' => 90,
        ]);

        $combo = Combo::create([
            'name' => '99 QAR Glow Trio',
            'price' => 99,
            'quantity_included' => 3,
            'validity_days' => 30,
            'status' => 'active',
        ]);
        $combo->services()->attach($service->id);

        $customer = Customer::create([
            'name' => 'Hessa Al-Marri',
            'phone' => '55990011',
            'loyalty_points' => 50,
        ]);

        // Unresolved complaint (resolved_at = null, is_staff_mistake = null)
        StaffComplaint::create([
            'complaint_date' => today()->toDateString(),
            'branch' => 'old_airport',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'category' => 'Wait Time',
            'description' => 'Waited 15 minutes past appointment slot',
            'is_staff_mistake' => null,
            'resolved_at' => null,
            'created_by' => $this->superAdmin->id,
        ]);

        // Resolved complaint (should NOT count as open)
        StaffComplaint::create([
            'complaint_date' => today()->toDateString(),
            'branch' => 'wakrah',
            'customer_name' => 'Resolved Client',
            'category' => 'Other',
            'description' => 'Resolved already',
            'is_staff_mistake' => false,
            'resolved_at' => now(),
            'created_by' => $this->superAdmin->id,
        ]);

        Sale::create([
            'customer_id' => $customer->id,
            'branch' => 'old_airport',
            'services_total' => 600,
            'products_total' => 50,
            'packages_total' => 99,
            'discount_amount' => 49,
            'tip_amount' => 25,
            'total_amount' => 725,
        ]);

        /** @var LaleenCommandCenterService $serviceInstance */
        $serviceInstance = app(LaleenCommandCenterService::class);

        $snapshot = $serviceInstance->buildLiveTelemetrySnapshot();

        $this->assertArrayNotHasKey('_is_fallback', $snapshot);
        $this->assertSame(700.0, (float) $snapshot['revenue']['today_total_net_qar']);
        $this->assertSame(1, $snapshot['staff']['open_complaints_count']);
        $this->assertSame(1, $snapshot['crm_operations']['services']['combos_count']);
        $this->assertSame('99 QAR Glow Trio', $snapshot['crm_operations']['services']['active_combos'][0]['name']);
        $this->assertSame(3, $snapshot['crm_operations']['services']['active_combos'][0]['sessions']);

        // Verify client lookup computes real LTV from Sale records (700.0 QAR excluding 25 QAR tip)
        $lookup = $serviceInstance->lookup_client_or_staff('Hessa');
        $this->assertCount(1, $lookup['clients']);
        $this->assertSame(700.0, (float) $lookup['clients'][0]['ltv_qar']);
    }

    public function test_dashboard_renders_200_ok_with_portable_sql_across_branches(): void
    {
        Sale::create([
            'branch' => 'old_airport',
            'services_total' => 300,
            'products_total' => 50,
            'packages_total' => 0,
            'discount_amount' => 20,
            'tip_amount' => 10,
            'total_amount' => 340,
        ]);

        Sale::create([
            'branch' => 'wakrah',
            'services_total' => 200,
            'products_total' => 0,
            'packages_total' => 99,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 299,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));
        $response->assertOk();

        $branchPnl = collect($response->viewData('branchPnl'))->keyBy('key');
        $this->assertSame(330.0, (float) $branchPnl['old_airport']['sales']);
        $this->assertSame(299.0, (float) $branchPnl['wakrah']['sales']);
    }

    public function test_store_payment_blocks_duplicate_sale_creation_on_double_submit_and_enforces_unique_appointment_index(): void
    {
        $stylist = Staff::create([
            'name' => 'Layla Stylist',
            'role' => 'Stylist',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);

        $service = Service::create([
            'name' => 'Blowdry & Style',
            'price' => 250,
            'duration' => 45,
        ]);

        $product = Product::create([
            'name' => 'Silk Hair Mask',
            'sku' => 'SHM-01',
            'price' => 100,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
        ]);

        $customer = Customer::create([
            'name' => 'Mona Al-Sulaiti',
            'phone' => '55334455',
            'loyalty_points' => 0,
        ]);

        AdLeadEntry::create([
            'date' => today()->toDateString(),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'category' => 'Hair',
        ]);

        $appt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => $service->name,
            'appointment_datetime' => now()->setTime(12, 0),
            'booking_date' => today()->toDateString(),
            'start_time' => '12:00:00',
            'staff_id' => $stylist->id,
            'branch' => 'old_airport',
            'status' => 'confirmed',
            'price' => 250,
        ]);

        AppointmentService::create([
            'appointment_id' => $appt->id,
            'service_id' => $service->id,
            'staff_id' => $stylist->id,
            'name' => $service->name,
            'price' => 250,
            'original_price' => 250,
            'duration' => 45,
            'start_time' => $appt->appointment_datetime,
        ]);

        $checkoutPayload = [
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'staff_id' => $stylist->id],
            ],
            'payments' => [
                'card' => 350,
            ],
        ];

        // First checkout succeeds
        $firstResponse = $this->actingAs($this->superAdmin)
            ->post(route('appointments.revenue.storePayment', $appt), $checkoutPayload);
        $firstResponse->assertRedirect(route('appointments.revenue.index'));
        $firstResponse->assertSessionHas('success');

        // Second (double-click) checkout is rejected cleanly without creating a second Sale or deducting stock twice
        $secondResponse = $this->actingAs($this->superAdmin)
            ->post(route('appointments.revenue.storePayment', $appt), $checkoutPayload);
        $secondResponse->assertRedirect(route('appointments.revenue.index'));
        $secondResponse->assertSessionHas('error', 'This appointment has already been checked out.');

        $this->assertSame(1, Sale::where('appointment_id', $appt->id)->count());
        $this->assertSame(9, (int) $product->fresh()->stock_quantity);
        $this->assertSame(350.0, (float) AdLeadEntry::where('customer_id', $customer->id)->value('ticket_amount'));

        // Database-level UNIQUE index also blocks raw duplicate appointment_id insert while allowing multiple NULL walk-in sales
        Sale::create(['appointment_id' => null, 'branch' => 'old_airport', 'services_total' => 50, 'total_amount' => 50]);
        Sale::create(['appointment_id' => null, 'branch' => 'wakrah', 'services_total' => 75, 'total_amount' => 75]);
        $this->assertSame(2, Sale::whereNull('appointment_id')->count());

        $this->expectException(UniqueConstraintViolationException::class);
        Sale::create([
            'appointment_id' => $appt->id,
            'branch' => 'old_airport',
            'services_total' => 350,
            'total_amount' => 350,
        ]);
    }

    public function test_morning_bookings_at_0930_asia_qatar_are_credited_to_0900_1700_shift_in_kpi_agent_target_report(): void
    {
        $this->assertSame('Asia/Qatar', config('app.timezone'));

        $agent = User::factory()->create([
            'name' => 'Areeba Morning Agent',
            'email' => 'areeba-shift@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $shiftDate = '2026-11-11';

        // Agent works both a morning shift (09:00–17:00 Doha) and an evening overtime shift (18:00–21:00 Doha)
        $morningLog = AgentShiftLog::create([
            'user_id' => $agent->id,
            'date' => $shiftDate,
            'shift' => 'morning',
            'check_in_time' => '09:00:00',
            'check_out_time' => '17:00:00',
            'created_by' => $this->superAdmin->id,
        ]);

        AgentShiftLog::create([
            'user_id' => $agent->id,
            'date' => $shiftDate,
            'shift' => 'evening',
            'check_in_time' => '18:00:00',
            'check_out_time' => '21:00:00',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertSame('Asia/Qatar', $morningLog->windowStart()->timezoneName);
        $this->assertSame('2026-11-11 09:00:00', $morningLog->windowStart()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-11 17:00:00', $morningLog->windowEnd()->format('Y-m-d H:i:s'));

        $stylist = Staff::create(['name' => 'Doha Stylist', 'branch' => 'old_airport', 'bookable' => true]);

        // Booking 1: Created at 09:30 Asia/Qatar (previously 06:30 UTC which failed < 09:00 UTC!)
        $morningAppt = Appointment::create([
            'customer_name' => 'Doha Morning Client',
            'phone' => '+97455100930',
            'service_name' => 'Manicure',
            'appointment_datetime' => Carbon::parse('2026-11-12 11:00:00', 'Asia/Qatar'),
            'booking_date' => '2026-11-12',
            'start_time' => '11:00:00',
            'staff_id' => $stylist->id,
            'branch' => 'old_airport',
            'price' => 120,
            'status' => 'confirmed',
            'booking_agent_id' => $agent->id,
            'created_by' => $agent->id,
        ]);
        $morningAppt->forceFill([
            'created_at' => Carbon::parse('2026-11-11 09:30:00', 'Asia/Qatar'),
        ])->saveQuietly();

        // Booking 2: Created at 11:45 Asia/Qatar passed as a UTC Carbon (08:45 UTC == 11:45 Asia/Qatar)
        $lateMorningAppt = Appointment::create([
            'customer_name' => 'Doha Late Morning Client',
            'phone' => '+97455101145',
            'service_name' => 'Pedicure',
            'appointment_datetime' => Carbon::parse('2026-11-12 13:00:00', 'Asia/Qatar'),
            'booking_date' => '2026-11-12',
            'start_time' => '13:00:00',
            'staff_id' => $stylist->id,
            'branch' => 'wakrah',
            'price' => 140,
            'status' => 'confirmed',
            'booking_agent_id' => $agent->id,
            'created_by' => $agent->id,
        ]);
        $lateMorningAppt->forceFill([
            'created_at' => Carbon::parse('2026-11-11 11:45:00', 'Asia/Qatar')->utc(),
        ])->saveQuietly();

        // Booking 3: Created at 19:15 Asia/Qatar -> credited to evening shift
        $eveningAppt = Appointment::create([
            'customer_name' => 'Doha Evening Client',
            'phone' => '+97455101915',
            'service_name' => 'HydraFacial',
            'appointment_datetime' => Carbon::parse('2026-11-12 19:00:00', 'Asia/Qatar'),
            'booking_date' => '2026-11-12',
            'start_time' => '19:00:00',
            'staff_id' => $stylist->id,
            'branch' => 'old_airport',
            'price' => 350,
            'status' => 'confirmed',
            'booking_agent_id' => $agent->id,
            'created_by' => $agent->id,
        ]);
        $eveningAppt->forceFill([
            'created_at' => Carbon::parse('2026-11-11 19:15:00', 'Asia/Qatar'),
        ])->saveQuietly();

        $report = new KpiAgentTargetReport([
            'date_from' => Carbon::parse($shiftDate, 'Asia/Qatar'),
            'date_to' => Carbon::parse($shiftDate, 'Asia/Qatar'),
        ]);

        $stats = $report->shiftStats();
        $this->assertSame(2, $stats['morning']['bookings']);
        $this->assertSame(1, $stats['evening']['bookings']);
    }
}
