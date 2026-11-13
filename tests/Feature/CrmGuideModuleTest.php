<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmGuideModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_deactivated_users_cannot_access_guide(): void
    {
        $this->get(route('guide.index'))
            ->assertRedirect('/login');

        $deactivated = User::factory()->create([
            'role' => 'agent',
            'is_active' => false,
            'permissions' => ['leads' => 'edit', 'bookings' => 'edit'],
        ]);

        $this->actingAs($deactivated)
            ->get(route('guide.index'))
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_authenticated_users_see_sidebar_and_header_guide_links(): void
    {
        $user = User::factory()->create([
            'name' => 'Areeba',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'edit',
                'bookings' => 'edit',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('CRM Guide & SOPs');
        $response->assertSee(route('guide.index'), false);
        $response->assertSee('Laleen Team Guide');
        $response->assertSee('Your daily workflow, booking rules, and targets.');
        $response->assertSee('Print / Save PDF');
    }

    public function test_minimal_ui_anti_nesting_zero_meta_copy_and_agent_stat_filtering(): void
    {
        $agent = User::factory()->create([
            'name' => 'Areeba',
            'email' => 'areeba@laleen.test',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'edit',
                'bookings' => 'edit',
                'services' => 'view',
                'kpis' => 'view',
            ],
        ]);

        $response = $this->actingAs($agent)->get(route('guide.index'));
        $response->assertOk();

        // Zero developer/meta copy or legacy clutter headers
        $response->assertDontSee('5-second visual playbooks');
        $response->assertDontSee('Server-filtered view');
        $response->assertDontSee('Role Playbooks');
        $response->assertDontSee('Details / Formula');
        $response->assertDontSee('When to Use & Goal');
        $response->assertDontSee('Exact Step-by-Step Clicks');
        $response->assertDontSee('Golden Rules');

        // Zero raw route paths in visible copy
        $response->assertDontSee('(/kpis/agents)');
        $response->assertDontSee('(/leads)');
        $response->assertDontSee('(/appointments/calendar)');
        $response->assertDontSee('(/services)');

        // Inline visual UI pills rendered
        $response->assertSee('<span class="g-ui-pill">+ Log Sign-In</span>', false);
        $response->assertSee('<span class="g-ui-pill">Booking Agent</span>', false);
        $response->assertSee('<span class="g-ui-pill">Redeem Package</span>', false);

        // Step 3 check-out edit bug absent
        $response->assertDontSee('check-out time');
        $response->assertDontSee('update check-out');

        // Chat Agent sees only her 3 core targets (no ≤ 48h Complaint SLA noise)
        $response->assertSee('13 / Day');
        $response->assertSee('≥ 85%');
        $response->assertSee('≥ 30%');
        $response->assertDontSee('≤ 48h');
        $response->assertDontSee('Complaint SLA');

        // Clean borderless horizontal category pills & tracks rendered
        $response->assertSee('gd-cat-bar', false);
        $response->assertSee('gd-track', false);
        $response->assertSee('Visit 1 Paid');
        $response->assertSee('0.00 QAR');
    }

    public function test_chat_agent_sees_only_allowed_playbooks_with_zero_admin_or_finance_html_leakage(): void
    {
        $agent = User::factory()->create([
            'name' => 'Zoya',
            'email' => 'zoya@laleen.test',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'edit',
                'bookings' => 'edit',
                'services' => 'view',
                'kpis' => 'view',
                'finance' => 'none',
                'clients' => 'none',
                'staff_management' => 'none',
                'ecommerce' => 'none',
            ],
        ]);

        $response = $this->actingAs($agent)->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('data-initial-tab="chat_cs"', false);
        $response->assertSee('Customer Service & Booking Desk');

        // Allowed playbooks & exact SOP labels
        $response->assertSee('Chat & Leads');
        $response->assertSee('+ Log Sign-In');
        $response->assertSee('Booking Agent');
        $response->assertSee('13/Day Booking Quota');
        $response->assertSee('Calendar, Combos & Reception');
        $response->assertSee('+ Add Combo');
        $response->assertSee('Redeem Package');

        // Strict zero HTML leakage for gated sub-cards and restricted playbooks
        $response->assertDontSee('POS Checkout & Split Payments');
        $response->assertDontSee('Walk-In Sale');
        $response->assertDontSee('Paid Ads & Organic Content');
        $response->assertDontSee('Branch Managers, Staff Sales & HR');
        $response->assertDontSee('E-commerce Hub (PK & QA)');
        $response->assertDontSee('Branch P&L');
        $response->assertDontSee('CLI Maintenance');
        $response->assertDontSee('Staff Access');
        $response->assertDontSee('Manage / Extend');
        $response->assertDontSee('Preview Role:');

        // Non-admin attempting ?preview_role=all cannot escalate visibility
        $tampered = $this->actingAs($agent)->get(route('guide.index', ['preview_role' => 'all']));
        $tampered->assertOk();
        $tampered->assertDontSee('Branch P&L');
        $tampered->assertDontSee('CLI Maintenance');
        $tampered->assertDontSee('Preview Role:');
    }

    public function test_branch_manager_defaults_to_managers_tab_with_strict_finance_and_admin_isolation(): void
    {
        $ahana = User::factory()->create([
            'name' => 'Ahana',
            'email' => 'ahana@laleen.test',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'edit',
                'bookings' => 'edit',
                'services' => 'view',
                'finance' => 'none',
                'ecommerce' => 'none',
                'staff_management' => 'none',
                'leads' => 'none',
            ],
        ]);

        $response = $this->actingAs($ahana)->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('data-initial-tab="managers_hr"', false);
        $response->assertSee('Branch Manager · Old Airport');
        $response->assertSee('Branch Managers, Staff Sales & HR');
        $response->assertSee('Mon – Tue');
        $response->assertSee('100% Roster');
        $response->assertSee('🟢 Mon: Open');
        $response->assertSee('Monday Staff Evaluation Compliance');
        $response->assertSee('Branch Mystery Shopper Assessments');
        $response->assertSee('Staff Sales & Upsell Coaching');

        // Must NOT leak Admin grading steps, Finance/POS, E-commerce, or Admin governance
        $response->assertDontSee('+ New Branch Evaluation');
        $response->assertDontSee('POS Checkout & Split Payments');
        $response->assertDontSee('E-commerce Hub (PK & QA)');
        $response->assertDontSee('Branch P&L');
        $response->assertDontSee('CLI Maintenance');
        $response->assertDontSee('Staff Access');
        $response->assertDontSee('Manage / Extend');
        $response->assertDontSee('Preview Role:');
    }

    public function test_marketing_specialist_defaults_to_marketing_content_playbook(): void
    {
        $sana = User::factory()->create([
            'name' => 'Sana',
            'email' => 'sana@laleen.test',
            'role' => 'user',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'edit',
                'clients' => 'view',
                'finance' => 'none',
                'bookings' => 'none',
                'leads' => 'none',
                'ecommerce' => 'none',
                'staff_management' => 'none',
            ],
        ]);

        $response = $this->actingAs($sana)->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('data-initial-tab="marketing_content"', false);
        $response->assertSee('Marketing & Content');
        $response->assertSee('Paid Ads & Organic Content');
        $response->assertSee('100% Separate');
        $response->assertSee('Weekly Content Calendar');
        $response->assertSee('Clients, Loyalty & Retention Radar');

        $response->assertDontSee('Admin Breach Log');
        $response->assertDontSee('Manage / Extend');
        $response->assertDontSee('Branch P&L');
        $response->assertDontSee('CLI Maintenance');
        $response->assertDontSee('Preview Role:');
    }

    public function test_regular_admin_sees_all_playbooks_and_preview_switcher_without_super_admin_users_card(): void
    {
        $admin = User::factory()->create([
            'name' => 'Executive Admin',
            'email' => 'admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('data-initial-tab="all"', false);
        $response->assertSee('Preview Role:');

        // All 7 playbooks + Admin-only cards visible
        $response->assertSee('Chat Agents & CS Lead');
        $response->assertSee('Calendar, Combos & Reception');
        $response->assertSee('POS Checkout & Split Payments');
        $response->assertSee('Clients, Loyalty & Retention Radar');
        $response->assertSee('Admin Package Override');
        $response->assertSee('Manage / Extend');
        $response->assertSee('Paid Ads & Organic Content');
        $response->assertSee('Branch Managers, Staff Sales & HR');
        $response->assertSee('E-commerce Hub (PK & QA)');
        $response->assertSee('Admin, Branch P&L & System Maintenance');
        $response->assertSee('Branch P&L & Sales Ledger');
        $response->assertSee('CLI Maintenance & Health Gates');

        // Zero raw route URLs in copy
        $response->assertDontSee('(/users)');
        $response->assertDontSee('(/revenue)');

        // Super-Admin exclusive Staff Access card MUST NOT be visible to regular admin
        $response->assertDontSee('Staff Access & Permissions');
        $response->assertDontSee('Open Staff Access');

        // Admin ?preview_role=agent renders exact filtered Agent SOPs while keeping the Admin preview switcher
        $previewResponse = $this->actingAs($admin)->get(route('guide.index', ['preview_role' => 'agent']));
        $previewResponse->assertOk();
        $previewResponse->assertSee('Chat Agent View');
        $previewResponse->assertSee('Preview Role:');
        $previewResponse->assertDontSee('Server-filtered view');
        $previewResponse->assertSee('data-initial-tab="chat_cs"', false);
        $previewResponse->assertSee('Chat & Leads');
        $previewResponse->assertSee('Calendar, Combos & Reception');

        $previewResponse->assertDontSee('POS Checkout & Split Payments');
        $previewResponse->assertDontSee('Admin Package Override');
        $previewResponse->assertDontSee('Manage / Extend');
        $previewResponse->assertDontSee('Paid Ads & Organic Content');
        $previewResponse->assertDontSee('Branch Managers, Staff Sales & HR');
        $previewResponse->assertDontSee('E-commerce Hub (PK & QA)');
        $previewResponse->assertDontSee('Branch P&L & Sales Ledger');
        $previewResponse->assertDontSee('CLI Maintenance & Health Gates');
    }

    public function test_super_admin_sees_staff_access_provisioning_sop_card(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Afnan',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('Super-Admin');
        $response->assertSee('Staff Access & Permissions');
        $response->assertSee('Open Staff Access');
    }

    public function test_zero_permission_user_receives_clean_200_onboarding_fallback_without_sop_leaks(): void
    {
        $newAccount = User::factory()->create([
            'name' => 'New Trainee',
            'email' => 'trainee@laleen.test',
            'role' => 'user',
            'is_active' => true,
            'permissions' => [],
        ]);

        $response = $this->actingAs($newAccount)->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('data-initial-tab="general"', false);
        $response->assertSee('General Account & Getting Module Access');
        $response->assertSee('Open Dashboard');

        // Zero SOP playbooks leaked & zero meta jargon
        $response->assertDontSee('Role-Scoped Security Active');
        $response->assertDontSee('(/profile)');
        $response->assertDontSee('Chat Agents & CS Lead');
        $response->assertDontSee('Calendar, Combos & Reception');
        $response->assertDontSee('Clients, Loyalty & Retention Radar');
        $response->assertDontSee('Paid Ads & Organic Content');
        $response->assertDontSee('Branch Managers, Staff Sales & HR');
        $response->assertDontSee('E-commerce Hub (PK & QA)');
        $response->assertDontSee('Branch P&L');
        $response->assertDontSee('CLI Maintenance');
        $response->assertDontSee('Staff Access');
        $response->assertDontSee('Preview Role:');
    }

    public function test_admin_can_preview_all_six_role_personas_cleanly(): void
    {
        $admin = User::factory()->create([
            'name' => 'Executive Admin',
            'email' => 'admin-preview@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $expectedDefaultTabs = [
            'all' => 'all',
            'agent' => 'chat_cs',
            'manager' => 'managers_hr',
            'marketing' => 'marketing_content',
            'frontdesk' => 'frontdesk_pos',
            'ecommerce' => 'ecommerce',
        ];

        foreach ($expectedDefaultTabs as $previewKey => $expectedTab) {
            $resp = $this->actingAs($admin)->get(route('guide.index', ['preview_role' => $previewKey]));
            $resp->assertOk();
            $resp->assertSee('data-initial-tab="' . $expectedTab . '"', false);
            $resp->assertSee('Preview Role:');
        }
    }
}
