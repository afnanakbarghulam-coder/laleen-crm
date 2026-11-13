<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\StaffContractCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $colleagueUser;
    protected Staff $beautician;
    protected Staff $branchManagerStaff;
    protected Staff $remoteSpecialist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Floor Supervisor',
            'email' => 'manager@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['staff_management' => 'edit'],
        ]);

        $this->colleagueUser = User::factory()->create([
            'name' => 'Colleague Stylist',
            'email' => 'colleague@laleen.qa',
            'role' => 'staff',
            'is_active' => true,
            'permissions' => ['staff_management' => 'view'],
        ]);

        Staff::create([
            'name' => 'Colleague Stylist',
            'first_name' => 'Colleague',
            'branch' => 'old_airport',
            'bookable' => true,
            'user_id' => $this->colleagueUser->id,
        ]);

        $this->beautician = Staff::create([
            'name' => 'Mariam Al-Dosari',
            'first_name' => 'Mariam',
            'last_name' => 'Al-Dosari',
            'branch' => 'old_airport',
            'bookable' => true,
            'base_salary' => 4500,
        ]);

        $this->branchManagerStaff = Staff::create([
            'name' => 'Ahana Kapoor',
            'first_name' => 'Ahana',
            'last_name' => 'Kapoor',
            'branch' => 'old_airport',
            'bookable' => false,
            'base_salary' => 7500,
        ]);

        $this->remoteSpecialist = Staff::create([
            'name' => 'Zoya Raza',
            'first_name' => 'Zoya',
            'last_name' => 'Raza',
            'branch' => 'both',
            'bookable' => false,
            'base_salary' => 0,
        ]);
    }

    public function test_staff_management_renders_streamlined_four_tab_pill_bar_without_multi_row_clutter(): void
    {
        // Default Contracts & Payroll view activates "📄 Staff Contracts" and hides Payroll sub-pane
        $contractsRes = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'contracts']))
            ->assertOk();

        $contractsRes->assertSee('Directory &amp; Profiles', false);
        $contractsRes->assertSee('Contracts &amp; Payroll', false);
        $contractsRes->assertSee('Shifts &amp; Ops Actions', false);
        $contractsRes->assertSee('HR &amp; Disciplinary', false);
        $contractsRes->assertDontSee('👥 Staff Directory &amp; Payroll', false);
        $contractsRes->assertDontSee('Team members');
        $contractsRes->assertDontSee('Directory, contracts &amp; payroll, shifts &amp; operations, and HR records.', false);

        // Sub-navigation pills & isolated sub-panes
        $contractsRes->assertSee('📄 Staff Contracts');
        $contractsRes->assertSee('💰 Payroll &amp; Wage Settings', false);
        $contractsRes->assertSee('id="cp-subpane-contracts" class="cp-sub-pane "', false);
        $contractsRes->assertSee('id="cp-subpane-payroll" class="cp-sub-pane d-none"', false);
        $contractsRes->assertSee('+ Create Contract');

        // Explicit ?tab=payroll activates "💰 Payroll & Wage Settings" and hides Contracts sub-pane
        $payrollRes = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'payroll']))
            ->assertOk();

        $payrollRes->assertSee('id="cp-subpane-contracts" class="cp-sub-pane d-none"', false);
        $payrollRes->assertSee('id="cp-subpane-payroll" class="cp-sub-pane "', false);
    }

    public function test_admin_can_create_contracts_across_all_three_templates_and_non_admins_are_blocked(): void
    {
        // 1. Salon Beautician (QAR + single compensation + hygiene/floor clauses)
        $this->actingAs($this->admin)->post(route('staff-contracts.store'), [
            'staff_id' => $this->beautician->id,
            'template_key' => 'salon_beautician',
            'compensation' => 4500,
            'start_date' => '2026-11-15',
            'expiry_date' => '2028-11-14',
            'status' => 'pending_signature',
        ])->assertRedirect(route('staffs.index', ['tab' => 'contracts']));

        $c1 = StaffContract::where('staff_id', $this->beautician->id)->firstOrFail();
        $this->assertSame('QAR', $c1->currency);
        $this->assertSame('Salon Beautician', $c1->role_title);
        $this->assertSame(4500.00, (float) $c1->base_salary);
        $this->assertSame(0.00, (float) $c1->housing_allowance);
        $this->assertSame(0.00, (float) $c1->transport_allowance);
        $this->assertSame(0.00, (float) $c1->other_allowance);
        $this->assertSame(64, strlen($c1->secure_token));
        $this->assertStringContainsString('HYGIENE, SANITIZATION', $c1->clauses_body);

        // 2. Branch Manager (QAR + branch target & supervisor clauses)
        $this->actingAs($this->admin)->post(route('staff-contracts.store'), [
            'staff_id' => $this->branchManagerStaff->id,
            'template_key' => 'branch_manager',
            'compensation' => 7500,
            'status' => 'draft',
        ])->assertRedirect(route('staffs.index', ['tab' => 'contracts']));

        $c2 = StaffContract::where('staff_id', $this->branchManagerStaff->id)->firstOrFail();
        $this->assertSame('QAR', $c2->currency);
        $this->assertSame('draft', $c2->status);
        $this->assertStringContainsString('48-HOUR CLIENT COMPLAINT SLA', $c2->clauses_body);

        // 3. Remote Chat / Creative (PKR + remote SLA clauses)
        $this->actingAs($this->admin)->post(route('staff-contracts.store'), [
            'staff_id' => $this->remoteSpecialist->id,
            'template_key' => 'remote_chat_creative',
            'currency' => 'PKR',
            'compensation' => 145000,
            'status' => 'pending_signature',
        ])->assertRedirect(route('staffs.index', ['tab' => 'contracts']));

        $c3 = StaffContract::where('staff_id', $this->remoteSpecialist->id)->firstOrFail();
        $this->assertSame('PKR', $c3->currency);
        $this->assertSame('remote', $c3->branch);
        $this->assertStringContainsString('REMOTE RESPONSE SLA', $c3->clauses_body);

        // Admin Contracts & Payroll tab renders decluttered 6-column table clearly
        $adminTab = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'contracts']))
            ->assertOk();

        $adminTab->assertSee('Mariam Al-Dosari');
        $adminTab->assertSee('Ahana Kapoor');
        $adminTab->assertSee('Zoya Raza');
        $adminTab->assertSee('PKR 145,000.00');
        $adminTab->assertSee('Designation');
        $adminTab->assertSee('Compensation');
        $adminTab->assertDontSee('No extra allowance');
        $adminTab->assertSee('Open ↗');
        $adminTab->assertSee('Copy Link');
        $adminTab->assertSee('Pending Signature');
        $adminTab->assertSee('Draft');

        // Non-admin manager cannot create contracts AND never receives other staff contracts in HTML
        $this->actingAs($this->manager)->post(route('staff-contracts.store'), [
            'staff_id' => $this->beautician->id,
            'template_key' => 'salon_beautician',
            'compensation' => 4500,
        ])->assertForbidden();

        $managerTab = $this->actingAs($this->manager)
            ->get(route('staffs.index', ['tab' => 'contracts']))
            ->assertOk();

        $managerTab->assertDontSee('PKR 145,000.00');
        $managerTab->assertDontSee($c1->secure_token);
    }

    public function test_outside_shell_hides_salary_and_clauses_until_pin_unlock_and_expires_after_ten_minutes(): void
    {
        $contract = StaffContract::create([
            'staff_id' => $this->beautician->id,
            'template_key' => 'salon_beautician',
            'role_title' => 'Senior Hair Stylist',
            'branch' => 'old_airport',
            'currency' => 'QAR',
            'base_salary' => 4850.00,
            'housing_allowance' => 0.00,
            'transport_allowance' => 0.00,
            'expiry_date' => '2028-06-30',
            'status' => StaffContract::STATUS_PENDING_SIGNATURE,
        ]);

        // 1. Unauthenticated Outside Shell shows Laleen Logo, Name, Designation, Branch, Status, and Unlock Agreement ONLY
        $lockedResponse = $this->get(route('contracts.portal.show', $contract->secure_token))
            ->assertOk();

        $lockedResponse->assertSee('laleen-contract-logo.png');
        $lockedResponse->assertSee('Mariam Al-Dosari');
        $lockedResponse->assertSee('Senior Hair Stylist');
        $lockedResponse->assertSee('Old Airport');
        $lockedResponse->assertSee('Pending Signature');
        $lockedResponse->assertSee('Unlock Agreement');
        $lockedResponse->assertDontSee('4,850.00');
        $lockedResponse->assertDontSee('HYGIENE, SANITIZATION');

        // PDF download and signing are blocked before PIN unlock
        $this->get(route('contracts.portal.pdf', $contract->secure_token))->assertForbidden();
        $this->post(route('contracts.portal.sign', $contract->secure_token), [
            'signed_name' => 'Mariam Al-Dosari',
        ])->assertForbidden();

        // 2. Unlock with default 4-digit PIN (2456)
        $this->post(route('contracts.portal.unlock', $contract->secure_token), [
            'pin' => '2456',
        ])->assertRedirect(route('contracts.portal.show', $contract->secure_token));

        $unlockedResponse = $this->get(route('contracts.portal.show', $contract->secure_token))
            ->assertOk();

        $unlockedResponse->assertSee('laleen-contract-logo.png');
        $unlockedResponse->assertSee('Monthly Compensation');
        $unlockedResponse->assertSee('Branch / Location');
        $unlockedResponse->assertSee('Effective Term');
        $unlockedResponse->assertSee('QAR 4,850.00');
        $unlockedResponse->assertDontSee('Housing Allowance');
        $unlockedResponse->assertDontSee('Transport Allowance');
        $unlockedResponse->assertDontSee('Total Monthly Package');
        $unlockedResponse->assertSee('HYGIENE, SANITIZATION');
        $unlockedResponse->assertSee('Sign &amp; Accept', false);

        // 3. After 11 minutes (> 10-minute TTL), session unlock expires automatically
        $this->travel(11)->minutes();

        $expiredResponse = $this->get(route('contracts.portal.show', $contract->secure_token))
            ->assertOk();

        $expiredResponse->assertDontSee('4,850.00');
        $expiredResponse->assertSee('Enter 4-Digit Security PIN');
        $expiredResponse->assertSee('Unlock Agreement');
    }

    public function test_pin_reset_and_five_failed_attempts_rate_limiting(): void
    {
        // Admin sets a custom 4-digit PIN (8391) for Mariam
        $this->actingAs($this->admin)
            ->patch(route('staff-contracts.update-pin', $this->beautician), [
                'pin' => '8391',
            ])
            ->assertRedirect(route('staffs.index', ['tab' => 'contracts']));

        $contract = StaffContract::create([
            'staff_id' => $this->beautician->id,
            'template_key' => 'salon_beautician',
            'branch' => 'old_airport',
            'currency' => 'QAR',
            'base_salary' => 4500,
            'status' => StaffContract::STATUS_PENDING_SIGNATURE,
        ]);

        // Logout admin to test staff public portal PIN gate
        auth()->logout();

        // Default PIN 2456 + 4 other wrong PINs = 5 failed attempts
        foreach (['2456', '0000', '1111', '2222', '3333'] as $wrongPin) {
            $this->post(route('contracts.portal.unlock', $contract->secure_token), [
                'pin' => $wrongPin,
            ])->assertRedirect(route('contracts.portal.show', $contract->secure_token));
        }

        // 6th attempt is throttled with HTTP 429 even if correct PIN is submitted during lockout
        $this->post(route('contracts.portal.unlock', $contract->secure_token), [
            'pin' => '8391',
        ])->assertStatus(429);

        // After 11 minutes cooldown, custom PIN 8391 succeeds
        $this->travel(11)->minutes();

        $this->post(route('contracts.portal.unlock', $contract->secure_token), [
            'pin' => '8391',
        ])->assertRedirect(route('contracts.portal.show', $contract->secure_token));

        $this->get(route('contracts.portal.show', $contract->secure_token))
            ->assertOk()
            ->assertSee('QAR 4,500.00');
    }

    public function test_signing_permanently_locks_contract_as_active_enforces_isolation_and_generates_laleen_pdf(): void
    {
        $contract = StaffContract::create([
            'staff_id' => $this->beautician->id,
            'template_key' => 'salon_beautician',
            'role_title' => 'Salon Beautician',
            'branch' => 'old_airport',
            'currency' => 'QAR',
            'base_salary' => 4500,
            'housing_allowance' => 0,
            'transport_allowance' => 0,
            'status' => StaffContract::STATUS_PENDING_SIGNATURE,
        ]);

        // 1. Strict isolation: another logged-in staff member cannot view or unlock Mariam's token
        $this->actingAs($this->colleagueUser)
            ->get(route('contracts.portal.show', $contract->secure_token))
            ->assertForbidden();

        // 2. Admin views full contract & downloads PDF freely without PIN gate
        $this->actingAs($this->admin)
            ->get(route('contracts.portal.show', $contract->secure_token))
            ->assertOk()
            ->assertSee('QAR 4,500.00');

        $adminPdf = $this->actingAs($this->admin)
            ->get(route('contracts.portal.pdf', $contract->secure_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $adminPdf->getContent());
        $this->assertStringContainsString('LALEEN BEAUTY SALON', $adminPdf->getContent());
        $this->assertStringContainsString('/ImLogo', $adminPdf->getContent());
        $this->assertStringContainsString('Monthly Compensation', $adminPdf->getContent());
        $this->assertStringNotContainsString('Housing Allowance', $adminPdf->getContent());

        // 3. Unauthenticated staff unlocks via PIN, signs with drawn canvas data URI, and locks contract as Active
        auth()->logout();

        $this->post(route('contracts.portal.unlock', $contract->secure_token), [
            'pin' => '2456',
        ])->assertRedirect();

        $sampleCanvasPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $this->post(route('contracts.portal.sign', $contract->secure_token), [
            'signed_name' => 'Mariam Al-Dosari',
            'signature_type' => 'drawn',
            'signature_data' => $sampleCanvasPng,
        ])->assertRedirect(route('contracts.portal.show', $contract->secure_token));

        $contract->refresh();
        $this->assertSame(StaffContract::STATUS_ACTIVE, $contract->status);
        $this->assertSame('Mariam Al-Dosari', $contract->signed_name);
        $this->assertSame('drawn', $contract->signature_type);
        $this->assertNotNull($contract->signed_at);
        $originalSignedAt = $contract->signed_at->toIso8601String();

        // 4. Signed Active contract is permanently locked: cannot re-sign, revert to draft, or delete
        $this->postJson(route('contracts.portal.sign', $contract->secure_token), [
            'signed_name' => 'Tamper Attempt',
        ])->assertStatus(422);

        $this->actingAs($this->admin)
            ->patchJson(route('staff-contracts.update-status', $contract), [
                'status' => 'draft',
            ])->assertStatus(422);

        $this->actingAs($this->admin)
            ->deleteJson(route('staff-contracts.destroy', $contract))
            ->assertStatus(422);

        $contract->refresh();
        $this->assertSame('Mariam Al-Dosari', $contract->signed_name);
        $this->assertSame($originalSignedAt, $contract->signed_at->toIso8601String());

        // 5. Staff downloads signed Laleen-branded PDF copy
        auth()->logout();
        $this->post(route('contracts.portal.unlock', $contract->secure_token), ['pin' => '2456']);

        $staffPdf = $this->get(route('contracts.portal.pdf', $contract->secure_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $staffPdf->getContent());
        $this->assertStringContainsString('Signed By: Mariam Al-Dosari', $staffPdf->getContent());
    }

    public function test_shifts_and_ops_actions_renders_sub_navigation_pills_and_strictly_isolates_scheduled_shifts_and_ops_tracker(): void
    {
        // 1. Default Shifts & Ops Actions view activates "⏰ Scheduled Shifts" and hides Ops Tracker sub-pane
        $shiftsRes = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'shifts_ops']))
            ->assertOk();

        $shiftsRes->assertSee('⏰ Scheduled Shifts');
        $shiftsRes->assertSee('⚡ Ops &amp; Action Tracker', false);
        $shiftsRes->assertSee('id="so-subpane-shifts" class="so-sub-pane "', false);
        $shiftsRes->assertSee('id="so-subpane-ops_tracker" class="so-sub-pane d-none"', false);
        $shiftsRes->assertSee('Old Airport');
        $shiftsRes->assertSee('Al Wakrah');
        $shiftsRes->assertSee('+ Add Shift Schedule');

        // 2. Explicit ?tab=ops_tracker activates "⚡ Ops & Action Tracker" and hides Scheduled Shifts sub-pane
        $opsRes = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'ops_tracker']))
            ->assertOk();

        $opsRes->assertSee('id="so-subpane-shifts" class="so-sub-pane d-none"', false);
        $opsRes->assertSee('id="so-subpane-ops_tracker" class="so-sub-pane "', false);
        $opsRes->assertSee('+ Add Task');

        // 3. Standalone routes also display the consistent sub-navigation pill bar
        $this->actingAs($this->admin)
            ->get(route('shifts.index'))
            ->assertOk()
            ->assertSee('⏰ Scheduled Shifts')
            ->assertSee('⚡ Ops &amp; Action Tracker', false);

        $this->actingAs($this->admin)
            ->get(route('staff.ops-tracker.index'))
            ->assertOk()
            ->assertSee('⏰ Scheduled Shifts')
            ->assertSee('⚡ Ops &amp; Action Tracker', false);
    }

    public function test_hr_and_disciplinary_renders_sub_navigation_pills_and_strictly_isolates_complaints_and_deductions(): void
    {
        // 1. Default HR & Disciplinary view activates "📋 Complaints & Feedback" and hides Deductions sub-pane
        $complaintsRes = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'complaints']))
            ->assertOk();

        $complaintsRes->assertSee('📋 Complaints &amp; Feedback', false);
        $complaintsRes->assertSee('💸 Deductions &amp; Penalties', false);
        $complaintsRes->assertSee('id="hr-subpane-complaints" class="hr-sub-pane "', false);
        $complaintsRes->assertSee('id="hr-subpane-deductions" class="hr-sub-pane d-none"', false);
        $complaintsRes->assertSee('Log Complaint');

        // 2. Explicit ?tab=deductions activates "💸 Deductions & Penalties" and hides Complaints sub-pane
        $deductionsRes = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'deductions']))
            ->assertOk();

        $deductionsRes->assertSee('id="hr-subpane-complaints" class="hr-sub-pane d-none"', false);
        $deductionsRes->assertSee('id="hr-subpane-deductions" class="hr-sub-pane "', false);
        $deductionsRes->assertSee('Deductions &amp; Penalties', false);
    }

    public function test_custom_contract_categories_single_compensation_branding_logo_and_decluttered_table(): void
    {
        // 1. Verify logo file & base64 data URI exist
        $this->assertFileExists(public_path('images/laleen-contract-logo.png'));
        $logoDataUri = StaffContract::logoDataUri();
        $this->assertNotEmpty($logoDataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $logoDataUri);

        // 2. Admin creates a contract using "+ Add Category" inline builder ("Nail Technician", QAR)
        $this->actingAs($this->admin)->post(route('staff-contracts.store'), [
            'staff_id' => $this->beautician->id,
            'template_key' => StaffContract::NEW_CATEGORY_SENTINEL,
            'custom_category_name' => 'Nail Technician',
            'custom_category_currency' => 'QAR',
            'compensation' => 4200,
            'start_date' => '2026-12-01',
            'expiry_date' => '2028-11-30',
            'status' => 'pending_signature',
        ])->assertRedirect(route('staffs.index', ['tab' => 'contracts']));

        $savedCategory = StaffContractCategory::where('label', 'Nail Technician')->firstOrFail();
        $this->assertSame('QAR', $savedCategory->default_currency);

        $contract = StaffContract::where('staff_id', $this->beautician->id)->firstOrFail();
        $this->assertSame($savedCategory->slug, $contract->template_key);
        // Designation auto-syncs from custom category name when role_title is omitted
        $this->assertSame('Nail Technician', $contract->role_title);
        $this->assertSame('Nail Technician', $contract->designationLabel());
        $this->assertSame(4200.00, $contract->monthlyCompensation());

        // 3. Admin can also save a reusable category directly via JSON endpoint ("Video Editor", PKR)
        $catJson = $this->actingAs($this->admin)->postJson(route('staff-contracts.categories.store'), [
            'label' => 'Video Editor',
            'default_currency' => 'PKR',
        ])->assertCreated();

        $catJson->assertJsonPath('success', true);
        $catJson->assertJsonPath('template.label', 'Video Editor');
        $catJson->assertJsonPath('template.default_currency', 'PKR');

        $allTemplates = StaffContract::allTemplates();
        $this->assertArrayHasKey($savedCategory->slug, $allTemplates);
        $this->assertArrayHasKey('video_editor', $allTemplates);

        // Non-admin cannot create custom categories
        $this->actingAs($this->manager)->postJson(route('staff-contracts.categories.store'), [
            'label' => 'Unauthorized Role',
            'default_currency' => 'QAR',
        ])->assertForbidden();

        // 4. Verify Contracts tab displays custom categories, dark select styling, + Add Category, and compact row actions
        $tabRes = $this->actingAs($this->admin)
            ->get(route('staffs.index', ['tab' => 'contracts']))
            ->assertOk();

        $tabRes->assertSee('+ Add Category');
        $tabRes->assertSee('Nail Technician (QAR/PKR)');
        $tabRes->assertSee('Video Editor (QAR/PKR)');
        $tabRes->assertSee('QAR 4,200.00');
        $tabRes->assertSee('Open ↗');
        $tabRes->assertSee('Copy Link');
        $tabRes->assertSee('Download PDF');
        $tabRes->assertSee('Set / Reset PIN');
        $tabRes->assertDontSee('Set / Reset Staff PIN');
        $tabRes->assertDontSee('Housing Allowance');

        // 5. Verify Official Agreement Web View renders formatted 01..05 clauses, single Designation pill, and Draw|Type toggle
        $portalRes = $this->actingAs($this->admin)
            ->get(route('contracts.portal.show', $contract->secure_token))
            ->assertOk();

        $portalRes->assertSee('laleen-contract-logo.png');
        $portalRes->assertSee('Laleen Beauty Salon &middot; Official Agreement', false);
        $portalRes->assertSee('<div class="lc-clause-num">01</div>', false);
        $portalRes->assertSee('modeDrawBtn', false);
        $portalRes->assertSee('modeTypeBtn', false);

        // 6. Verify PDF Blade template embeds base64 logo & 3-stat strip cleanly
        $renderedPdfHtml = view('contracts.pdf', ['contract' => $contract])->render();
        $this->assertStringContainsString('data:image/png;base64,', $renderedPdfHtml);
        $this->assertStringContainsString('Monthly Compensation', $renderedPdfHtml);
        $this->assertStringContainsString('QAR 4,200.00', $renderedPdfHtml);
        $this->assertStringNotContainsString('Housing Allowance', $renderedPdfHtml);
    }
}
