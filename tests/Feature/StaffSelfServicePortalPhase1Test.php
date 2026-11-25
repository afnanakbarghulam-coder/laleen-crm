<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffSelfServicePortalPhase1Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $specialistStaff;
    private Staff $agentStaff;
    private Staff $otherStaff;
    private Service $haircut;
    private Service $keratin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'Asia/Qatar'));

        $this->admin = User::factory()->create([
            'name' => 'Salon Admin',
            'email' => 'admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->haircut = Service::create(['name' => 'Haircut', 'price' => 60, 'duration' => 30]);
        $this->keratin = Service::create(['name' => 'Keratin Treatment', 'price' => 400, 'duration' => 240]);

        $this->specialistStaff = Staff::create([
            'name' => 'Sara Specialist',
            'first_name' => 'Sara',
            'last_name' => 'Specialist',
            'phone' => '+97455123456',
            'branch' => 'old_airport',
            'bookable' => true,
            'availability_status' => 'present',
            'employment_type' => 'full_time',
            'start_date' => '2025-05-01',
        ]);
        $this->specialistStaff->services()->sync([$this->haircut->id, $this->keratin->id]);

        // Working Sun(0), Mon(1), Tue(2), Thu(4), Fri(5), Sat(6) — Fixed Weekly Off = Wednesday(3)
        $pattern = ShiftPattern::create([
            'staff_id' => $this->specialistStaff->id,
            'repeat_frequency' => 'weekly',
            'start_date' => '2026-11-01',
            'end_date' => null,
        ]);
        foreach ([0, 1, 2, 4, 5, 6] as $dow) {
            ShiftBlock::create([
                'shift_pattern_id' => $pattern->id,
                'day_of_week' => $dow,
                'start_time' => '10:00:00',
                'end_time' => '21:00:00',
            ]);
        }

        StaffTimeOff::create([
            'staff_id' => $this->specialistStaff->id,
            'start_date' => '2026-12-02',
            'end_date' => '2026-12-03',
            'reason' => 'annual',
            'notes' => 'Family trip',
        ]);

        $this->agentStaff = Staff::create([
            'name' => 'Zoya Agent',
            'first_name' => 'Zoya',
            'last_name' => 'Agent',
            'phone' => '+97455987654',
            'branch' => 'old_airport',
            'bookable' => false,
            'availability_status' => 'present',
            'employment_type' => 'full_time',
        ]);

        $this->otherStaff = Staff::create([
            'name' => 'Fatima Secret',
            'first_name' => 'Fatima',
            'last_name' => 'Secret',
            'branch' => 'wakrah',
            'bookable' => true,
            'availability_status' => 'present',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_login_page_shows_unified_portal_subtitle(): void
    {
        $res = $this->get('/login');
        $res->assertOk();
        $res->assertSee('Sign in to Laleen Ops &amp; Staff Portal', false);
        $res->assertDontSee('Sign in to manage your salon');
    }

    public function test_admin_can_provision_salon_staff_portal_login_and_strict_isolation_is_enforced(): void
    {
        // Admin creates Salon Staff portal login from Directory & Profiles
        $createRes = $this->actingAs($this->admin)->postJson(
            route('staffs.portal-access.store', $this->specialistStaff),
            [
                'email' => 'sara.portal@laleen.qa',
                'password' => 'TempPass123!',
                'role' => 'salon_staff',
            ]
        );

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'salon_staff')
            ->assertJsonPath('user.email', 'sara.portal@laleen.qa')
            ->assertJsonPath('user.is_active', true);

        $this->specialistStaff->refresh();
        $salonUser = User::findOrFail($this->specialistStaff->user_id);
        $this->assertTrue($salonUser->isSalonStaff());
        $this->assertSame('none', $salonUser->permissionLevel('dashboard'));
        $this->assertSame('none', $salonUser->permissionLevel('guide'));
        $this->assertSame('none', $salonUser->permissionLevel('leads'));

        // Log out admin, then log in via unified /login as Salon Staff -> redirects directly to /my-profile
        $this->post('/logout');

        $loginRes = $this->post('/login', [
            'email' => 'sara.portal@laleen.qa',
            'password' => 'TempPass123!',
        ]);
        $loginRes->assertRedirect(route('portal.profile'));
        $this->assertAuthenticatedAs($salonUser);

        // /my-profile renders distraction-free portal layout + accurate Branch, Skills, Off Days (ignoring forged staff_id!)
        $profileRes = $this->actingAs($salonUser)->get('/my-profile?staff_id=' . $this->otherStaff->id);
        $profileRes->assertOk();
        $profileRes->assertSee('Sara Specialist');
        $profileRes->assertSee('Salon Staff');
        $profileRes->assertSee('Old Airport');
        $profileRes->assertSee('10:00 AM – 9:00 PM');
        $profileRes->assertSee('Haircut');
        $profileRes->assertSee('Keratin Treatment');
        $profileRes->assertSee('Wednesday');
        $profileRes->assertSee('Family trip');
        $profileRes->assertSee('Laleen');
        $profileRes->assertSee('Staff Portal');
        $profileRes->assertDontSee('Fatima Secret');
        $profileRes->assertDontSee('id="layout-menu"', false);

        // Portal tabs work cleanly
        $this->actingAs($salonUser)->get('/my-profile?tab=contracts')->assertOk()->assertSee('Active Employment Contract');
        $this->actingAs($salonUser)->get('/my-profile?tab=documents')->assertOk()->assertSee('Personal Documents');
        $this->actingAs($salonUser)->get('/my-profile?tab=leaves')->assertOk()->assertSee('Annual Leave');

        // Strict server-side isolation: ALL operational CRM routes & JSON endpoints return 403
        $blockedHtmlUrls = [
            '/dashboard',
            '/leads',
            '/appointments',
            '/bookings',
            '/appointments-calendar',
            '/enhanced-calendar',
            '/revenue',
            '/finance',
            '/clients',
            '/customers',
            '/staffs',
            '/staff-management',
            '/services',
            '/kpis',
            '/guide',
            '/profile',
        ];
        foreach ($blockedHtmlUrls as $url) {
            $this->actingAs($salonUser)
                ->get($url)
                ->assertStatus(403);
        }

        // Blocked JSON/API endpoints also return 403 JSON
        $this->actingAs($salonUser)
            ->getJson('/appointments-calendar-data')
            ->assertStatus(403);
        $this->actingAs($salonUser)
            ->getJson('/customers-search?q=a')
            ->assertStatus(403);

        // Root redirects cleanly to /my-profile
        $this->actingAs($salonUser)
            ->get('/')
            ->assertRedirect(route('portal.profile'));
    }

    public function test_disabling_portal_user_immediately_invalidates_session_and_blocks_login_with_clear_message(): void
    {
        $salonUser = User::factory()->create([
            'name' => $this->specialistStaff->name,
            'email' => 'sara.lock@laleen.qa',
            'password' => Hash::make('ActivePass123!'),
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $this->specialistStaff->update(['user_id' => $salonUser->id]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->insert([
                'id' => 'portal_sess_1',
                'user_id' => $salonUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('portal'),
                'last_activity' => time(),
            ]);
        }

        // Admin disables portal access via Directory toggle
        $toggleRes = $this->actingAs($this->admin)->patchJson(
            route('staffs.portal-access.toggle', $this->specialistStaff)
        );
        $toggleRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_active', false);

        $this->assertFalse($salonUser->fresh()->is_active);
        if (Schema::hasTable('sessions')) {
            $this->assertDatabaseMissing('sessions', ['user_id' => $salonUser->id]);
        }

        // Active session hitting /my-profile is immediately logged out & redirected to /login
        $kickRes = $this->actingAs($salonUser->fresh())->get('/my-profile');
        $kickRes->assertRedirect('/login')
            ->assertSessionHas('error', 'Your account has been deactivated. Please contact management.');
        $this->assertGuest();

        // Attempting fresh login while disabled fails with exact deactivated message
        $blockedLogin = $this->from('/login')->post('/login', [
            'email' => 'sara.lock@laleen.qa',
            'password' => 'ActivePass123!',
        ]);
        $blockedLogin->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Your account has been deactivated. Please contact management.',
            ]);
        $this->assertGuest();
    }

    public function test_chat_agent_keeps_crm_access_and_views_my_profile_inside_crm_layout(): void
    {
        $createRes = $this->actingAs($this->admin)->postJson(
            route('staffs.portal-access.store', $this->agentStaff),
            [
                'email' => 'zoya.portal@laleen.qa',
                'password' => 'AgentPass123!',
                'role' => 'agent',
            ]
        );
        $createRes->assertCreated()->assertJsonPath('user.role', 'agent');

        $agentUser = User::where('email', 'zoya.portal@laleen.qa')->firstOrFail();
        $this->post('/logout');

        // Login as Chat Agent -> redirects to CRM dashboard (not locked to /my-profile)
        $loginRes = $this->post('/login', [
            'email' => 'zoya.portal@laleen.qa',
            'password' => 'AgentPass123!',
        ]);
        $loginRes->assertRedirect(route('dashboard'));

        // Normal CRM modules remain accessible
        $this->actingAs($agentUser)->get(route('leads.index'))->assertOk();
        $this->actingAs($agentUser)->get(route('appointments.calendar'))->assertOk();

        // /my-profile Overview works inside standard CRM layout (sidebar present) with Remote & Monthly Salary
        $profileRes = $this->actingAs($agentUser)->get('/my-profile');
        $profileRes->assertOk();
        $profileRes->assertSee('Zoya Agent');
        $profileRes->assertSee('Chat Agent');
        $profileRes->assertSee('Remote');
        $profileRes->assertDontSee('Old Airport');
        $profileRes->assertSee('Monthly Salary');
        $profileRes->assertSee('Performance');
        $profileRes->assertSee('id="layout-menu"', false);
        $profileRes->assertSee('My Profile');

        // /my-profile?tab=performance displays Chat Agent Booking Targets in KPIs-module scorecard style
        $perfRes = $this->actingAs($agentUser)->get('/my-profile?tab=performance');
        $perfRes->assertOk();
        $perfRes->assertSee("Today's Daily Booking Target", false);
        $perfRes->assertSee('MTD Booking Quota');

        // Non-admin Chat Agent CANNOT access Admin Portal Access endpoints
        $this->actingAs($agentUser)
            ->postJson(route('staffs.portal-access.store', $this->specialistStaff), [
                'email' => 'hijack@laleen.qa',
                'password' => 'Secret123!',
                'role' => 'salon_staff',
            ])
            ->assertStatus(403);
    }

    public function test_admin_password_reset_and_staff_self_service_password_change_work(): void
    {
        $salonUser = User::factory()->create([
            'name' => $this->specialistStaff->name,
            'email' => 'sara.pw@laleen.qa',
            'password' => Hash::make('InitialPass1!'),
            'role' => 'salon_staff',
            'is_active' => true,
        ]);
        $this->specialistStaff->update(['user_id' => $salonUser->id]);

        // 1. Admin resets password from Directory & Profiles
        $this->actingAs($this->admin)->patchJson(
            route('staffs.portal-access.reset-password', $this->specialistStaff),
            ['password' => 'AdminReset99!']
        )->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('AdminReset99!', $salonUser->fresh()->password));

        // 2. Staff tries self-service password change with WRONG current password -> validation error
        $wrongRes = $this->actingAs($salonUser->fresh())
            ->from('/my-profile')
            ->put(route('portal.password.update'), [
                'current_password' => 'WrongCurrent1!',
                'password' => 'MyNewSecurePass1!',
                'password_confirmation' => 'MyNewSecurePass1!',
            ]);
        $wrongRes->assertRedirect('/my-profile')
            ->assertSessionHasErrors('current_password', null, 'portalPassword');

        // 3. Staff updates password with CORRECT current password -> succeeds
        $okRes = $this->actingAs($salonUser->fresh())
            ->from('/my-profile')
            ->put(route('portal.password.update'), [
                'current_password' => 'AdminReset99!',
                'password' => 'MyNewSecurePass1!',
                'password_confirmation' => 'MyNewSecurePass1!',
            ]);
        $okRes->assertRedirect(route('portal.profile', ['tab' => 'overview']))
            ->assertSessionHas('success', 'Your password has been updated.');

        $this->assertTrue(Hash::check('MyNewSecurePass1!', $salonUser->fresh()->password));
    }
}
