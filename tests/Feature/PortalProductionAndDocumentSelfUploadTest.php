<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\User;
use App\Support\StaffServiceProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalProductionAndDocumentSelfUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $ahanaUser;
    private Staff $ahanaStaff;
    private User $aishaUser;
    private Staff $aishaStaff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-25 14:00:00', 'Asia/Qatar'));
        Storage::fake('local');

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'admin.prod@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->ahanaUser = User::factory()->create([
            'name' => 'Ahana Sharma',
            'email' => 'ahana.prod@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
            'is_active' => true,
        ]);
        $this->ahanaStaff = Staff::create([
            'name' => 'Ahana Sharma',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $this->ahanaUser->id,
        ]);

        $this->aishaUser = User::factory()->create([
            'name' => 'Aisha Specialist',
            'email' => 'aisha.prod@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $this->aishaStaff = Staff::create([
            'name' => 'Aisha Specialist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $this->aishaUser->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mtd_service_production_math_prorates_combos_discounts_freebies_and_excludes_cancelled_or_remote(): void
    {
        $client1 = Customer::create(['name' => 'Fatima Client', 'phone' => '+97455110001', 'branch' => 'old_airport']);
        $client2 = Customer::create(['name' => 'Noor Client', 'phone' => '+97455110002', 'branch' => 'old_airport']);
        $client3 = Customer::create(['name' => 'Mariam Client', 'phone' => '+97455110003', 'branch' => 'old_airport']);

        $hairColor = Service::create(['name' => 'Hair Color', 'price' => 250, 'duration' => 90]);
        $keratin = Service::create(['name' => 'Keratin Treatment', 'price' => 300, 'duration' => 120]);
        $blowdry = Service::create(['name' => 'Signature Blowdry', 'price' => 100, 'duration' => 45]);
        $facial = Service::create(['name' => 'Glow Facial', 'price' => 150, 'duration' => 60]);
        $eyebrow = Service::create(['name' => 'Eyebrow Threading', 'price' => 30, 'duration' => 15]);
        $combo = Combo::create(['name' => 'Keratin + Blowdry Combo', 'price' => 300, 'status' => 'active']);
        $combo->services()->sync([$keratin->id, $blowdry->id]);

        // 1. Completed standalone DISCOUNTED service for Aisha: 250 - 50 = 200 QAR
        $appt1 = Appointment::create([
            'customer_id' => $client1->id,
            'customer_name' => $client1->name,
            'phone' => $client1->phone,
            'service_name' => $hairColor->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-10',
            'start_time' => '11:00:00',
            'appointment_datetime' => '2026-11-10 11:00:00',
            'staff_id' => $this->aishaStaff->id,
            'status' => 'completed',
            'price' => 200,
            'original_price' => 250,
            'discount_amount' => 50,
            'paid_at' => Carbon::parse('2026-11-10 12:30:00', 'Asia/Qatar'),
        ]);
        AppointmentService::create([
            'appointment_id' => $appt1->id,
            'service_id' => $hairColor->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $hairColor->name,
            'price' => 200,
            'original_price' => 250,
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'duration' => 90,
            'is_pending' => false,
        ]);

        // 2. Completed 2-service Combo (paid 300 QAR) SPLIT between Aisha (Keratin 300 catalog -> 225 QAR) & Ahana (Blowdry 100 catalog -> 75 QAR)
        $appt2 = Appointment::create([
            'customer_id' => $client2->id,
            'customer_name' => $client2->name,
            'phone' => $client2->phone,
            'service_name' => $keratin->name . ', ' . $blowdry->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-18',
            'start_time' => '13:00:00',
            'appointment_datetime' => '2026-11-18 13:00:00',
            'staff_id' => $this->aishaStaff->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'status' => 'completed',
            'price' => 300,
            'paid_at' => Carbon::parse('2026-11-18 15:45:00', 'Asia/Qatar'),
        ]);
        $sale2 = Sale::create([
            'appointment_id' => $appt2->id,
            'customer_id' => $client2->id,
            'staff_id' => $this->aishaStaff->id,
            'created_by' => $this->admin->id,
            'branch' => 'old_airport',
            'services_total' => 0,
            'products_total' => 0,
            'packages_total' => 300,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 300,
        ]);
        $pkg2 = ClientPackage::create([
            'customer_id' => $client2->id,
            'combo_id' => $combo->id,
            'sale_id' => $sale2->id,
            'appointment_id' => $appt2->id,
            'combo_name' => $combo->name,
            'price_paid' => 300,
            'original_combo_price' => 400,
            'quantity_included' => 2,
            'validity_days' => 7,
            'status' => 'completed',
        ]);
        $lineKeratin = AppointmentService::create([
            'appointment_id' => $appt2->id,
            'service_id' => $keratin->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'client_package_id' => $pkg2->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $keratin->name,
            'price' => 0,
            'original_price' => 300,
            'duration' => 120,
            'is_pending' => false,
        ]);
        $lineBlowdry = AppointmentService::create([
            'appointment_id' => $appt2->id,
            'service_id' => $blowdry->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'client_package_id' => $pkg2->id,
            'staff_id' => $this->ahanaStaff->id,
            'name' => $blowdry->name,
            'price' => 0,
            'original_price' => 100,
            'duration' => 45,
            'is_pending' => false,
        ]);
        ClientPackageService::create([
            'client_package_id' => $pkg2->id,
            'service_id' => $keratin->id,
            'service_name' => $keratin->name,
            'status' => 'redeemed',
            'appointment_service_id' => $lineKeratin->id,
            'sale_id' => $sale2->id,
        ]);
        ClientPackageService::create([
            'client_package_id' => $pkg2->id,
            'service_id' => $blowdry->id,
            'service_name' => $blowdry->name,
            'status' => 'redeemed',
            'appointment_service_id' => $lineBlowdry->id,
            'sale_id' => $sale2->id,
        ]);

        // 3. Completed Service + Freebies for Aisha: Anchor 150 QAR + Freebie 0 QAR (still counted in performed services!)
        $appt3 = Appointment::create([
            'customer_id' => $client3->id,
            'customer_name' => $client3->name,
            'phone' => $client3->phone,
            'service_name' => $facial->name . ', ' . $eyebrow->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-22',
            'start_time' => '15:00:00',
            'appointment_datetime' => '2026-11-22 15:00:00',
            'staff_id' => $this->aishaStaff->id,
            'status' => 'completed',
            'price' => 150,
            'paid_at' => Carbon::parse('2026-11-22 16:15:00', 'Asia/Qatar'),
        ]);
        AppointmentService::create([
            'appointment_id' => $appt3->id,
            'service_id' => $facial->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $facial->name,
            'price' => 150,
            'original_price' => 150,
            'duration' => 60,
            'is_pending' => false,
        ]);
        AppointmentService::create([
            'appointment_id' => $appt3->id,
            'service_id' => $eyebrow->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $eyebrow->name,
            'price' => 0,
            'original_price' => 30,
            'is_freebie_redemption' => true,
            'duration' => 15,
            'is_pending' => false,
        ]);

        // 4. EXCLUDED bookings: Cancelled, No-Show, Uncompleted Future, and Previous-Month Completed
        foreach ([
            ['date' => '2026-11-19', 'status' => 'cancelled', 'paid_at' => null],
            ['date' => '2026-11-20', 'status' => 'no_show', 'paid_at' => null],
            ['date' => '2026-11-28', 'status' => 'scheduled', 'paid_at' => null],
            ['date' => '2026-10-29', 'status' => 'completed', 'paid_at' => Carbon::parse('2026-10-29 14:00:00', 'Asia/Qatar')],
        ] as $ex) {
            $exAppt = Appointment::create([
                'customer_id' => $client1->id,
                'customer_name' => $client1->name,
                'phone' => $client1->phone,
                'service_name' => $hairColor->name,
                'branch' => 'old_airport',
                'booking_date' => $ex['date'],
                'start_time' => '10:00:00',
                'appointment_datetime' => $ex['date'] . ' 10:00:00',
                'staff_id' => $this->aishaStaff->id,
                'status' => $ex['status'],
                'price' => 500,
                'paid_at' => $ex['paid_at'],
            ]);
            AppointmentService::create([
                'appointment_id' => $exAppt->id,
                'service_id' => $hairColor->id,
                'staff_id' => $this->aishaStaff->id,
                'name' => $hairColor->name,
                'price' => 500,
                'original_price' => 500,
                'duration' => 60,
                'is_pending' => false,
            ]);
        }

        // Verify exact totals via Portal Performance tab for Salon Staff (Aisha) & Salon Manager (Ahana)
        $aishaPerf = $this->actingAs($this->aishaUser)->get('/my-profile?tab=performance')->assertOk();
        $aishaProd = $aishaPerf->viewData('mtdProduction');
        $this->assertSame(4, $aishaProd['total_services']);
        $this->assertSame(3, $aishaProd['standalone_count']); // Hair Color + Facial anchor + Eyebrow freebie
        $this->assertSame(1, $aishaProd['combo_count']);      // Keratin from combo
        $this->assertSame(3, $aishaProd['clients_served']);
        $this->assertEquals(575.00, $aishaProd['total_revenue_qar']); // 200 + 225 + 150 + 0
        $aishaPerf->assertSee('MTD Service Production');
        $aishaPerf->assertSee('Services Performed (MTD)');
        $aishaPerf->assertSee('575 QAR');
        $aishaPerf->assertSee('3 standalone · 1 combo · 3 clients served');

        $ahanaPerf = $this->actingAs($this->ahanaUser)->get('/my-profile?tab=performance')->assertOk();
        $ahanaProd = $ahanaPerf->viewData('mtdProduction');
        $this->assertSame(1, $ahanaProd['total_services']);
        $this->assertSame(0, $ahanaProd['standalone_count']);
        $this->assertSame(1, $ahanaProd['combo_count']); // Blowdry from combo
        $this->assertSame(1, $ahanaProd['clients_served']);
        $this->assertEquals(75.00, $ahanaProd['total_revenue_qar']); // 75 QAR share of 300 QAR combo
        $ahanaPerf->assertSee('MTD Service Production');
        $ahanaPerf->assertSee('75 QAR');
        $ahanaPerf->assertSee('0 standalone · 1 combo · 1 client served');

        // Zero double-counting invariant: sum of combo shares across both specialists === 300.00 QAR
        $this->assertEquals(300.00, round(225.00 + $ahanaProd['total_revenue_qar'], 2));

        // Equal-split fallback when catalog weights sum to 0
        $this->assertSame([100.0, 100.0], StaffServiceProductionService::prorateAmount(200.0, [0.0, 0.0]));

        // Remote roles NEVER see MTD Service Production
        foreach (['agent', 'sales_coordinator', 'ops_lead', 'marketing_specialist', 'social_media_editor'] as $remoteRole) {
            $ru = User::factory()->create([
                'name' => 'Remote ' . $remoteRole,
                'email' => $remoteRole . '.noprod@laleen.qa',
                'role' => $remoteRole,
                'permissions' => User::defaultPermissionsForRole($remoteRole),
            ]);
            Staff::create([
                'name' => $ru->name,
                'branch' => null,
                'is_remote' => true,
                'bookable' => false,
                'user_id' => $ru->id,
            ]);
            $remotePerf = $this->actingAs($ru)->get('/my-profile?tab=performance')->assertOk();
            $this->assertNull($remotePerf->viewData('mtdProduction'));
            $remotePerf->assertDontSee('MTD Service Production');
        }
    }

    public function test_staff_self_upload_category_restrictions_pending_delete_admin_verify_and_cross_staff_403(): void
    {
        // 1. Ahana (Salon Manager) uploads her own QID & Passport (forged staff_id MUST be ignored!)
        $qidRes = $this->actingAs($this->ahanaUser)->postJson(route('portal.documents.store'), [
            'staff_id' => $this->aishaStaff->id, // tampered target — MUST be ignored!
            'category' => 'qid',
            'title' => 'Ahana Qatar ID 2026',
            'document_number' => '29988776655',
            'issue_date' => '2025-01-01',
            'expiry_date' => '2028-01-01',
            'file' => UploadedFile::fake()->create('ahana-qid.pdf', 120, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('document.verification_status', StaffDocument::STATUS_PENDING_REVIEW)
            ->assertJsonPath('document.staff_id', $this->ahanaStaff->id);

        $qidDoc = StaffDocument::findOrFail($qidRes->json('document.id'));
        $this->assertSame($this->ahanaStaff->id, (int) $qidDoc->staff_id);
        $this->assertSame(StaffDocument::STATUS_PENDING_REVIEW, $qidDoc->verification_status);
        $this->assertStringStartsWith('private/staff-documents/' . $this->ahanaStaff->id . '/', $qidDoc->file_path);
        Storage::disk('local')->assertExists($qidDoc->file_path);

        $passRes = $this->actingAs($this->ahanaUser)->postJson(route('portal.documents.store'), [
            'category' => 'passport',
            'title' => 'Ahana Passport Copy',
            'file' => UploadedFile::fake()->image('passport.jpg'),
        ])->assertCreated()
            ->assertJsonPath('document.verification_status', StaffDocument::STATUS_PENDING_REVIEW);
        $passDoc = StaffDocument::findOrFail($passRes->json('document.id'));

        // 2. Staff attempting to upload HR-only categories (`signed_contract`, `offer_letter`) -> strictly rejected (422)!
        foreach (['signed_contract', 'offer_letter', 'other'] as $blockedCat) {
            $this->actingAs($this->ahanaUser)->postJson(route('portal.documents.store'), [
                'category' => $blockedCat,
                'title' => 'Attempted HR Upload',
                'file' => UploadedFile::fake()->create('hr.pdf', 50, 'application/pdf'),
            ])->assertStatus(422)->assertJsonValidationErrors(['category']);
        }

        // 3. Salon Staff (Aisha) can ALSO self-upload her own personal ID (`national_id`), and delete while pending_review
        $aishaUpload = $this->actingAs($this->aishaUser)->postJson(route('portal.documents.store'), [
            'category' => 'national_id',
            'title' => 'Aisha Blurry ID',
            'file' => UploadedFile::fake()->create('blurry.pdf', 50, 'application/pdf'),
        ])->assertCreated();
        $aishaPendingDoc = StaffDocument::findOrFail($aishaUpload->json('document.id'));

        $this->actingAs($this->aishaUser)->deleteJson(route('portal.documents.destroy', $aishaPendingDoc))
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('staff_documents', ['id' => $aishaPendingDoc->id]);
        Storage::disk('local')->assertMissing($aishaPendingDoc->file_path);

        // 4. Cross-staff isolation: Aisha gets HTTP 403 trying to download, verify, or delete Ahana's QID!
        $this->actingAs($this->aishaUser)->get(route('staff-documents.download', $qidDoc))->assertForbidden();
        $this->actingAs($this->aishaUser)->deleteJson(route('portal.documents.destroy', $qidDoc))->assertForbidden();
        $this->actingAs($this->aishaUser)->postJson(route('staff-documents.verify', $qidDoc))->assertForbidden();
        $this->actingAs($this->ahanaUser)->postJson(route('staff-documents.verify', $qidDoc))->assertForbidden();

        // 5. Ahana sees Pending HR Review + Upload Document CTA (no "Read-only" text!)
        $ahanaDocsPage = $this->actingAs($this->ahanaUser)->get('/my-profile?tab=documents')->assertOk();
        $ahanaDocsPage->assertSee('Ahana Qatar ID 2026');
        $ahanaDocsPage->assertSee('Pending HR Review');
        $ahanaDocsPage->assertSee('Upload Document');
        $ahanaDocsPage->assertDontSee('Private HR Vault &middot; Read-only', false);

        // 6. Admin Preview shows Upload Document + Verify button; Admin verifies Ahana's QID
        $adminPreviewDocs = $this->actingAs($this->admin)
            ->get(route('portal.admin-preview', ['staff' => $this->ahanaStaff->id, 'tab' => 'documents']))
            ->assertOk();
        $adminPreviewDocs->assertSee('Upload Document');
        $adminPreviewDocs->assertSee('Pending HR Review');
        $adminPreviewDocs->assertSee('Verify');

        $this->actingAs($this->admin)->postJson(route('staff-documents.verify', $qidDoc))
            ->assertOk()
            ->assertJsonPath('document.verification_status', StaffDocument::STATUS_VERIFIED);

        $qidDoc->refresh();
        $this->assertTrue($qidDoc->isVerified());
        $this->assertSame($this->admin->id, (int) $qidDoc->verified_by);
        $this->assertNotNull($qidDoc->verified_at);

        // Once VERIFIED, Ahana can NO LONGER delete her own QID (HTTP 403!)
        $this->actingAs($this->ahanaUser)->deleteJson(route('portal.documents.destroy', $qidDoc))->assertForbidden();
        $this->assertDatabaseHas('staff_documents', ['id' => $qidDoc->id]);

        // 7. Admin inline upload (including HR-only `signed_contract`) saves directly as Verified
        $hrUpload = $this->actingAs($this->admin)->postJson(route('staff-documents.store', $this->ahanaStaff), [
            'category' => 'signed_contract',
            'title' => '2026 Signed Manager Contract',
            'file' => UploadedFile::fake()->create('signed-contract.pdf', 120, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('document.verification_status', StaffDocument::STATUS_VERIFIED);
        $hrDoc = StaffDocument::findOrFail($hrUpload->json('document.id'));
        $this->assertTrue($hrDoc->isVerified());
        // Staff cannot delete HR-uploaded documents (HTTP 403)
        $this->actingAs($this->ahanaUser)->deleteJson(route('portal.documents.destroy', $hrDoc))->assertForbidden();
        // Owner CAN still download her verified QID and HR-uploaded contract
        $this->actingAs($this->ahanaUser)->get(route('staff-documents.download', $qidDoc))->assertOk();
        $this->actingAs($this->ahanaUser)->get(route('staff-documents.download', $hrDoc))->assertOk();

        // Directory & Profiles JSON index returns verification status + verify_url for pending Passport
        $this->actingAs($this->admin)->getJson(route('staff-documents.index', $this->ahanaStaff))
            ->assertOk()
            ->assertJsonFragment(['id' => $passDoc->id, 'verification_status' => StaffDocument::STATUS_PENDING_REVIEW]);
    }
}
