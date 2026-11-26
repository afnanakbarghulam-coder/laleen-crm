<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceStepsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $chatAgent;
    protected User $salesCoordinator;
    protected User $salonManager;
    protected User $legalTeam;
    protected User $salonStaff;
    protected User $marketingSpecialist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-steps@laleen.test',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('admin'),
        ]);

        $this->chatAgent = User::factory()->create([
            'role' => 'agent',
            'email' => 'agent-steps@laleen.test',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);

        $this->salesCoordinator = User::factory()->create([
            'role' => 'sales_coordinator',
            'email' => 'sales-steps@laleen.test',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('sales_coordinator'),
        ]);

        $this->salonManager = User::factory()->create([
            'role' => 'salon_manager',
            'email' => 'manager-steps@laleen.test',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);

        $this->legalTeam = User::factory()->create([
            'role' => 'legal_team',
            'email' => 'legal-steps@laleen.test',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('legal_team'),
        ]);

        $this->salonStaff = User::factory()->create([
            'role' => 'salon_staff',
            'email' => 'staff-steps@laleen.test',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);

        $this->marketingSpecialist = User::factory()->create([
            'role' => 'marketing_specialist',
            'email' => 'marketing-steps@laleen.test',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('marketing_specialist'),
        ]);
    }

    public function test_admin_can_create_and_edit_service_with_ordered_steps(): void
    {
        $category = ServiceCategory::create(['name' => 'Facials', 'sort_order' => 1]);
        $stylist = Staff::create([
            'name' => 'Amina Specialist',
            'role' => 'Esthetician',
            'branch' => 'old_airport',
            'status' => 'active',
        ]);

        // 1. Create service with ordered steps (including blanks & surrounding spaces)
        $this->actingAs($this->admin)
            ->post(route('services.store'), [
                'name' => 'Fruit Facial',
                'category_id' => $category->id,
                'description' => 'Fresh botanical radiance facial.',
                'price' => 220,
                'duration' => 60,
                'staff_ids' => [$stylist->id],
                'steps_submitted' => '1',
                'steps' => [
                    '  Deep Cleansing  ',
                    '',
                    '   ',
                    'Gentle Exfoliation & Steam',
                    'Fruit Mask',
                    'Moisturizer & SPF ',
                ],
            ])
            ->assertRedirect();

        $service = Service::where('name', 'Fruit Facial')->firstOrFail();
        $this->assertSame([
            'Deep Cleansing',
            'Gentle Exfoliation & Steam',
            'Fruit Mask',
            'Moisturizer & SPF',
        ], $service->steps);

        // Services page shows the 4 steps pill & Service steps tab inside the modal
        $this->actingAs($this->admin)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('Fruit Facial')
            ->assertSee('4 steps')
            ->assertSee('Service steps')
            ->assertSee('Copy Steps');

        // 2. Edit service reordering and trimming steps
        $this->actingAs($this->admin)
            ->put(route('services.update', $service), [
                'name' => 'Fruit Facial',
                'category_id' => $category->id,
                'description' => 'Updated facial description.',
                'price' => 240,
                'duration' => 65,
                'staff_ids' => [$stylist->id],
                'steps_submitted' => '1',
                'steps' => [
                    'Skin Analysis',
                    'Deep Cleansing',
                    '  ',
                    'Botanical Mask & SPF',
                ],
            ])
            ->assertRedirect();

        $service->refresh();
        $this->assertEquals(240.0, (float) $service->price);
        $this->assertSame([
            'Skin Analysis',
            'Deep Cleansing',
            'Botanical Mask & SPF',
        ], $service->steps);

        // 3. Clearing all steps with steps_submitted=1 stores null cleanly
        $this->actingAs($this->admin)
            ->put(route('services.update', $service), [
                'name' => 'Fruit Facial',
                'category_id' => $category->id,
                'price' => 240,
                'duration' => 65,
                'steps_submitted' => '1',
                'steps' => ['', '   '],
            ])
            ->assertRedirect();

        $this->assertNull($service->fresh()->steps);
    }

    public function test_chat_agent_sales_coordinator_and_salon_manager_can_update_steps_without_touching_pricing_or_deleting(): void
    {
        $category = ServiceCategory::create(['name' => 'Hair Spa', 'sort_order' => 1]);
        $service = Service::create([
            'name' => 'Botox Hair Therapy',
            'category_id' => $category->id,
            'price' => 450.00,
            'duration' => 120,
            'steps' => ['Clarifying Wash'],
        ]);

        // Chat Agent sees Services page with clickable steps pill & modal (not full-edit Delete)
        $this->actingAs($this->chatAgent)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('Botox Hair Therapy')
            ->assertSee('1 step')
            ->assertSee('Service steps')
            ->assertDontSee('Delete this service?');

        // 1. Chat Agent updates steps via PATCH /services/{service}/steps (200 OK)
        $agentResp = $this->actingAs($this->chatAgent)
            ->patchJson(route('services.steps.update', $service), [
                'steps' => [
                    '  Clarifying Detox Wash  ',
                    '',
                    'Botox Serum Application',
                    'Thermal Sealing & Blowdry',
                ],
                // Attempted tamper fields must be completely ignored
                'price' => 10,
                'duration' => 5,
                'name' => 'Tampered Name',
            ]);

        $agentResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('steps_count', 3)
            ->assertJsonPath('steps', [
                'Clarifying Detox Wash',
                'Botox Serum Application',
                'Thermal Sealing & Blowdry',
            ]);

        $service->refresh();
        $this->assertSame('Botox Hair Therapy', $service->name);
        $this->assertEquals(450.0, (float) $service->price);
        $this->assertSame(120, (int) $service->duration);
        $this->assertSame([
            'Clarifying Detox Wash',
            'Botox Serum Application',
            'Thermal Sealing & Blowdry',
        ], $service->steps);

        // 2. Sales Coordinator reorders & updates steps (200 OK)
        $salesResp = $this->actingAs($this->salesCoordinator)
            ->patchJson(route('services.steps.update', $service), [
                'steps' => [
                    'Consultation & Strand Check',
                    'Clarifying Detox Wash',
                    'Botox Serum Application',
                    'Thermal Sealing & Blowdry',
                ],
            ]);
        $salesResp->assertOk()->assertJsonPath('steps_count', 4);
        $this->assertCount(4, $service->fresh()->steps);
        $this->assertEquals(450.0, (float) $service->fresh()->price);

        // 3. Salon Manager updates steps (200 OK)
        $mgrResp = $this->actingAs($this->salonManager)
            ->patchJson(route('services.steps.update', $service), [
                'steps' => [
                    'Clarifying Detox Wash',
                    'Botox Infusion + Steam',
                ],
            ]);
        $mgrResp->assertOk()->assertJsonPath('steps_count', 2);
        $this->assertSame(['Clarifying Detox Wash', 'Botox Infusion + Steam'], $service->fresh()->steps);

        // 4. Chat Agent, Sales Coordinator, and Salon Manager still get 403 on main store, update, and delete endpoints
        foreach ([$this->chatAgent, $this->salesCoordinator, $this->salonManager] as $nonCatalogEditor) {
            $this->actingAs($nonCatalogEditor)
                ->post(route('services.store'), [
                    'name' => 'Unauthorized Service',
                    'price' => 100,
                    'duration' => 30,
                ])
                ->assertForbidden();

            $this->actingAs($nonCatalogEditor)
                ->put(route('services.update', $service), [
                    'name' => 'Hijacked Name',
                    'price' => 1,
                    'duration' => 10,
                ])
                ->assertForbidden();

            $this->actingAs($nonCatalogEditor)
                ->delete(route('services.destroy', $service))
                ->assertForbidden();
        }

        // Price, duration, and existence remain intact
        $service->refresh();
        $this->assertSame('Botox Hair Therapy', $service->name);
        $this->assertEquals(450.0, (float) $service->price);
        $this->assertSame(120, (int) $service->duration);
    }

    public function test_legal_team_salon_staff_and_unauthorized_roles_get_403_and_invalid_steps_return_422(): void
    {
        $service = Service::create([
            'name' => 'Classic Manicure',
            'price' => 90.00,
            'duration' => 40,
            'steps' => ['Nail Shaping', 'Cuticle Care'],
        ]);

        // Legal Team receives 403 on PATCH /services/{service}/steps and GET /services
        $this->actingAs($this->legalTeam)
            ->patchJson(route('services.steps.update', $service), ['steps' => ['Hacked Step']])
            ->assertForbidden();
        $this->actingAs($this->legalTeam)
            ->get(route('services.index'))
            ->assertForbidden();

        // Salon Staff (portal-isolated) receives 403 on PATCH /services/{service}/steps
        $this->actingAs($this->salonStaff)
            ->patchJson(route('services.steps.update', $service), ['steps' => ['Hacked Step']])
            ->assertForbidden();

        // Read-only marketing role without services:edit receives 403 on write, 200 on view
        $this->actingAs($this->marketingSpecialist)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('2 steps');
        $this->actingAs($this->marketingSpecialist)
            ->patchJson(route('services.steps.update', $service), ['steps' => ['Blocked']])
            ->assertForbidden();

        // Validation rejects non-array steps (422)
        $this->actingAs($this->chatAgent)
            ->patchJson(route('services.steps.update', $service), [
                'steps' => 'Deep Cleansing',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['steps']);

        // Validation rejects step strings longer than 255 characters (422)
        $this->actingAs($this->admin)
            ->patchJson(route('services.steps.update', $service), [
                'steps' => [str_repeat('A', 256)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['steps.0']);

        // Original steps unchanged after blocked / invalid attempts
        $this->assertSame(['Nail Shaping', 'Cuticle Care'], $service->fresh()->steps);
    }
}
