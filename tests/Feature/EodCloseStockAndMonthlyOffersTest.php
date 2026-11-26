<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\DailyBranchClose;
use App\Models\MonthlyOffer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockWeek;
use App\Models\StockWeekEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EodCloseStockAndMonthlyOffersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $oldAirportManager;
    private User $wakrahManager;
    private User $opsLead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->oldAirportManager = $this->makeBranchManager('Ahana Old Airport', 'ahana.eod@laleen.qa', 'old_airport');
        $this->wakrahManager = $this->makeBranchManager('Rhea Al Wakrah', 'rhea.eod@laleen.qa', 'wakrah');

        $this->opsLead = User::factory()->create([
            'name' => 'Nadia Ops Lead',
            'email' => 'ops.stock@laleen.qa',
            'role' => 'ops_lead',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('ops_lead'),
        ]);
    }

    private function makeBranchManager(string $name, string $email, string $staffBranch): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => 'salon_manager',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
        ]);

        Staff::create([
            'name' => $name,
            'email' => $email,
            'branch' => $staffBranch,
            'role' => 'Salon Manager',
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    private function createSaleWithPayments(string $salesBranch, Carbon $when, array $payments, array $productItems = []): Sale
    {
        $total = array_sum($payments);
        $sale = Sale::create([
            'branch' => $salesBranch,
            'services_total' => empty($productItems) ? $total : 0,
            'products_total' => empty($productItems) ? 0 : $total,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => $total,
            'created_by' => $this->admin->id,
        ]);
        $sale->timestamps = false;
        $sale->created_at = $when;
        $sale->updated_at = $when;
        $sale->save();

        foreach ($payments as $method => $amount) {
            SalePayment::create([
                'sale_id' => $sale->id,
                'method' => $method,
                'amount' => $amount,
            ]);
        }

        foreach ($productItems as $pi) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'type' => 'product',
                'product_id' => $pi['product_id'],
                'name' => $pi['name'],
                'quantity' => $pi['quantity'],
                'price' => $pi['unit_price'],
                'total' => $pi['unit_price'] * $pi['quantity'],
            ]);
        }

        return $sale;
    }

    /* =========================================================================
     * UPGRADE 1 — FINANCE -> SALES LEDGER: BLIND END-OF-DAY (EOD) CLOSE
     * ========================================================================= */

    public function test_blind_eod_close_hides_totals_pre_submit_requires_z_report_and_blocks_cross_branch(): void
    {
        Storage::fake('local');
        $today = Carbon::now('Asia/Qatar')->setTime(14, 0);
        $todayStr = $today->toDateString();

        $this->createSaleWithPayments('old_airport', $today, [
            'cash' => 320.00,
            'card' => 480.00,
            'online_transfer' => 150.00,
        ]);
        $this->createSaleWithPayments('wakrah', $today, [
            'cash' => 210.00,
            'card' => 90.00,
        ]);

        // Salon Manager without finance permission still accesses blind EOD Sales Ledger for own branch only
        $resp = $this->actingAs($this->oldAirportManager)
            ->get(route('appointments.revenue.index', ['eod_date' => $todayStr]))
            ->assertOk();

        $resp->assertSee('End-of-Day Cash &amp; POS Close', false);
        $resp->assertSee('Payment totals hidden until End-of-Day Close is submitted');
        $resp->assertDontSee('Admin Live Reference');
        $resp->assertDontSee('Cash: 320.00 QAR');
        $resp->assertDontSee('Card: 480.00 QAR');
        $resp->assertDontSee('Total Expenses');

        // Cross-branch submit is strictly 403
        $this->actingAs($this->oldAirportManager)
            ->post(route('finance.eod-close.store'), [
                'branch' => 'al_wakrah',
                'close_date' => $todayStr,
                'opening_float_qar' => 0,
                'entered_cash_qar' => 210,
                'entered_card_qar' => 90,
                'entered_transfer_qar' => 0,
                'z_report' => UploadedFile::fake()->image('z-wakrah.jpg'),
            ])
            ->assertForbidden();

        // Missing Z-Report when card > 0 returns 422 validation error
        $this->actingAs($this->oldAirportManager)
            ->postJson(route('finance.eod-close.store'), [
                'branch' => 'old_airport',
                'close_date' => $todayStr,
                'opening_float_qar' => 100,
                'entered_cash_qar' => 420,
                'entered_card_qar' => 480,
                'entered_transfer_qar' => 150,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['z_report']);
    }

    public function test_blind_eod_close_reconciles_balanced_shortage_overage_locks_and_secures_z_report(): void
    {
        Storage::fake('local');
        $today = Carbon::now('Asia/Qatar')->setTime(15, 30);
        $todayStr = $today->toDateString();

        $this->createSaleWithPayments('old_airport', $today, [
            'cash' => 300.00,
            'card' => 450.00,
            'online_transfer' => 150.00,
        ]);

        // 1. Balanced submit (Opening Float 200 + Expected Cash 300 = 500 physical cash)
        $this->actingAs($this->oldAirportManager)
            ->post(route('finance.eod-close.store'), [
                'branch' => 'old_airport',
                'close_date' => $todayStr,
                'opening_float_qar' => 200,
                'entered_cash_qar' => 500,
                'entered_card_qar' => 450,
                'entered_transfer_qar' => 150,
                'variance_note' => 'All matched cleanly',
                'z_report' => UploadedFile::fake()->create('z-batch.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $close = DailyBranchClose::where('branch', 'old_airport')->whereDate('close_date', $todayStr)->firstOrFail();
        $this->assertSame(DailyBranchClose::STATUS_BALANCED, $close->status);
        $this->assertEquals(0.0, $close->cash_variance_qar);
        $this->assertEquals(0.0, $close->card_variance_qar);
        $this->assertEquals(0.0, $close->transfer_variance_qar);
        $this->assertEquals(0.0, $close->total_variance_qar);
        $this->assertTrue($close->is_locked);
        $this->assertNotNull($close->z_report_path);
        Storage::disk('local')->assertExists($close->z_report_path);

        // Post-submit reveals reconciliation table to Old Airport Manager
        $this->actingAs($this->oldAirportManager)
            ->get(route('appointments.revenue.index', ['tab' => 'sales-ledger', 'eod_date' => $todayStr]))
            ->assertOk()
            ->assertSee('Balanced')
            ->assertSee('Total Daily Close')
            ->assertSee('900.00');

        // 2. Private Z-Report download policy check
        $this->actingAs($this->admin)->get(route('finance.eod-close.z-report', $close))->assertOk();
        $this->actingAs($this->oldAirportManager)->get(route('finance.eod-close.z-report', $close))->assertOk();
        $this->actingAs($this->wakrahManager)->get(route('finance.eod-close.z-report', $close))->assertForbidden();
        $this->actingAs($this->opsLead)->get(route('finance.eod-close.z-report', $close))->assertForbidden();

        // 3. Locked day rejects Manager edit/resubmit (403) and Manager cannot self-verify or unlock (403)
        $this->actingAs($this->oldAirportManager)
            ->post(route('finance.eod-close.store'), [
                'branch' => 'old_airport',
                'close_date' => $todayStr,
                'opening_float_qar' => 200,
                'entered_cash_qar' => 480,
                'entered_card_qar' => 450,
                'entered_transfer_qar' => 150,
            ])
            ->assertForbidden();

        $this->actingAs($this->oldAirportManager)->post(route('finance.eod-close.verify', $close))->assertForbidden();
        $this->actingAs($this->oldAirportManager)->post(route('finance.eod-close.unlock', $close))->assertForbidden();

        // 4. Admin verifies, then unlocks so Manager can resubmit with a Shortage (-25.00 QAR)
        $this->actingAs($this->admin)->post(route('finance.eod-close.verify', $close))->assertRedirect();
        $close->refresh();
        $this->assertTrue($close->isVerified());
        $this->assertSame($this->admin->id, $close->verified_by);

        $this->actingAs($this->admin)->post(route('finance.eod-close.unlock', $close))->assertRedirect();
        $close->refresh();
        $this->assertFalse($close->is_locked);
        $this->assertFalse($close->isVerified());

        $this->actingAs($this->oldAirportManager)
            ->post(route('finance.eod-close.store'), [
                'branch' => 'old_airport',
                'close_date' => $todayStr,
                'opening_float_qar' => 200,
                'entered_cash_qar' => 475,
                'entered_card_qar' => 450,
                'entered_transfer_qar' => 150,
                'variance_note' => '25 QAR petty taxi receipt missing',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $close->refresh();
        $this->assertSame(DailyBranchClose::STATUS_SHORTAGE, $close->status);
        $this->assertEquals(-25.0, $close->cash_variance_qar);
        $this->assertEquals(-25.0, $close->total_variance_qar);
        $this->assertTrue($close->is_locked);

        // 5. Admin overrides with Overage (+15.00 QAR)
        $this->actingAs($this->admin)
            ->post(route('finance.eod-close.store'), [
                'branch' => 'old_airport',
                'close_date' => $todayStr,
                'opening_float_qar' => 200,
                'entered_cash_qar' => 515,
                'entered_card_qar' => 450,
                'entered_transfer_qar' => 150,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $close->refresh();
        $this->assertSame(DailyBranchClose::STATUS_OVERAGE, $close->status);
        $this->assertEquals(15.0, $close->total_variance_qar);
    }

    /* =========================================================================
     * UPGRADE 2 — DEDICATED STOCK MODULE (SATURDAY–FRIDAY WEEKLY AUDIT)
     * ========================================================================= */

    public function test_stock_module_auto_syncs_retail_products_on_create_update_and_delete(): void
    {
        $product = Product::create([
            'name' => 'Laleen Argan Hair Serum 100ml',
            'sku' => 'LAL-ARG-100',
            'price' => 145.00,
            'stock_quantity' => 12,
            'low_stock_threshold' => 4,
        ]);

        $stockItem = StockItem::where('product_id', $product->id)->first();
        $this->assertNotNull($stockItem);
        $this->assertSame(StockItem::TYPE_RETAIL, $stockItem->item_type);
        $this->assertSame('Laleen Argan Hair Serum 100ml', $stockItem->name);
        $this->assertEquals(4.0, $stockItem->par_level_old_airport);
        $this->assertTrue($stockItem->is_active);

        $product->update(['name' => 'Laleen Gold Argan Hair Serum 100ml']);
        $stockItem->refresh();
        $this->assertSame('Laleen Gold Argan Hair Serum 100ml', $stockItem->name);

        $product->delete();
        $stockItem->refresh();
        $this->assertFalse($stockItem->is_active);
    }

    public function test_stock_weekly_sat_to_fri_audit_math_decimals_transfers_revision_and_carry_forward(): void
    {
        // Verify Sat -> Fri week bounds in Asia/Qatar (Wednesday 2026-11-25 belongs to Sat 2026-11-21 -> Fri 2026-11-27)
        $bounds = StockWeek::weekBoundsFor('2026-11-25');
        $this->assertSame('2026-11-21', $bounds['start_str']);
        $this->assertSame('2026-11-27', $bounds['end_str']);
        $this->assertSame(Carbon::SATURDAY, $bounds['start']->dayOfWeek);
        $this->assertSame(Carbon::FRIDAY, $bounds['end']->dayOfWeek);

        // Admin adds a Backbar item & a Retail product
        $this->actingAs($this->admin)->post(route('stock.items.store'), [
            'name' => 'Majirel 7.1 Hair Color Tube',
            'category' => 'hair_color',
            'unit' => 'Tube',
            'par_level_old_airport' => 6,
            'par_level_al_wakrah' => 4,
        ])->assertRedirect(route('stock.index', ['tab' => 'catalog']));

        $backbar = StockItem::where('name', 'Majirel 7.1 Hair Color Tube')->firstOrFail();

        $retailProduct = Product::create([
            'name' => 'Keratin Home Mask',
            'sku' => 'KER-01',
            'price' => 160,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
        ]);
        $retailItem = StockItem::where('product_id', $retailProduct->id)->firstOrFail();

        // Seed 2 units of Retail product sold on Old Airport during the Sat-Fri window
        $saleTime = Carbon::parse('2026-11-24 13:00:00', 'Asia/Qatar');
        $this->createSaleWithPayments('old_airport', $saleTime, ['card' => 320], [
            [
                'product_id' => $retailProduct->id,
                'name' => $retailProduct->name,
                'quantity' => 2,
                'unit_price' => 160,
            ],
        ]);

        // Load Old Airport's week so entries auto-populate (including retail_sold_qty = 2)
        $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk()
            ->assertSee('Majirel 7.1 Hair Color Tube')
            ->assertSee('Keratin Home Mask');

        $oaWeek = StockWeek::where('branch', 'old_airport')->whereDate('week_start_date', $bounds['start_str'])->firstOrFail();

        // 1. Fresh-start Week 1: Manager enters initial Opening (Sat) = 10 backbar, 6 retail
        $this->actingAs($this->oldAirportManager)
            ->post(route('stock.weeks.save-counts', $oaWeek), [
                'entries' => [
                    $backbar->id => ['opening_qty' => 10, 'closing_qty' => null],
                    $retailItem->id => ['opening_qty' => 6, 'closing_qty' => null],
                ],
            ])
            ->assertRedirect();

        // 2. Ops Lead logs +2.5 Received delivery for Old Airport
        $this->actingAs($this->opsLead)
            ->post(route('stock.movements.store'), [
                'branch' => 'old_airport',
                'stock_item_id' => $backbar->id,
                'week_start_date' => $bounds['start_str'],
                'quantity' => 2.5,
                'reference_note' => 'Supplier INV-88',
            ])
            ->assertRedirect();

        // 3. Old Airport Manager transfers 1.5 tubes to Al Wakrah (atomic both branches)
        $this->actingAs($this->oldAirportManager)
            ->post(route('stock.transfer'), [
                'from_branch' => 'old_airport',
                'to_branch' => 'al_wakrah',
                'stock_item_id' => $backbar->id,
                'week_start_date' => $bounds['start_str'],
                'quantity' => 1.5,
                'reference_note' => 'Driver courier',
            ])
            ->assertRedirect();

        $wakrahWeek = StockWeek::where('branch', 'al_wakrah')->whereDate('week_start_date', $bounds['start_str'])->firstOrFail();
        $wakrahEntry = StockWeekEntry::where('stock_week_id', $wakrahWeek->id)->where('stock_item_id', $backbar->id)->firstOrFail();
        $this->assertEquals(1.5, $wakrahEntry->transfer_net_qty);
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_TRANSFER_OUT)->count());
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_TRANSFER_IN)->count());

        // 4. Manager enters Friday Closing counts (4.5 backbar, 3 retail) and submits for approval
        $this->actingAs($this->oldAirportManager)
            ->post(route('stock.weeks.submit', $oaWeek), [
                'entries' => [
                    $backbar->id => ['opening_qty' => 10, 'closing_qty' => 4.5],
                    $retailItem->id => ['opening_qty' => 6, 'closing_qty' => 3],
                ],
            ])
            ->assertRedirect();

        $oaWeek->refresh();
        $this->assertSame(StockWeek::STATUS_SUBMITTED, $oaWeek->status);

        $bbEntry = StockWeekEntry::where('stock_week_id', $oaWeek->id)->where('stock_item_id', $backbar->id)->firstOrFail();
        $rtEntry = StockWeekEntry::where('stock_week_id', $oaWeek->id)->where('stock_item_id', $retailItem->id)->firstOrFail();

        // Math: Used = Opening (10) + Received (2.5) + Transfer (-1.5) - Closing (4.5) = 6.5
        $this->assertEquals(6.5, $bbEntry->used_qty);
        $this->assertEquals(1.5, $bbEntry->needToOrderQty($backbar->parForBranch('old_airport')));

        // Retail: Opening 6 - Closing 3 = Used 3 vs Retail Sold 2 -> shrinkage flag true!
        $this->assertEquals(3.0, $rtEntry->used_qty);
        $this->assertEquals(2.0, $rtEntry->retail_sold_qty);
        $this->assertTrue($rtEntry->hasRetailShrinkage());
        $this->assertEquals(2.0, $rtEntry->needToOrderQty($retailItem->parForBranch('old_airport')));

        // 5. Post-submit lock: Manager gets 403 editing counts, logging restocks, or self-approving
        $this->actingAs($this->oldAirportManager)
            ->post(route('stock.weeks.save-counts', $oaWeek), [
                'entries' => [$backbar->id => ['closing_qty' => 5]],
            ])
            ->assertForbidden();

        $this->actingAs($this->oldAirportManager)
            ->post(route('stock.movements.store'), [
                'branch' => 'old_airport',
                'stock_item_id' => $backbar->id,
                'week_start_date' => $bounds['start_str'],
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->oldAirportManager)
            ->post(route('stock.weeks.approve', $oaWeek))
            ->assertForbidden();

        // 6. Admin requests revision with note -> unlocks for Manager
        $this->actingAs($this->admin)
            ->post(route('stock.weeks.request-revision', $oaWeek), [
                'admin_note' => 'Please recount Keratin Home Mask on retail shelf.',
            ])
            ->assertRedirect();

        $oaWeek->refresh();
        $this->assertSame(StockWeek::STATUS_REVISION_REQUESTED, $oaWeek->status);
        $this->assertSame('Please recount Keratin Home Mask on retail shelf.', $oaWeek->admin_note);

        // Manager can now correct closing to 4 (matches 2 sold) and resubmit
        $this->actingAs($this->oldAirportManager)
            ->post(route('stock.weeks.submit', $oaWeek), [
                'entries' => [
                    $backbar->id => ['closing_qty' => 4.5],
                    $retailItem->id => ['closing_qty' => 4],
                ],
            ])
            ->assertRedirect();

        // 7. Admin approves & carries forward Friday Closing -> next Saturday Opening (2026-11-28)
        $this->actingAs($this->admin)
            ->post(route('stock.weeks.approve', $oaWeek))
            ->assertRedirect();

        $oaWeek->refresh();
        $this->assertSame(StockWeek::STATUS_APPROVED, $oaWeek->status);

        $nextWeek = StockWeek::where('branch', 'old_airport')->whereDate('week_start_date', '2026-11-28')->firstOrFail();
        $nextBackbar = StockWeekEntry::where('stock_week_id', $nextWeek->id)->where('stock_item_id', $backbar->id)->firstOrFail();
        $nextRetail = StockWeekEntry::where('stock_week_id', $nextWeek->id)->where('stock_item_id', $retailItem->id)->firstOrFail();

        $this->assertEquals(4.5, $nextBackbar->opening_qty);
        $this->assertEquals(4.0, $nextRetail->opening_qty);

        // Verify Need to Order tab renders properly with WhatsApp copy text
        $this->actingAs($this->opsLead)
            ->get(route('stock.index', ['tab' => 'order', 'branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk()
            ->assertSee('Majirel 7.1 Hair Color Tube')
            ->assertSee('Copy WhatsApp Order List');
    }

    public function test_stock_rbac_enforces_branch_isolation_ops_lead_limits_and_unauthorized_roles(): void
    {
        $bounds = StockWeek::weekBoundsFor('2026-11-25');
        $item = StockItem::create([
            'name' => 'Cotton Pads Pack',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'consumables',
            'unit' => 'Pack',
            'par_level_old_airport' => 5,
            'par_level_al_wakrah' => 5,
            'is_active' => true,
        ]);

        // Old Airport Manager cannot view Al Wakrah stock
        $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'al_wakrah', 'week' => $bounds['start_str']]))
            ->assertForbidden();

        // Ops Lead can view both branches & log restock, but gets 403 entering Friday counts or approving
        $this->actingAs($this->opsLead)
            ->get(route('stock.index', ['branch' => 'al_wakrah', 'week' => $bounds['start_str']]))
            ->assertOk();

        $wakrahWeek = StockWeek::where('branch', 'al_wakrah')->whereDate('week_start_date', $bounds['start_str'])->firstOrFail();

        $this->actingAs($this->opsLead)
            ->post(route('stock.weeks.save-counts', $wakrahWeek), [
                'entries' => [$item->id => ['closing_qty' => 2]],
            ])
            ->assertForbidden();

        $this->actingAs($this->opsLead)
            ->post(route('stock.items.store'), [
                'name' => 'Blocked Ops Catalog Item',
                'category' => 'consumables',
                'unit' => 'Box',
            ])
            ->assertForbidden();

        // Chat Agent and Legal Team have no access to Stock
        $chatAgent = User::factory()->create([
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);
        $legalUser = User::factory()->create([
            'role' => 'legal_team',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('legal_team'),
        ]);

        $this->actingAs($chatAgent)->get(route('stock.index'))->assertForbidden();
        $this->actingAs($legalUser)->get(route('stock.index'))->assertForbidden();
    }

    public function test_stock_minimal_ui_empty_state_six_columns_and_context_aware_actions_for_admin_and_manager(): void
    {
        $bounds = StockWeek::weekBoundsFor('2026-11-25');

        // 1. 0-item empty state: clean empty state, + Add First Item, no footer action bar, collapsed movements disclosure
        $emptyAdminResp = $this->actingAs($this->admin)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();

        $emptyAdminResp->assertSee('Weekly Saturday&ndash;Friday stock counts per branch.', false);
        $emptyAdminResp->assertSeeInOrder(['Weekly Count', 'To Order', 'Items']);
        $emptyAdminResp->assertSee('No stock items added yet');
        $emptyAdminResp->assertSee('Add First Item');
        $emptyAdminResp->assertSee('Deliveries &amp; Transfers This Week (<span id="stkMovementsCount">0</span>)', false);
        $emptyAdminResp->assertDontSee('Save Draft');
        $emptyAdminResp->assertDontSee('Request Revision');
        $emptyAdminResp->assertDontSee('Approve &amp; Close Week', false);
        $emptyAdminResp->assertDontSee('Formula:');

        // 2. Seed 5 mixed Backbar (3) + Retail (2) items + 2 retail bottles sold in week
        $bb1 = StockItem::create([
            'name' => 'Majirel 7.1 Hair Color',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'hair_color',
            'unit' => 'Tube',
            'par_level_old_airport' => 6,
            'par_level_al_wakrah' => 4,
            'is_active' => true,
        ]);
        $bb2 = StockItem::create([
            'name' => 'Hair Botox Treatment',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'treatments',
            'unit' => 'Bottle',
            'par_level_old_airport' => 4,
            'par_level_al_wakrah' => 3,
            'is_active' => true,
        ]);
        $bb3 = StockItem::create([
            'name' => 'Azulene Wax Tin',
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => 'waxing',
            'unit' => 'Tin',
            'par_level_old_airport' => 5,
            'par_level_al_wakrah' => 5,
            'is_active' => true,
        ]);

        $retProd1 = Product::create([
            'name' => 'Argan Shine Serum',
            'sku' => 'ARG-01',
            'price' => 140,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
        ]);
        $retProd2 = Product::create([
            'name' => 'Silk Repair Shampoo',
            'sku' => 'SLK-02',
            'price' => 120,
            'stock_quantity' => 8,
            'low_stock_threshold' => 4,
        ]);
        $ret1 = StockItem::where('product_id', $retProd1->id)->firstOrFail();
        $ret2 = StockItem::where('product_id', $retProd2->id)->firstOrFail();

        $saleTime = Carbon::parse('2026-11-24 14:00:00', 'Asia/Qatar');
        $this->createSaleWithPayments('old_airport', $saleTime, ['card' => 280], [
            [
                'product_id' => $retProd1->id,
                'name' => $retProd1->name,
                'quantity' => 2,
                'unit_price' => 140,
            ],
        ]);

        // Load week to seed all 5 entries, then save decimal count (3.5) and Retail shrinkage mismatch (Used 3 vs 2 sold)
        $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();

        $oaWeek = StockWeek::where('branch', 'old_airport')->whereDate('week_start_date', $bounds['start_str'])->firstOrFail();

        $this->actingAs($this->oldAirportManager)->post(route('stock.weeks.save-counts', $oaWeek), [
            'entries' => [
                $bb1->id => ['opening_qty' => 8, 'closing_qty' => 3.5],
                $bb2->id => ['opening_qty' => 5, 'closing_qty' => 4],
                $bb3->id => ['opening_qty' => 6, 'closing_qty' => 5],
                $ret1->id => ['opening_qty' => 6, 'closing_qty' => 3],
                $ret2->id => ['opening_qty' => 5, 'closing_qty' => 4],
            ],
        ])->assertRedirect();

        // 3. Open state: Salon Manager sees 6 live balance columns, decimal 3.5, retail "2 sold" mismatch, Order +2.5, single Submit button (no Save Draft / Request Revision / Approve)
        $mgrOpenResp = $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();

        $mgrOpenResp->assertSeeInOrder(['Item', 'Opening', 'Added', 'Total', 'Used', 'Remaining']);
        $mgrOpenResp->assertDontSee('Next Sat');
        $mgrOpenResp->assertDontSee('Formula:');
        $mgrOpenResp->assertDontSee('Save Draft');
        $mgrOpenResp->assertSee('3.5');
        $mgrOpenResp->assertSee('Order +2.5');
        $mgrOpenResp->assertSee('2 sold');
        $mgrOpenResp->assertSee('Submit Friday Count for Approval');
        $mgrOpenResp->assertDontSee('Request Revision');
        $mgrOpenResp->assertDontSee('Approve &amp; Carry Forward to Next Week', false);

        // 4. Open state: Admin sees Approve & Carry Forward to Next Week (NO Save Draft or Request Revision while Open)
        $adminOpenResp = $this->actingAs($this->admin)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();

        $adminOpenResp->assertDontSee('Save Draft');
        $adminOpenResp->assertSee('Approve &amp; Carry Forward to Next Week', false);
        $adminOpenResp->assertDontSee('Request Revision');

        // 5. Submitted state: Manager sees calm locked notice; Admin sees Request Revision + Approve & Carry Forward
        $this->actingAs($this->oldAirportManager)->post(route('stock.weeks.submit', $oaWeek))->assertRedirect();

        $mgrSubResp = $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();
        $mgrSubResp->assertSee('Pending Approval');
        $mgrSubResp->assertSee('Submitted for Admin approval');
        $mgrSubResp->assertDontSee('Submit Friday Count for Approval');

        $adminSubResp = $this->actingAs($this->admin)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();
        $adminSubResp->assertSee('Request Revision');
        $adminSubResp->assertSee('Approve &amp; Carry Forward', false);

        // 6. Approved state: calm approved line, and Week 2 Opening is read-only text (no opening_qty input)
        $this->actingAs($this->admin)->post(route('stock.weeks.approve', $oaWeek))->assertRedirect();

        $approvedResp = $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();
        $approvedResp->assertSee('Remaining balances carried forward to next Saturday');
        $approvedResp->assertDontSee('Submit Friday Count for Approval');

        $nextWeekResp = $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => '2026-11-28']))
            ->assertOk();
        $nextWeekResp->assertDontSee('[opening_qty]', false);
        $nextWeekResp->assertSee('3.5');
    }

    public function test_stock_live_balance_sheet_autosave_bidirectional_sync_restock_validation_and_guards(): void
    {
        $bounds = StockWeek::weekBoundsFor('2026-11-25');

        $product = Product::create([
            'name' => 'Argan Oil Hair Serum',
            'sku' => 'ARG-SER-01',
            'price' => 150,
            'stock_quantity' => 10,
            'low_stock_threshold' => 6,
        ]);
        $item = StockItem::where('product_id', $product->id)->firstOrFail();

        // Seed Old Airport week
        $this->actingAs($this->oldAirportManager)
            ->get(route('stock.index', ['branch' => 'old_airport', 'week' => $bounds['start_str']]))
            ->assertOk();

        $oaWeek = StockWeek::where('branch', 'old_airport')->whereDate('week_start_date', $bounds['start_str'])->firstOrFail();
        $entry = StockWeekEntry::where('stock_week_id', $oaWeek->id)->where('stock_item_id', $item->id)->firstOrFail();
        $entryUrl = route('stock.weeks.entries.update', ['stockWeek' => $oaWeek->id, 'stockWeekEntry' => $entry->id]);

        // Step 1: Set Opening = 4 -> auto-saves, Total = 4, Remaining = 4, Used = 0
        $this->actingAs($this->oldAirportManager)
            ->patchJson($entryUrl, [
                'changed_field' => 'opening',
                'opening_qty' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('entry.opening_qty', 4)
            ->assertJsonPath('entry.total_qty', 4)
            ->assertJsonPath('entry.used_qty', 0)
            ->assertJsonPath('entry.closing_qty', 4);

        // Step 2: Inline + add 2 -> Added = +2, Total = 6, Remaining = 6, Used = 0
        $this->actingAs($this->oldAirportManager)
            ->postJson(route('stock.movements.store'), [
                'branch' => 'old_airport',
                'week_start_date' => $bounds['start_str'],
                'stock_item_id' => $item->id,
                'quantity' => 2,
                'reference_note' => 'Morning delivery',
            ])
            ->assertOk()
            ->assertJsonPath('entry.added_qty', 2)
            ->assertJsonPath('entry.total_qty', 6)
            ->assertJsonPath('entry.used_qty', 0)
            ->assertJsonPath('entry.closing_qty', 6);

        // Step 3: Enter Used = 2 -> Remaining immediately becomes 4 and auto-saves
        $this->actingAs($this->oldAirportManager)
            ->patchJson($entryUrl, [
                'changed_field' => 'used',
                'used_qty' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('entry.total_qty', 6)
            ->assertJsonPath('entry.used_qty', 2)
            ->assertJsonPath('entry.closing_qty', 4);

        // Step 4: Add another 3 via + -> Added = +5, Total = 9, Used stays 2, Remaining auto-updates to 7
        $this->actingAs($this->oldAirportManager)
            ->postJson(route('stock.movements.store'), [
                'branch' => 'old_airport',
                'week_start_date' => $bounds['start_str'],
                'stock_item_id' => $item->id,
                'quantity' => 3,
                'reference_note' => 'Afternoon restock',
            ])
            ->assertOk()
            ->assertJsonPath('entry.added_qty', 5)
            ->assertJsonPath('entry.total_qty', 9)
            ->assertJsonPath('entry.used_qty', 2)
            ->assertJsonPath('entry.closing_qty', 7);

        // Step 5: Enter Remaining = 5 -> Used immediately becomes 4 and auto-saves
        $this->actingAs($this->oldAirportManager)
            ->patchJson($entryUrl, [
                'changed_field' => 'remaining',
                'closing_qty' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('entry.total_qty', 9)
            ->assertJsonPath('entry.used_qty', 4)
            ->assertJsonPath('entry.closing_qty', 5);

        // Validation: Used > Total (10 > 9) or Remaining > Total (10 > 9) returns 422 "Cannot exceed Total balance"
        $this->actingAs($this->oldAirportManager)
            ->patchJson($entryUrl, ['changed_field' => 'used', 'used_qty' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['used_qty']);

        $this->actingAs($this->oldAirportManager)
            ->patchJson($entryUrl, ['changed_field' => 'remaining', 'closing_qty' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closing_qty']);

        // Cross-branch Manager & Ops Lead receive 403 on per-row auto-save
        $this->actingAs($this->wakrahManager)
            ->patchJson($entryUrl, ['changed_field' => 'used', 'used_qty' => 1])
            ->assertForbidden();

        $this->actingAs($this->opsLead)
            ->patchJson($entryUrl, ['changed_field' => 'used', 'used_qty' => 1])
            ->assertForbidden();

        // Manager submitting locks per-row auto-save for Manager (403)
        $this->actingAs($this->oldAirportManager)->post(route('stock.weeks.submit', $oaWeek))->assertRedirect();
        $this->actingAs($this->oldAirportManager)
            ->patchJson($entryUrl, ['changed_field' => 'used', 'used_qty' => 1])
            ->assertForbidden();

        // Step 6: Admin Approve & Carry Forward -> Next week Opening starts at 5, Remaining = 5, Used = 0
        $this->actingAs($this->admin)->post(route('stock.weeks.approve', $oaWeek))->assertRedirect();

        // Approved week blocks per-row auto-save even for Admin (403)
        $this->actingAs($this->admin)
            ->patchJson($entryUrl, ['changed_field' => 'used', 'used_qty' => 1])
            ->assertForbidden();

        $nextWeek = StockWeek::where('branch', 'old_airport')->whereDate('week_start_date', '2026-11-28')->firstOrFail();
        $nextEntry = StockWeekEntry::where('stock_week_id', $nextWeek->id)->where('stock_item_id', $item->id)->firstOrFail();
        $this->assertEquals(5.0, $nextEntry->opening_qty);
        $this->assertEquals(5.0, $nextEntry->closing_qty);
        $this->assertEquals(0.0, $nextEntry->used_qty);
    }

    /* =========================================================================
     * UPGRADE 3 — SERVICES MODULE: COMPANY-WIDE MONTHLY OFFERS BOARD
     * ========================================================================= */

    public function test_monthly_offers_live_syncs_combos_and_freebies_requires_tcs_and_evaluates_qatar_dates(): void
    {
        $svc1 = Service::create(['name' => 'Signature Blowdry', 'price' => 150, 'duration' => 45]);
        $svc2 = Service::create(['name' => 'Classic Manicure', 'price' => 90, 'duration' => 30]);
        $svc3 = Service::create(['name' => 'Eyebrow Threading', 'price' => 45, 'duration' => 15]);

        $combo = Combo::create([
            'name' => 'November Glow Trio',
            'price' => 220,
            'quantity_included' => 2,
            'validity_days' => 14,
            'status' => 'active',
        ]);
        $combo->services()->sync([$svc1->id, $svc2->id]);

        $freebie = ServiceFreebiePackage::create([
            'name' => 'Blowdry + Free Brows',
            'anchor_service_id' => $svc1->id,
            'price' => 150,
            'free_choices_count' => 1,
            'validity_days' => 7,
            'is_active' => true,
        ]);
        $freebie->eligibleServices()->sync([$svc3->id]);

        $today = Carbon::now('Asia/Qatar');

        // 1. Blank Terms & Conditions must fail validation (422)
        $this->actingAs($this->admin)
            ->postJson(route('offers.store'), [
                'title' => 'Missing TCs Promo',
                'offer_type' => 'standalone',
                'promo_price_qar' => 199,
                'branch_scope' => 'all',
                'valid_from' => $today->copy()->subDay()->toDateString(),
                'valid_until' => $today->copy()->addDays(10)->toDateString(),
                'terms_and_conditions' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_and_conditions']);

        // 2. Create Active Combo Offer, Upcoming Service+Freebie Offer, and Expired Standalone Offer
        $this->actingAs($this->admin)->post(route('offers.store'), [
            'title' => 'Monthly Glow Combo Promo',
            'offer_type' => 'combo',
            'combo_id' => $combo->id,
            'branch_scope' => 'all',
            'valid_from' => $today->copy()->subDays(2)->toDateString(),
            'valid_until' => $today->copy()->addDays(10)->toDateString(),
            'terms_and_conditions' => "Valid Sunday to Wednesday only\nAdvance booking required",
            'staff_notes' => 'Upsell hair mask add-on.',
            'is_active' => '1',
        ])->assertRedirect(route('services.index', ['tab' => 'offers']));

        $this->actingAs($this->admin)->post(route('offers.store'), [
            'title' => 'Next Week Freebie Special',
            'offer_type' => 'service_freebie',
            'service_freebie_id' => $freebie->id,
            'branch_scope' => 'old_airport',
            'valid_from' => $today->copy()->addDays(3)->toDateString(),
            'valid_until' => $today->copy()->addDays(15)->toDateString(),
            'terms_and_conditions' => 'Old Airport branch only',
            'is_active' => '1',
        ])->assertRedirect(route('services.index', ['tab' => 'offers']));

        $this->actingAs($this->admin)->post(route('offers.store'), [
            'title' => 'Last Month Flash Deal',
            'offer_type' => 'standalone',
            'promo_price_qar' => 99,
            'original_value_qar' => 160,
            'branch_scope' => 'al_wakrah',
            'valid_from' => $today->copy()->subDays(20)->toDateString(),
            'valid_until' => $today->copy()->subDays(2)->toDateString(),
            'terms_and_conditions' => 'Walk-ins welcome',
            'is_active' => '1',
        ])->assertRedirect(route('services.index', ['tab' => 'offers']));

        $activeOffer = MonthlyOffer::where('title', 'Monthly Glow Combo Promo')->with('combo.services')->firstOrFail();
        $upcomingOffer = MonthlyOffer::where('title', 'Next Week Freebie Special')->with(['freebiePackage.anchorService', 'freebiePackage.eligibleServices'])->firstOrFail();
        $expiredOffer = MonthlyOffer::where('title', 'Last Month Flash Deal')->firstOrFail();

        $this->assertSame('active', $activeOffer->liveStatus($today)['key']);
        $this->assertSame('upcoming', $upcomingOffer->liveStatus($today)['key']);
        $this->assertSame('expired', $expiredOffer->liveStatus($today)['key']);
        $this->assertEquals(220.0, $activeOffer->effectivePrice());

        // Live sync: updating linked Combo price updates Monthly Offer display live without re-saving the offer
        $combo->update(['price' => 275]);
        $activeOffer->refresh()->load('combo.services');
        $this->assertEquals(275.0, $activeOffer->effectivePrice());

        $this->actingAs($this->admin)
            ->get(route('services.index', ['tab' => 'offers']))
            ->assertOk()
            ->assertSee('Monthly Glow Combo Promo')
            ->assertSee('275')
            ->assertSee('Signature Blowdry')
            ->assertSee('Valid Sunday to Wednesday only')
            ->assertSee('Copy for Client');

        // Toggle archive & delete
        $this->actingAs($this->admin)->patch(route('offers.toggle', $activeOffer))->assertRedirect();
        $this->assertFalse($activeOffer->fresh()->is_active);
        $this->assertSame('archived', $activeOffer->fresh()->liveStatus($today)['key']);

        $this->actingAs($this->admin)->delete(route('offers.destroy', $expiredOffer))->assertRedirect();
        $this->assertDatabaseMissing('monthly_offers', ['id' => $expiredOffer->id]);
    }

    public function test_monthly_offers_accessible_to_all_operational_roles_and_blocked_for_legal_team(): void
    {
        $today = Carbon::now('Asia/Qatar');
        $offer = MonthlyOffer::create([
            'title' => 'Laleen Signature Monthly Glow',
            'offer_type' => MonthlyOffer::TYPE_STANDALONE,
            'promo_price_qar' => 299,
            'original_value_qar' => 420,
            'branch_scope' => 'all',
            'valid_from' => $today->copy()->subDay()->toDateString(),
            'valid_until' => $today->copy()->addDays(10)->toDateString(),
            'terms_and_conditions' => "Valid all days\nCannot be combined with other offers",
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        // Create salon_staff linked to a Staff row for /my-profile verification
        $salonStaffUser = User::factory()->create([
            'name' => 'Lina Stylist',
            'email' => 'lina.offers@laleen.qa',
            'role' => 'salon_staff',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
        ]);
        Staff::create([
            'name' => 'Lina Stylist',
            'email' => 'lina.offers@laleen.qa',
            'branch' => 'old_airport',
            'role' => 'Stylist',
            'user_id' => $salonStaffUser->id,
            'status' => 'active',
        ]);

        $otherRoles = [
            'sales_coordinator',
            'agent',
            'marketing_specialist',
            'social_media_editor',
        ];
        $operationalUsers = [
            $this->admin,
            $this->oldAirportManager,
            $this->opsLead,
            $salonStaffUser,
        ];
        foreach ($otherRoles as $r) {
            $operationalUsers[] = User::factory()->create([
                'role' => $r,
                'is_active' => true,
                'permissions' => User::defaultPermissionsForRole($r),
            ]);
        }

        // All 8 operational roles can view /monthly-offers (200) and read title + T&Cs
        foreach ($operationalUsers as $u) {
            $this->actingAs($u)
                ->get(route('offers.index'))
                ->assertOk()
                ->assertSee('Laleen Signature Monthly Glow')
                ->assertSee('Cannot be combined with other offers');
        }

        // Salon Staff sees Monthly Offers shortcut in /my-profile & stays isolated from /services
        $this->actingAs($salonStaffUser)
            ->get(route('portal.profile'))
            ->assertOk()
            ->assertSee(route('offers.index'), false)
            ->assertSee('Monthly Offers');

        $this->actingAs($salonStaffUser)->get(route('services.index'))->assertForbidden();

        // Non-editors cannot create, toggle, or delete offers (403)
        foreach ([$salonStaffUser, $this->opsLead, $this->oldAirportManager] as $nonEditor) {
            if ($nonEditor->canEdit('services')) {
                continue;
            }
            $this->actingAs($nonEditor)->post(route('offers.store'), [
                'title' => 'Unauthorized Promo',
                'offer_type' => 'standalone',
                'promo_price_qar' => 100,
                'branch_scope' => 'all',
                'valid_from' => $today->toDateString(),
                'valid_until' => $today->copy()->addDays(5)->toDateString(),
                'terms_and_conditions' => 'None',
            ])->assertForbidden();

            $this->actingAs($nonEditor)->patch(route('offers.toggle', $offer))->assertForbidden();
            $this->actingAs($nonEditor)->delete(route('offers.destroy', $offer))->assertForbidden();
        }

        // Legal Team is strictly blocked (403) from /monthly-offers and /offers
        $legalUser = User::factory()->create([
            'role' => 'legal_team',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('legal_team'),
        ]);
        $this->actingAs($legalUser)->get('/monthly-offers')->assertForbidden();
        $this->actingAs($legalUser)->get('/offers')->assertForbidden();
    }
}
