<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnifiedRolesAndAccessHubTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agentUser;
    private User $salonStaffUser;
    private Staff $unlinkedSpecialist;
    private Staff $linkedSpecialist;
    private Service $blowdry;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-25 11:00:00', 'Asia/Qatar'));

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'admin.unified@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
            'profile_photo' => 'uploads/profiles/non_existent_broken_avatar.jpg',
        ]);

        // Existing unlinked Chat Agent (e.g., Zoya / Areeba before HR profile linking)
        $this->agentUser = User::factory()->create([
            'name' => 'Zoya Ali',
            'email' => 'zoya.unlinked@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);

        $this->blowdry = Service::create(['name' => 'Luxury Blowdry', 'price' => 80, 'duration' => 45]);

        $this->unlinkedSpecialist = Staff::create([
            'name' => 'Aasma Stylist',
            'first_name' => 'Aasma',
            'last_name' => 'Stylist',
            'branch' => 'old_airport',
            'bookable' => true,
            'availability_status' => 'present',
            'profile_picture' => 'staff/profile-picture/missing_photo.png',
        ]);
        $this->unlinkedSpecialist->services()->sync([$this->blowdry->id]);

        $this->salonStaffUser = User::factory()->create([
            'name' => 'Sara Beautician',
            'email' => 'sara.linked@laleen.qa',
            'role' => 'salon_staff',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);

        $this->linkedSpecialist = Staff::create([
            'name' => 'Sara Beautician',
            'first_name' => 'Sara',
            'last_name' => 'Beautician',
            'email' => 'sara.linked@laleen.qa',
            'branch' => 'wakrah',
            'bookable' => true,
            'availability_status' => 'present',
            'user_id' => $this->salonStaffUser->id,
        ]);
        $this->linkedSpecialist->services()->sync([$this->blowdry->id]);

        $pattern = ShiftPattern::create([
            'staff_id' => $this->linkedSpecialist->id,
            'repeat_frequency' => 'weekly',
            'start_date' => '2026-11-01',
            'end_date' => null,
        ]);
        foreach ([0, 1, 2, 4, 5, 6] as $dow) {
            ShiftBlock::create([
                'shift_pattern_id' => $pattern->id,
                'day_of_week' => $dow,
                'start_time' => '10:00:00',
                'end_time' => '20:00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unified_five_tab_staff_management_navigation_renders_across_all_staff_pages_without_legacy_bar(): void
    {
        $pages = [
            route('staffs.index'),
            route('shifts.index'),
            route('staff.ops-tracker.index'),
            route('users.index'),
        ];

        foreach ($pages as $url) {
            $resp = $this->actingAs($this->admin)->get($url)->assertOk();
            $resp->assertSee('laleen-staff-pill-bar', false);
            $resp->assertSeeInOrder([
                'Directory &amp; Profiles',
                'Contracts &amp; Payroll',
                'Shifts &amp; Ops Actions',
                'HR &amp; Disciplinary',
                'Roles &amp; Access',
            ], false);
            // Retired legacy 3-tab bar must not appear
            $resp->assertDontSee('>Team Members</a>', false);
            // Broken avatar file must not emit an <img> referencing the missing file
            $resp->assertDontSee('non_existent_broken_avatar.jpg', false);
            $resp->assertSee('laleen-avatar-wrap', false);
        }

        // Non-admin manager with staff_management access cannot access /users (403) and does not see Roles & Access tab
        $manager = User::factory()->create([
            'name' => 'Branch Mgr',
            'email' => 'mgr@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('manager'),
        ]);
        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
        $mgrStaffResp = $this->actingAs($manager)->get(route('staffs.index'))->assertOk();
        $mgrStaffResp->assertDontSee('Roles &amp; Access', false);
    }

    public function test_roles_and_access_hub_views_grant_login_linking_and_create_staff_profile_work_end_to_end(): void
    {
        // 1. All Accounts view shows Unlinked badge on Zoya, Linked HR profile on Sara, and counts
        $allResp = $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
        $allResp->assertSee('Zoya Ali');
        $allResp->assertSee('Unlinked');
        $allResp->assertSee('Link Profile');
        $allResp->assertSee('Sara Beautician');
        $allResp->assertSee('Portal Only (/my-profile)');
        $allResp->assertSee('Preview Portal Profile');

        // 2. Staff Without Login view lists Aasma Stylist with + Grant Access
        $unlinkedResp = $this->actingAs($this->admin)->get(route('users.index', ['view' => 'unlinked_staff']))->assertOk();
        $unlinkedResp->assertSee('Aasma Stylist');
        $unlinkedResp->assertSee('Grant Access');
        $unlinkedResp->assertDontSee('missing_photo.png', false);

        // 3. Grant Login to Existing Staff (Aasma Stylist) as Salon Staff via unified POST /users
        $grantRes = $this->actingAs($this->admin)->postJson(route('users.store'), [
            'existing_staff_id' => $this->unlinkedSpecialist->id,
            'email' => 'aasma.portal@laleen.qa',
            'password' => 'AasmaPass123!',
            'role' => 'salon_staff',
            // Even if tampered permissions are passed, salon_staff is hard-locked to 'none'
            'permissions' => ['leads' => 'edit', 'finance' => 'edit'],
        ]);
        $grantRes->assertCreated()->assertJsonPath('success', true);

        $this->unlinkedSpecialist->refresh();
        $aasmaUser = User::findOrFail($this->unlinkedSpecialist->user_id);
        $this->assertTrue($aasmaUser->isSalonStaff());
        $this->assertSame('none', $aasmaUser->permissionLevel('leads'));
        $this->assertSame('none', $aasmaUser->permissionLevel('finance'));

        // 4. Attempting to link Zoya to Aasma's already-linked staff profile fails 422 (strict 1-to-1 uniqueness)
        $this->actingAs($this->admin)->putJson(route('users.update', $this->agentUser), [
            'name' => $this->agentUser->name,
            'email' => $this->agentUser->email,
            'role' => 'agent',
            'staff_id' => $this->unlinkedSpecialist->id,
        ])->assertStatus(422);

        // 5. 1-click Create Staff Profile for unlinked Zoya Ali -> links 1-to-1 immediately
        $createProfileRes = $this->actingAs($this->admin)
            ->postJson(route('users.create-staff-profile', $this->agentUser), ['branch' => 'remote']);
        $createProfileRes->assertCreated()->assertJsonPath('success', true);

        $zoyaStaff = Staff::where('user_id', $this->agentUser->id)->firstOrFail();
        $this->assertSame('Zoya Ali', $zoyaStaff->name);
        $this->assertFalse((bool) $zoyaStaff->bookable);

        // 6. Create brand-new account with "Also create linked Staff HR Profile" checked
        $newAccountRes = $this->actingAs($this->admin)->postJson(route('users.store'), [
            'name' => 'Areeba New',
            'email' => 'areeba.new@laleen.qa',
            'password' => 'AreebaPass123!',
            'role' => 'agent',
            'create_staff_profile' => 1,
            'staff_branch' => 'remote',
        ]);
        $newAccountRes->assertCreated()->assertJsonPath('success', true);
        $areebaUser = User::where('email', 'areeba.new@laleen.qa')->firstOrFail();
        $this->assertTrue(Staff::where('user_id', $areebaUser->id)->exists());

        // 7. Quick password reset + Disable via unified PUT /users/{user} purges DB sessions & shows in Disabled view
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->insert([
                'id' => 'aasma_sess_99',
                'user_id' => $aasmaUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('x'),
                'last_activity' => time(),
            ]);
        }

        $this->actingAs($this->admin)->patchJson(route('users.reset-password', $aasmaUser), [
            'password' => 'FreshReset99!',
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('FreshReset99!', $aasmaUser->fresh()->password));

        $this->actingAs($this->admin)->putJson(route('users.update', $aasmaUser), [
            'name' => $aasmaUser->name,
            'email' => $aasmaUser->email,
            'role' => 'salon_staff',
            'is_active' => 0,
            'staff_id' => $this->unlinkedSpecialist->id,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertFalse($aasmaUser->fresh()->is_active);
        if (Schema::hasTable('sessions')) {
            $this->assertDatabaseMissing('sessions', ['user_id' => $aasmaUser->id]);
        }

        $disabledResp = $this->actingAs($this->admin)->get(route('users.index', ['view' => 'disabled']))->assertOk();
        $disabledHtml = $disabledResp->getContent();
        preg_match('/<table class="table ra-table[^"]*">.*?<\/table>/s', $disabledHtml, $tableMatch);
        $this->assertNotEmpty($tableMatch[0] ?? '', 'Expected Roles & Access table on Disabled Accounts view.');
        $this->assertStringContainsString('Aasma Stylist', $tableMatch[0]);
        $this->assertStringNotContainsString('Zoya Ali', $tableMatch[0]);
    }

    public function test_admin_preview_portal_profile_is_strictly_admin_only_and_renders_read_only_banner(): void
    {
        // Admin can preview Sara Beautician's portal profile with banner & Back to Roles & Access link
        $preview = $this->actingAs($this->admin)
            ->get(route('portal.admin-preview', $this->linkedSpecialist))
            ->assertOk();

        $preview->assertSee("Admin Preview", false);
        $preview->assertSee("Viewing Sara Beautician's Portal Profile", false);
        $preview->assertSee('Back to Roles &amp; Access', false);
        $preview->assertSee('Al Wakrah');
        $preview->assertSee('Luxury Blowdry');
        $preview->assertSee('Wednesday');
        $preview->assertDontSee('Change Password');

        // Sub-tab inside Admin Preview stays inside admin-preview route cleanly
        $this->actingAs($this->admin)
            ->get(route('portal.admin-preview', ['staff' => $this->linkedSpecialist->id, 'tab' => 'leaves']))
            ->assertOk()
            ->assertSee('Coming soon');

        // Non-admins (Chat Agent, Manager, Salon Staff) ALL receive strict HTTP 403
        $this->actingAs($this->agentUser)
            ->get(route('portal.admin-preview', $this->linkedSpecialist))
            ->assertForbidden();

        $this->actingAs($this->salonStaffUser)
            ->get(route('portal.admin-preview', $this->linkedSpecialist))
            ->assertForbidden();

        // /my-profile itself STILL ignores any staff_id query parameter
        $ownProfile = $this->actingAs($this->salonStaffUser)
            ->get('/my-profile?staff_id=' . $this->unlinkedSpecialist->id)
            ->assertOk();
        $ownProfile->assertSee('Sara Beautician');
        $ownProfile->assertDontSee('Aasma Stylist');
    }
}
