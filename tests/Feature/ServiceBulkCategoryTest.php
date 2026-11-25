<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceBulkCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $viewOnlyUser;
    private User $noAccessUser;
    private User $salonStaffUser;
    private User $editorUser;
    private ServiceCategory $waxingCat;
    private ServiceCategory $hairCat;
    private Staff $stylist;
    private Service $chinShama;
    private Service $chinThreading;
    private Service $chinWax;
    private Service $blowdry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'admin.svcbulk@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->viewOnlyUser = User::factory()->create([
            'name' => 'View Only User',
            'email' => 'view.svcbulk@laleen.qa',
            'role' => 'agent',
            'permissions' => array_merge(User::defaultPermissionsForRole('agent'), ['services' => 'view']),
            'is_active' => true,
        ]);

        $this->noAccessUser = User::factory()->create([
            'name' => 'No Access User',
            'email' => 'none.svcbulk@laleen.qa',
            'role' => 'agent',
            'permissions' => array_merge(User::defaultPermissionsForRole('agent'), ['services' => 'none']),
            'is_active' => true,
        ]);

        $this->salonStaffUser = User::factory()->create([
            'name' => 'Salon Staff User',
            'email' => 'staff.svcbulk@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);

        $this->editorUser = User::factory()->create([
            'name' => 'Catalog Editor',
            'email' => 'editor.svcbulk@laleen.qa',
            'role' => 'ops_lead',
            'permissions' => array_merge(User::defaultPermissionsForRole('ops_lead'), ['services' => 'edit']),
            'is_active' => true,
        ]);

        $this->waxingCat = ServiceCategory::create(['name' => 'Waxing', 'color' => '#d98f83', 'sort_order' => 1]);
        $this->hairCat = ServiceCategory::create(['name' => 'Hair Care', 'color' => '#b98ea3', 'sort_order' => 2]);

        $this->stylist = Staff::create([
            'name' => 'Aisha Specialist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
        ]);

        $this->chinShama = Service::create([
            'name' => 'Chin shama',
            'category_id' => null,
            'price' => 35.00,
            'duration' => 15,
            'rebooking_interval_days' => 21,
            'treatment_type' => 'Facial Hair',
            'description' => 'Gentle chin shama',
        ]);
        $this->chinShama->staff()->sync([$this->stylist->id]);

        $this->chinThreading = Service::create([
            'name' => 'Chin Threading',
            'category_id' => null,
            'price' => 25.00,
            'duration' => 10,
            'rebooking_interval_days' => 14,
            'description' => 'Precision chin threading',
        ]);
        $this->chinThreading->staff()->sync([$this->stylist->id]);

        $this->chinWax = Service::create([
            'name' => 'Chin Wax',
            'category_id' => null,
            'price' => 30.00,
            'duration' => 15,
            'description' => 'Warm wax for chin area',
        ]);

        $this->blowdry = Service::create([
            'name' => 'Signature Blowdry',
            'category_id' => $this->hairCat->id,
            'price' => 120.00,
            'duration' => 45,
        ]);
    }

    public function test_uncategorized_quick_filter_counts_and_edit_vs_readonly_checkbox_visibility(): void
    {
        // Admin sees accurate All (4), Uncategorized (3), Waxing (0), Hair Care (1) counts + multi-select controls
        $allResp = $this->actingAs($this->admin)->get(route('services.index'))->assertOk();
        $this->assertSame(3, $allResp->viewData('uncategorizedCount'));
        $this->assertSame(4, $allResp->viewData('totalServicesCount'));
        $allResp->assertSee('Uncategorized');
        $allResp->assertSee('svc-bulk-check', false);
        $allResp->assertSee('svcBulkActionBar', false);
        $allResp->assertSee('Select all visible (4)');

        // Uncategorized filter shows ONLY the 3 uncategorized Chin services (excludes Signature Blowdry)
        $uncatResp = $this->actingAs($this->admin)
            ->get(route('services.index', ['category_id' => 'uncategorized']))
            ->assertOk();
        $uncatNames = $uncatResp->viewData('services')->flatten(1)->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Chin shama', 'Chin Threading', 'Chin Wax'], $uncatNames);
        $this->assertNotContains('Signature Blowdry', $uncatNames);

        preg_match('/<div class="svc-tab-pane[^"]*" id="tab-services">.*?<!-- \/TAB: SERVICES -->/s', $uncatResp->getContent(), $svcPaneMatch);
        $this->assertNotEmpty($svcPaneMatch[0] ?? '');
        $this->assertStringContainsString('Chin shama', $svcPaneMatch[0]);
        $this->assertStringContainsString('Chin Threading', $svcPaneMatch[0]);
        $this->assertStringContainsString('Chin Wax', $svcPaneMatch[0]);
        $this->assertStringNotContainsString('Signature Blowdry', $svcPaneMatch[0]);

        // Uncategorized filter combined with search narrows accurately
        $searchUncat = $this->actingAs($this->admin)
            ->get(route('services.index', ['category_id' => 'uncategorized', 'search' => 'Wax']))
            ->assertOk();
        $this->assertSame(['Chin Wax'], $searchUncat->viewData('services')->flatten(1)->pluck('name')->all());

        // View-only user sees Uncategorized filter and services, but ZERO bulk checkboxes or bulk bar
        $viewResp = $this->actingAs($this->viewOnlyUser)->get(route('services.index'))->assertOk();
        $viewResp->assertSee('Uncategorized');
        $viewResp->assertSee('Chin shama');
        $viewResp->assertDontSee('svc-bulk-check', false);
        $viewResp->assertDontSee('id="svcBulkActionBar"', false);
        $viewResp->assertDontSee('svcSelectAllVisible', false);
    }

    public function test_bulk_assign_category_and_move_back_to_uncategorized_preserves_all_other_attributes_and_relations(): void
    {
        $combo = Combo::create([
            'name' => 'Face Grooming Combo',
            'price' => 70.00,
            'status' => 'active',
        ]);
        $combo->services()->sync([$this->chinShama->id, $this->chinThreading->id]);

        // 1. Move all 3 Chin services from Uncategorized -> Waxing
        $res = $this->actingAs($this->admin)->postJson(route('services.bulk-category'), [
            'service_ids' => [$this->chinShama->id, $this->chinThreading->id, $this->chinWax->id],
            'category_id' => $this->waxingCat->id,
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated_count', 3)
            ->assertJsonPath('category_id', $this->waxingCat->id)
            ->assertJsonPath('category_name', 'Waxing')
            ->assertJsonPath('message', 'Moved 3 services to Waxing')
            ->assertJsonPath('uncategorized_count', 0)
            ->assertJsonPath('total_count', 4)
            ->assertJsonPath('category_counts.' . $this->waxingCat->id, 3)
            ->assertJsonPath('category_counts.' . $this->hairCat->id, 1);

        // Verify ONLY category_id changed; price, duration, rebooking, treatment_type, description, staff skills & combos intact!
        $this->chinShama->refresh();
        $this->chinThreading->refresh();
        $this->chinWax->refresh();
        $this->blowdry->refresh();

        $this->assertSame($this->waxingCat->id, $this->chinShama->category_id);
        $this->assertEquals(35.00, (float) $this->chinShama->price);
        $this->assertSame(15, (int) $this->chinShama->duration);
        $this->assertSame(21, (int) $this->chinShama->rebooking_interval_days);
        $this->assertSame('Facial Hair', $this->chinShama->treatment_type);
        $this->assertSame('Gentle chin shama', $this->chinShama->description);
        $this->assertSame([$this->stylist->id], $this->chinShama->staff()->pluck('staff.id')->all());
        $this->assertSame([$this->chinShama->id, $this->chinThreading->id], $combo->fresh()->services()->orderBy('services.id')->pluck('services.id')->all());

        // Untouched service remains in Hair Care
        $this->assertSame($this->hairCat->id, $this->blowdry->category_id);

        // 2. Move 2 services back to -- Uncategorized -- (`category_id: null`) using non-admin user with services=edit
        $uncatRes = $this->actingAs($this->editorUser)->postJson(route('services.bulk-category'), [
            'service_ids' => [$this->chinThreading->id, $this->chinWax->id],
            'category_id' => null,
        ]);

        $uncatRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated_count', 2)
            ->assertJsonPath('category_id', null)
            ->assertJsonPath('category_name', 'Uncategorized')
            ->assertJsonPath('message', 'Moved 2 services to Uncategorized')
            ->assertJsonPath('uncategorized_count', 2)
            ->assertJsonPath('category_counts.' . $this->waxingCat->id, 1);

        $this->assertNull($this->chinThreading->fresh()->category_id);
        $this->assertNull($this->chinWax->fresh()->category_id);
        $this->assertSame($this->waxingCat->id, $this->chinShama->fresh()->category_id);
    }

    public function test_bulk_category_validation_errors_and_strict_403_authorization(): void
    {
        // 403 for view-only, no-access, and Salon Staff users
        foreach ([$this->viewOnlyUser, $this->noAccessUser, $this->salonStaffUser] as $blockedUser) {
            $this->actingAs($blockedUser)->postJson(route('services.bulk-category'), [
                'service_ids' => [$this->chinShama->id],
                'category_id' => $this->waxingCat->id,
            ])->assertForbidden();
        }
        $this->assertNull($this->chinShama->fresh()->category_id);

        // 422 for empty service_ids
        $this->actingAs($this->admin)->postJson(route('services.bulk-category'), [
            'service_ids' => [],
            'category_id' => $this->waxingCat->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['service_ids']);

        // 422 for missing service_ids
        $this->actingAs($this->admin)->postJson(route('services.bulk-category'), [
            'category_id' => $this->waxingCat->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['service_ids']);

        // 422 for non-existent service ID
        $this->actingAs($this->admin)->postJson(route('services.bulk-category'), [
            'service_ids' => [999999],
            'category_id' => $this->waxingCat->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['service_ids.0']);

        // 422 for non-existent category ID
        $this->actingAs($this->admin)->postJson(route('services.bulk-category'), [
            'service_ids' => [$this->chinShama->id],
            'category_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['category_id']);

        // 422 for non-integer category ID
        $this->actingAs($this->admin)->postJson(route('services.bulk-category'), [
            'service_ids' => [$this->chinShama->id],
            'category_id' => 'bogus-category',
        ])->assertStatus(422)->assertJsonValidationErrors(['category_id']);

        // Confirm DB untouched after all failed validation attempts
        $this->assertNull($this->chinShama->fresh()->category_id);
    }
}
