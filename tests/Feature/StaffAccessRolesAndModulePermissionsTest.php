<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Lead;
use App\Models\LegalTask;
use App\Models\SocialContentPlan;
use App\Models\StaffContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffAccessRolesAndModulePermissionsTest extends TestCase
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

    public function test_all_fourteen_modules_render_in_sidebar_order_and_save_no_access_view_only_and_full_edit_accurately(): void
    {
        $expectedModules = [
            'dashboard' => 'Dashboard',
            'command_center' => 'Command Center',
            'leads' => 'Leads',
            'bookings' => 'Bookings',
            'calendar' => 'Enhanced Calendar',
            'finance' => 'Finance',
            'clients' => 'Clients',
            'staff' => 'Staff Management',
            'services' => 'Services',
            'products' => 'Products',
            'kpis' => 'KPIs',
            'ecommerce' => 'Ecommerce',
            'legal_tasks' => 'Legal Tasks',
            'guide' => 'CRM Guide & SOPs',
        ];

        $this->assertSame($expectedModules, User::MODULES);
        $this->assertSame($expectedModules, config('modules'));

        $indexResp = $this->actingAs($this->superAdmin)
            ->get(route('users.index'))
            ->assertOk();

        $indexResp->assertSeeInOrder(array_values($expectedModules));
        $indexResp->assertSee('Marketing Specialist');
        $indexResp->assertSee('Social Media Editor');
        $indexResp->assertSee("window.Alpine.data('staffAccessUserForm'", false);
        $indexResp->assertDontSee('x-data="{', false);

        // Save all 14 modules using no_access / view_only / full_edit tokens
        $submittedPermissions = [
            'dashboard' => 'view_only',
            'command_center' => 'no_access',
            'leads' => 'full_edit',
            'bookings' => 'view_only',
            'calendar' => 'full_edit',
            'finance' => 'no_access',
            'clients' => 'view_only',
            'staff' => 'view_only',
            'services' => 'full_edit',
            'products' => 'view_only',
            'kpis' => 'full_edit',
            'ecommerce' => 'no_access',
            'legal_tasks' => 'view_only',
            'guide' => 'view_only',
        ];

        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Custom Matrix Coordinator',
            'email' => 'matrix@laleen.qa',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'role' => 'manager',
            'permissions' => $submittedPermissions,
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'matrix@laleen.qa')->firstOrFail();
        $this->assertCount(14, $created->permissions);
        $this->assertSame('view', $created->permissionLevel('dashboard'));
        $this->assertSame('none', $created->permissionLevel('command_center'));
        $this->assertSame('edit', $created->permissionLevel('leads'));
        $this->assertSame('view', $created->permissionLevel('bookings'));
        $this->assertSame('edit', $created->permissionLevel('calendar'));
        $this->assertSame('none', $created->permissionLevel('finance'));
        $this->assertSame('view', $created->permissionLevel('clients'));
        $this->assertSame('view', $created->permissionLevel('staff'));
        $this->assertSame('view', $created->permissionLevel('staff_management'));
        $this->assertSame('edit', $created->permissionLevel('services'));
        $this->assertSame('view', $created->permissionLevel('products'));
        $this->assertSame('edit', $created->permissionLevel('kpis'));
        $this->assertSame('none', $created->permissionLevel('ecommerce'));
        $this->assertSame('view', $created->permissionLevel('legal_tasks'));
        $this->assertSame('view', $created->permissionLevel('guide'));

        // Verify legal_tasks View Only allows GET /legal/tasks and blocks POST /legal/tasks
        LegalTask::create([
            'title' => 'Municipality Signage Permit',
            'deadline' => today()->addDays(14)->toDateString(),
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($created)->get(route('legal.tasks.index'))->assertOk()->assertSee('Municipality Signage Permit');
        $this->actingAs($created)->post(route('legal.tasks.store'), [
            'title' => 'Should Fail View Only',
            'deadline' => today()->addDays(5)->toDateString(),
        ])->assertForbidden();
    }

    public function test_marketing_specialist_role_end_to_end_smart_presets_sidebar_visibility_and_route_gates(): void
    {
        $preset = User::defaultPermissionsForRole('marketing_specialist');

        $this->assertSame('edit', $preset['kpis']);
        $this->assertSame('edit', $preset['ecommerce']);
        $this->assertSame('edit', $preset['leads']);
        $this->assertSame('view', $preset['dashboard']);
        $this->assertSame('view', $preset['clients']);
        $this->assertSame('view', $preset['services']);
        $this->assertSame('view', $preset['products']);
        $this->assertSame('view', $preset['guide']);
        $this->assertSame('none', $preset['finance']);
        $this->assertSame('none', $preset['staff']);
        $this->assertSame('none', $preset['legal_tasks']);

        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Ayan Growth',
            'email' => 'ayan.specialist@laleen.qa',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'role' => 'marketing_specialist',
            'permissions' => $preset,
        ])->assertRedirect(route('users.index'));

        $specialist = User::where('email', 'ayan.specialist@laleen.qa')->firstOrFail();
        $this->assertSame('marketing_specialist', $specialist->role);
        $this->assertSame('Marketing Specialist', $specialist->roleBadgeLabel());

        // 1. Sidebar visibility on Dashboard: permitted links visible, No Access links completely hidden
        $dashResp = $this->actingAs($specialist)->get(route('dashboard'))->assertOk();
        $dashResp->assertSee(route('leads.index'), false);
        $dashResp->assertSee(route('customers.index'), false);
        $dashResp->assertSee(route('services.index'), false);
        $dashResp->assertSee(route('products.index'), false);
        $dashResp->assertSee(route('kpi.hub'), false);
        $dashResp->assertSee(route('ecommerce.index'), false);
        $dashResp->assertSee(route('guide.index'), false);

        $dashResp->assertDontSee(route('appointments.revenue.index'), false);
        $dashResp->assertDontSee(route('staffs.index'), false);
        $dashResp->assertDontSee(route('legal.tasks.index'), false);
        $dashResp->assertDontSee(route('command-center.index'), false);
        $dashResp->assertDontSee(route('appointments.index'), false);
        $dashResp->assertDontSee(route('appointments.calendar'), false);

        // 2. No Access returns 403 on direct URL access
        $this->actingAs($specialist)->get(route('appointments.revenue.index'))->assertForbidden();
        $this->actingAs($specialist)->get(route('staffs.index'))->assertForbidden();
        $this->actingAs($specialist)->get(route('legal.tasks.index'))->assertForbidden();
        $this->actingAs($specialist)->get(route('command-center.index'))->assertForbidden();
        $this->actingAs($specialist)->get(route('appointments.index'))->assertForbidden();
        $this->actingAs($specialist)->get(route('appointments.calendar'))->assertForbidden();

        // 3. View Only allows GET and blocks POST/PUT/DELETE mutations with 403
        $this->actingAs($specialist)->get(route('services.index'))->assertOk();
        $this->actingAs($specialist)->post(route('services.store'), [
            'name' => 'Unauthorized Service',
            'price' => 200,
            'duration' => 45,
        ])->assertForbidden();

        $this->actingAs($specialist)->get(route('products.index'))->assertOk();
        $this->actingAs($specialist)->post(route('products.store'), [
            'name' => 'Unauthorized Product',
            'price' => 95,
            'stock_quantity' => 5,
        ])->assertForbidden();

        $this->actingAs($specialist)->get(route('customers.index'))->assertOk();
        $this->actingAs($specialist)->post(route('customers.store'), [
            'name' => 'Blocked Client Mutation',
            'phone' => '55990011',
        ])->assertForbidden();

        // 4. Full Edit allows mutations on Leads, KPIs (Ads), and Ecommerce
        $this->actingAs($specialist)->post(route('leads.store'), [
            'country_code' => '974',
            'phone_number' => '55114477',
            'customer_name' => 'Meta Ad Prospect',
            'source' => 'instagram',
            'category' => 'inquiry',
            'service_interest' => ['Hair Color & Balayage'],
            'next_followup_date' => now('Asia/Qatar')->addDay()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leads', ['phone' => Lead::normalizePhone('974', '55114477')]);

        $this->actingAs($specialist)->post(route('kpi.ad-leads.store'), [
            'date' => now('Asia/Qatar')->toDateString(),
            'customer_name' => 'TikTok Lead Client',
            'country_code' => '974',
            'phone_number' => '66223344',
            'category' => AdLeadEntry::availableCategories()[0],
            'branch' => 'old_airport',
        ])->assertRedirect(route('kpi.ads.index'))->assertSessionHasNoErrors();
        $this->assertSame(1, AdLeadEntry::where('phone', Lead::normalizePhone('974', '66223344'))->count());

        $this->actingAs($specialist)->get(route('ecommerce.index'))->assertOk();

        // 5. Guide & Staff Contracts Integration
        $guideResp = $this->actingAs($specialist)->get(route('guide.index'))->assertOk();
        $guideResp->assertSee('data-initial-tab="marketing_content"', false);
        $guideResp->assertSee('Marketing Specialist');
        $guideResp->assertSee('Paid Ads & Organic Content');

        $contractProfile = StaffContract::resolveUserContractProfile($specialist);
        $this->assertSame('Marketing Specialist', $contractProfile['tag']);
        $this->assertSame('Marketing Specialist', $contractProfile['role_title']);
        $this->assertSame('remote_chat_creative', $contractProfile['template_key']);

        $this->actingAs($this->superAdmin)
            ->get(route('staffs.index', ['tab' => 'contracts']))
            ->assertOk()
            ->assertSee('Ayan Growth — Marketing Specialist');
    }

    public function test_social_media_editor_role_end_to_end_enforces_no_access_view_only_full_edit_guide_and_contracts(): void
    {
        $preset = User::defaultPermissionsForRole('social_media_editor');

        $this->assertSame('edit', $preset['kpis']);
        $this->assertSame('view', $preset['services']);
        $this->assertSame('view', $preset['products']);
        $this->assertSame('view', $preset['guide']);
        $this->assertSame('none', $preset['dashboard']);
        $this->assertSame('none', $preset['finance']);
        $this->assertSame('none', $preset['staff']);
        $this->assertSame('none', $preset['leads']);
        $this->assertSame('none', $preset['clients']);
        $this->assertSame('none', $preset['legal_tasks']);

        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Maha Reels',
            'email' => 'maha.editor@laleen.qa',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'role' => 'social_media_editor',
            'permissions' => $preset,
        ])->assertRedirect(route('users.index'));

        $editor = User::where('email', 'maha.editor@laleen.qa')->firstOrFail();
        $this->assertSame('social_media_editor', $editor->role);
        $this->assertSame('Social Media Editor', $editor->roleBadgeLabel());
        $this->assertSame(route('kpi.hub'), $editor->defaultLandingUrl());

        // 1. No Access returns 403 across Dashboard, Finance, Staff, Leads, Clients, Legal Tasks
        $this->actingAs($editor)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($editor)->get(route('appointments.revenue.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('staffs.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('leads.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('legal.tasks.index'))->assertForbidden();

        // 2. Sidebar on Content Calendar renders only KPIs, Services, Products, and Guide
        $calResp = $this->actingAs($editor)->get(route('kpi.content-calendar.index'))->assertOk();
        $calResp->assertSee(route('kpi.hub'), false);
        $calResp->assertSee(route('services.index'), false);
        $calResp->assertSee(route('products.index'), false);
        $calResp->assertSee(route('guide.index'), false);

        $calResp->assertDontSee(route('dashboard'), false);
        $calResp->assertDontSee(route('leads.index'), false);
        $calResp->assertDontSee(route('customers.index'), false);
        $calResp->assertDontSee(route('appointments.revenue.index'), false);
        $calResp->assertDontSee(route('staffs.index'), false);
        $calResp->assertDontSee(route('legal.tasks.index'), false);

        // 3. Full Edit on Content Calendar (Reels/Stories tracker) works
        $weekStart = now('Asia/Qatar')->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->actingAs($editor)->post(route('kpi.content-calendar.update'), [
            'week_start_date' => $weekStart,
            'days' => [
                'Monday' => [
                    'branch' => 'Old Airport',
                    'owner' => 'Maha Reels',
                    'content_pillar' => 'Transformation',
                    'reel_content' => 'Bridal HydraFacial Glow Reveal',
                    'reel_link' => 'https://instagram.com/reel/laleen123',
                    'stories_content' => '3 behind-the-scenes clips',
                    'is_reel_posted' => '1',
                    'are_stories_posted' => '1',
                ],
            ],
        ])->assertRedirect();

        $this->assertTrue(
            SocialContentPlan::where('week_start_date', $weekStart)
                ->where('day_of_week', 'Monday')
                ->where('reel_content', 'Bridal HydraFacial Glow Reveal')
                ->exists()
        );

        // 4. Guide & Staff Contracts integration
        $guideResp = $this->actingAs($editor)->get(route('guide.index'))->assertOk();
        $guideResp->assertSee('data-initial-tab="marketing_content"', false);
        $guideResp->assertSee('Social Media Editor');
        $guideResp->assertSee('Paid Ads & Organic Content');

        $contractProfile = StaffContract::resolveUserContractProfile($editor);
        $this->assertSame('Social Media Editor', $contractProfile['tag']);
        $this->assertSame('Social Media Editor', $contractProfile['role_title']);

        $this->actingAs($this->superAdmin)
            ->get(route('staffs.index', ['tab' => 'contracts']))
            ->assertOk()
            ->assertSee('Maha Reels — Social Media Editor');

        // 5. Updating Guide to No Access via PUT /users/{user} hides Guide link and returns 403 on /guide
        $updatedPerms = $preset;
        $updatedPerms['guide'] = 'no_access';

        $this->actingAs($this->superAdmin)->put(route('users.update', $editor), [
            'name' => 'Maha Reels',
            'email' => 'maha.editor@laleen.qa',
            'role' => 'social_media_editor',
            'permissions' => $updatedPerms,
        ])->assertRedirect(route('users.index'));

        $editor->refresh();
        $this->assertFalse($editor->canView('guide'));
        $this->actingAs($editor)->get(route('guide.index'))->assertForbidden();
        $this->actingAs($editor)
            ->get(route('kpi.content-calendar.index'))
            ->assertOk()
            ->assertDontSee(route('guide.index'), false);
    }

    public function test_backward_compatibility_preserves_legacy_user_permissions_until_customized(): void
    {
        $legacyAgent = User::factory()->create([
            'name' => 'Legacy Agent',
            'email' => 'legacy.agent@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'edit',
                'bookings' => 'edit',
            ],
        ]);

        // Legacy record without 'dashboard', 'calendar', or 'guide' keys keeps non-breaking access
        $this->assertTrue($legacyAgent->canView('dashboard'));
        $this->assertTrue($legacyAgent->canEdit('calendar'));
        $this->assertTrue($legacyAgent->canView('guide'));
        $this->assertFalse($legacyAgent->canView('command_center'));
        $this->assertFalse($legacyAgent->canView('legal_tasks'));

        $this->actingAs($legacyAgent)->get(route('dashboard'))->assertOk();
        $this->actingAs($legacyAgent)->get(route('appointments.index'))->assertOk();
        $this->actingAs($legacyAgent)->get(route('appointments.calendar'))->assertOk();
        $this->actingAs($legacyAgent)->get(route('guide.index'))->assertOk();

        // Once Admin explicitly sets Enhanced Calendar to No Access while keeping Bookings View Only:
        $customPerms = $legacyAgent->effectivePermissionsMap();
        $customPerms['bookings'] = 'view_only';
        $customPerms['calendar'] = 'no_access';

        $this->actingAs($this->superAdmin)->put(route('users.update', $legacyAgent), [
            'name' => 'Legacy Agent',
            'email' => 'legacy.agent@laleen.qa',
            'role' => 'agent',
            'permissions' => $customPerms,
        ])->assertRedirect(route('users.index'));

        $legacyAgent->refresh();
        $this->actingAs($legacyAgent)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee(route('appointments.index'), false)
            ->assertDontSee(route('appointments.calendar'), false);

        $this->actingAs($legacyAgent)->get(route('appointments.calendar'))->assertForbidden();
    }
}
