<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Staff;
use App\Models\User;
use App\Services\ClientImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tempCsvPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCsvPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laleen_client_import_test_' . uniqid() . '.csv';
    }

    protected function tearDown(): void
    {
        if (isset($this->tempCsvPath) && is_file($this->tempCsvPath)) {
            @unlink($this->tempCsvPath);
        }
        parent::tearDown();
    }

    private function writeTempCsv(array $rows, bool $withBom = true): string
    {
        $headers = [
            'phone',
            'phone_local_8',
            'name',
            'branch',
            'branch_source',
            'is_multi_branch',
            'visited_branches',
            'total_bookings',
            'first_booking_date',
            'last_booking_date',
            'last_service',
            'est_booking_spend_qar',
            'data_sources',
            'status',
        ];

        $fh = fopen($this->tempCsvPath, 'wb');
        if ($withBom) {
            fwrite($fh, "\xEF\xBB\xBF");
        }
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $ordered = [];
            foreach ($headers as $col) {
                $ordered[] = $row[$col] ?? '';
            }
            fputcsv($fh, $ordered);
        }
        fclose($fh);

        return $this->tempCsvPath;
    }

    public function test_unified_e164_phone_normalization_and_whatsapp_digits(): void
    {
        $this->assertSame('+97466485627', Customer::normalizePhoneE164('66485627'));
        $this->assertSame('+97466485627', Customer::normalizePhoneE164('066485627'));
        $this->assertSame('+97466523384', Customer::normalizePhoneE164('97466523384'));
        $this->assertSame('+97466523384', Customer::normalizePhoneE164('+974 6652-3384'));
        $this->assertSame('+97466523384', Customer::normalizePhoneE164('0097466523384'));

        $this->assertSame('+918010267487', Customer::normalizePhoneE164('918010267487'));
        $this->assertSame('+918010267487', Customer::normalizePhoneE164('+91 80102 67487'));
        $this->assertSame('+447985461109', Customer::normalizePhoneE164('447985461109'));
        $this->assertSame('+923423670034', Customer::normalizePhoneE164('923423670034'));

        // WhatsApp wa.me digits strip leading '+' while keeping country code
        $this->assertSame('97466485627', Customer::whatsappDigits('+97466485627'));
        $this->assertSame('918010267487', Customer::whatsappDigits('+918010267487'));
    }

    public function test_dry_run_makes_zero_database_writes_and_reports_exact_counts(): void
    {
        // Existing 8-digit Qatar client with placeholder name "," and null branch
        $existing8 = Customer::create([
            'name' => ',',
            'phone' => '66485627',
            'branch' => null,
            'loyalty_points' => 120,
        ]);

        // Existing 11-digit Qatar client without '+' with real name & assigned branch
        $existing11 = Customer::create([
            'name' => 'Mariam Al-Thani',
            'phone' => '97466523384',
            'branch' => 'old_airport',
            'loyalty_points' => 500,
        ]);

        // Existing international client already E.164 with no changes needed
        $existingIntl = Customer::create([
            'name' => 'Priya Sharma',
            'phone' => '+918010267487',
            'branch' => 'wakrah',
            'notes' => '[Legacy Import 2026-11] Bookings: 2',
        ]);

        $csv = $this->writeTempCsv([
            [
                'phone' => '97466485627',
                'phone_local_8' => '66485627',
                'name' => 'Fatima Hassan',
                'branch' => 'Al Wakrah',
                'total_bookings' => '5',
                'last_booking_date' => '2026-10-12',
                'last_service' => 'Keratin',
                'visited_branches' => 'Al Wakrah',
                'status' => 'Active',
            ],
            [
                'phone' => '97466523384',
                'phone_local_8' => '66523384',
                'name' => 'Different CSV Name',
                'branch' => 'Al Wakrah',
                'total_bookings' => '3',
                'last_booking_date' => '2026-09-01',
                'last_service' => 'Blow dry',
                'status' => 'Active',
            ],
            [
                'phone' => '918010267487',
                'name' => 'Priya Sharma',
                'branch' => 'Old Airport',
                'total_bookings' => '2',
                'status' => 'Active',
            ],
            [
                'phone' => '447985461109',
                'name' => 'Sarah UK',
                'branch' => 'Old Airport',
                'total_bookings' => '1',
                'status' => 'Active',
            ],
            [
                'phone' => '97455112233',
                'name' => '',
                'branch' => '',
                'total_bookings' => '0',
                'status' => 'Active',
            ],
        ], withBom: true);

        $service = app(ClientImportService::class);
        $summary = $service->importFromCsv($csv, dryRun: true, normalizeExisting: true);

        $this->assertTrue($summary['dry_run']);
        $this->assertSame(5, $summary['total_csv_rows']);
        $this->assertSame(2, $summary['normalized']); // existing8 + existing11
        $this->assertSame(1, $summary['already_normalized']); // existingIntl
        $this->assertSame(2, $summary['updated']); // existing8 (name+branch+notes) + existing11 (notes)
        $this->assertSame(2, $summary['created']); // Sarah UK + blank-name Qatar client
        $this->assertSame(1, $summary['skipped']); // existingIntl unchanged

        // Assert ZERO database writes occurred
        $this->assertSame(3, Customer::count());
        $this->assertSame('66485627', $existing8->fresh()->phone);
        $this->assertSame(',', $existing8->fresh()->name);
        $this->assertNull($existing8->fresh()->branch);
        $this->assertSame('97466523384', $existing11->fresh()->phone);
        $this->assertNull(Customer::where('phone', '+447985461109')->first());

        // Also test Artisan CLI --dry-run command output & exit code
        $this->artisan('crm:import-clients', [
            'file' => $csv,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY-RUN (0 DB writes)')
            ->expectsOutputToContain('Dry-run complete. Zero database changes were written.')
            ->assertExitCode(0);

        $this->assertSame(3, Customer::count());
    }

    public function test_live_import_normalizes_deduplicates_preserves_ltv_handles_placeholders_and_blank_names(): void
    {
        $staff = Staff::create([
            'name' => 'Hina Stylist',
            'branch' => 'old_airport',
            'is_active' => true,
        ]);

        // 1. Existing 8-digit Qatar client with junk placeholder name "." and live LTV/appointment
        $qatar8 = Customer::create([
            'name' => '.',
            'phone' => '66485627',
            'branch' => null,
            'notes' => 'Prefers organic products.',
            'loyalty_points' => 250,
        ]);

        Appointment::create([
            'customer_id' => $qatar8->id,
            'customer_name' => '.',
            'phone' => '66485627',
            'staff_id' => $staff->id,
            'branch' => 'old_airport',
            'service_name' => 'Hair Spa',
            'appointment_datetime' => now()->subDays(5),
            'status' => 'completed',
            'price' => 350,
        ]);

        Sale::create([
            'customer_id' => $qatar8->id,
            'branch' => 'old_airport',
            'services_total' => 350,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'total_amount' => 350,
            'paid_amount' => 350,
        ]);

        // Reset branch to null after activity hooks to simulate legacy unassigned client before import
        \Illuminate\Support\Facades\DB::table('customers')
            ->where('id', $qatar8->id)
            ->update(['branch' => null]);

        // 2. Existing 11-digit Qatar client (no '+') with REAL name & assigned Old Airport branch
        $qatar11 = Customer::create([
            'name' => 'Noora Al-Kuwari',
            'phone' => '97466523384',
            'branch' => 'old_airport',
            'loyalty_points' => 640,
        ]);

        // 3. Existing international client without '+' and junk placeholder "????"
        $existingIntl = Customer::create([
            'name' => '????',
            'phone' => '918010267487',
            'branch' => null,
            'loyalty_points' => 80,
        ]);

        $csv = $this->writeTempCsv([
            [
                'phone' => '97466485627',
                'phone_local_8' => '66485627',
                'name' => 'Amrita Nair',
                'branch' => 'Al Wakrah',
                'visited_branches' => 'Al Wakrah; Old Airport',
                'total_bookings' => '18',
                'first_booking_date' => '2025-01-10',
                'last_booking_date' => '2026-10-15',
                'last_service' => 'Keratin Treatment',
                'est_booking_spend_qar' => '2450.0',
                'status' => 'Active',
            ],
            [
                'phone' => '97466523384',
                'phone_local_8' => '66523384',
                'name' => 'Should Not Overwrite Real Name',
                'branch' => 'Al Wakrah', // Must NOT overwrite existing old_airport branch
                'visited_branches' => 'Old Airport',
                'total_bookings' => '9',
                'last_booking_date' => '2026-08-20',
                'last_service' => 'Blow dry',
                'status' => 'Active',
            ],
            [
                'phone' => '918010267487',
                'name' => 'Ananya Mehta',
                'branch' => 'Old Airport',
                'total_bookings' => '4',
                'status' => 'Active',
            ],
            [
                'phone' => '923423670034',
                'name' => 'Ayesha Khan',
                'branch' => 'Al Wakrah',
                'total_bookings' => '2',
                'status' => 'Active',
            ],
            [
                'phone' => '97471949259',
                'phone_local_8' => '71949259',
                'name' => '', // Blank CSV name -> clean 'Unknown' fallback
                'branch' => '',
                'total_bookings' => '0',
                'status' => 'Active',
            ],
        ], withBom: true);

        $this->artisan('crm:import-clients', ['file' => $csv])
            ->expectsOutputToContain('LIVE (Committed)')
            ->assertExitCode(0);

        // Total customers = 3 existing (no duplicates!) + 2 net-new = 5
        $this->assertSame(5, Customer::count());

        // Check existing 8-digit Qatar client: normalized to +974, "." replaced by Amrita Nair,
        // branch filled to wakrah, human note preserved + legacy note appended, loyalty & LTV untouched
        $qatar8->refresh();
        $this->assertSame('+97466485627', $qatar8->phone);
        $this->assertSame('Amrita Nair', $qatar8->name);
        $this->assertSame('wakrah', $qatar8->branch);
        $this->assertSame(250, $qatar8->loyalty_points);
        $this->assertStringContainsString('Prefers organic products.', (string) $qatar8->notes);
        $this->assertStringContainsString('[Legacy Import 2026-11]', (string) $qatar8->notes);
        $this->assertStringContainsString('Bookings: 18', (string) $qatar8->notes);
        $this->assertSame(1, $qatar8->appointments()->count());
        $this->assertSame(350.0, (float) $qatar8->sales()->sum('total_amount'));

        // Check existing 11-digit Qatar client: normalized to +974, real name & old_airport branch preserved!
        $qatar11->refresh();
        $this->assertSame('+97466523384', $qatar11->phone);
        $this->assertSame('Noora Al-Kuwari', $qatar11->name);
        $this->assertSame('old_airport', $qatar11->branch);
        $this->assertSame(640, $qatar11->loyalty_points);

        // Check existing international client: stored as +918010267487, "????" replaced by Ananya Mehta
        $existingIntl->refresh();
        $this->assertSame('+918010267487', $existingIntl->phone);
        $this->assertSame('Ananya Mehta', $existingIntl->name);
        $this->assertSame('old_airport', $existingIntl->branch);
        $this->assertSame(80, $existingIntl->loyalty_points);

        // Check net-new international client: stored as +923423670034 with status & import_source
        $newIntl = Customer::where('phone', '+923423670034')->firstOrFail();
        $this->assertSame('Ayesha Khan', $newIntl->name);
        $this->assertSame('wakrah', $newIntl->branch);
        $this->assertSame('active', $newIntl->status);
        $this->assertSame('legacy_excel_2026_11', $newIntl->import_source);

        // Check blank CSV name row: cleanly inserted with 'Unknown' fallback and null branch
        $blankNameClient = Customer::where('phone', '+97471949259')->firstOrFail();
        $this->assertSame('Unknown', $blankNameClient->name);
        $this->assertNull($blankNameClient->branch);
        $this->assertSame('active', $blankNameClient->status);
        $this->assertSame('legacy_excel_2026_11', $blankNameClient->import_source);

        // Re-running import must be 100% idempotent (0 created, 0 updated, 0 normalized, 5 skipped)
        $reRun = app(ClientImportService::class)->importFromCsv($csv, dryRun: false, normalizeExisting: true);
        $this->assertSame(0, $reRun['normalized']);
        $this->assertSame(0, $reRun['created']);
        $this->assertSame(0, $reRun['updated']);
        $this->assertSame(5, $reRun['skipped']);
        $this->assertSame(5, Customer::count());
    }

    public function test_pre_import_normalization_merges_duplicate_phone_collisions_without_losing_appointments_or_ltv(): void
    {
        $staff = Staff::create([
            'name' => 'Sana Stylist',
            'branch' => 'old_airport',
            'is_active' => true,
        ]);

        // Legacy 8-digit record with real name, appointment, sale, and loyalty points
        $legacy8 = Customer::create([
            'name' => 'Aisha Al-Mansoor',
            'phone' => '66485627',
            'branch' => 'old_airport',
            'loyalty_points' => 300,
        ]);

        $appt1 = Appointment::create([
            'customer_id' => $legacy8->id,
            'customer_name' => 'Aisha Al-Mansoor',
            'phone' => '66485627',
            'staff_id' => $staff->id,
            'branch' => 'old_airport',
            'service_name' => 'Balayage',
            'appointment_datetime' => now()->subDays(10),
            'status' => 'completed',
            'price' => 600,
        ]);

        $sale1 = Sale::create([
            'customer_id' => $legacy8->id,
            'branch' => 'old_airport',
            'services_total' => 600,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'total_amount' => 600,
            'paid_amount' => 600,
        ]);

        // Duplicate 11-digit record with another appointment & loyalty points
        $dup11 = Customer::create([
            'name' => '-',
            'phone' => '97466485627',
            'branch' => null,
            'notes' => 'Duplicate entry note',
            'loyalty_points' => 150,
        ]);

        $appt2 = Appointment::create([
            'customer_id' => $dup11->id,
            'customer_name' => '-',
            'phone' => '97466485627',
            'staff_id' => $staff->id,
            'branch' => 'wakrah',
            'service_name' => 'Manicure',
            'appointment_datetime' => now()->subDays(2),
            'status' => 'completed',
            'price' => 120,
        ]);

        $summary = app(ClientImportService::class)->normalizeExistingCustomers(dryRun: false);

        $this->assertSame(1, $summary['collisions_merged']);
        $this->assertCount(1, $summary['collisions']);
        $this->assertSame(1, Customer::count());

        $merged = Customer::firstOrFail();
        $this->assertSame('+97466485627', $merged->phone);
        $this->assertSame('Aisha Al-Mansoor', $merged->name);
        $this->assertSame('old_airport', $merged->branch);
        $this->assertSame(450, $merged->loyalty_points); // 300 + 150 preserved!
        $this->assertStringContainsString('Duplicate entry note', (string) $merged->notes);

        // Both appointments and sale now belong to the single merged E.164 customer
        $this->assertSame($merged->id, $appt1->fresh()->customer_id);
        $this->assertSame($merged->id, $appt2->fresh()->customer_id);
        $this->assertSame($merged->id, $sale1->fresh()->customer_id);
    }

    public function test_crm_search_lookup_and_whatsapp_links_work_cleanly_with_e164_plus_phones(): void
    {
        $admin = User::create([
            'name' => 'Afnan Owner',
            'email' => 'afnan.import.test@laleen.qa',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $qatarClient = Customer::create([
            'name' => 'Amrita Doha',
            'phone' => '+97466485627',
            'branch' => 'old_airport',
        ]);

        $intlClient = Customer::create([
            'name' => 'Priya Mumbai',
            'phone' => '+918010267487',
            'branch' => 'wakrah',
        ]);

        // 1. Clients index search works for 8-digit local, 11-digit, and +974 formatted queries
        foreach (['66485627', '97466485627', '+97466485627', '+974 6648 5627'] as $query) {
            $resp = $this->actingAs($admin)
                ->get(route('customers.index', ['search' => $query]))
                ->assertOk();
            $resp->assertSee('Amrita Doha');
            $resp->assertSee('+97466485627');
            $resp->assertDontSee('Priya Mumbai');
        }

        // 2. Booking drawer live search & phone lookup work with both local and +E.164 queries
        $this->actingAs($admin)
            ->getJson(route('customers.search', ['q' => '66485627']))
            ->assertOk()
            ->assertJsonFragment(['id' => $qatarClient->id, 'phone' => '+97466485627']);

        $this->actingAs($admin)
            ->getJson(route('customers.lookup', ['phone' => '66485627']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('id', $qatarClient->id)
            ->assertJsonPath('phone', '+97466485627');

        $this->actingAs($admin)
            ->getJson(route('customers.lookup', ['phone' => '918010267487']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('id', $intlClient->id)
            ->assertJsonPath('phone', '+918010267487');

        // 3. Index viewData wa_phone strips leading '+' cleanly for wa.me/ links
        $indexResp = $this->actingAs($admin)
            ->get(route('customers.index'))
            ->assertOk();

        $tiers = $indexResp->viewData('customerTiers');
        $this->assertSame('97466485627', $tiers[$qatarClient->id]['wa_phone']);
        $this->assertSame('918010267487', $tiers[$intlClient->id]['wa_phone']);
    }
}
