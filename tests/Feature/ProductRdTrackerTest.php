<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductRdItem;
use App\Models\ProductRdSample;
use App\Models\ProductRdSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRdTrackerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'      => 'Super Admin',
            'email'     => 'afnanakbarghulam@gmail.com',
            'role'      => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_flat_tables_render_exact_six_regional_rows_and_six_supplier_sample_rows_without_kpi_cards_or_drawers(): void
    {
        $this->assertSame(2, ProductRdSupplier::count());
        $this->assertSame(3, ProductRdItem::count());
        $this->assertSame(6, ProductRdSample::count());

        $response = $this->actingAs($this->admin)->get(route('products.rd.index'));
        $response->assertOk();

        // Clean header & flat section headings
        $response->assertSee('Products Catalog');
        $response->assertSee('R&D & Sourcing');
        $response->assertSee('+ Add Row');
        $response->assertSee('+ Add Supplier');
        $response->assertSee('1. Regional SKU & Packaging Baseline');
        $response->assertSee('2. Supplier Sample Evaluation (Fist Industries & Glen)');

        // Zero KPI summary cards & zero slide-over drawers
        $response->assertDontSee('Pending Samples');
        $response->assertDontSee('Formulas Approved');
        $response->assertDontSee('Qatar Readiness');
        $response->assertDontSee('Pakistan Readiness');
        $response->assertDontSee('rdBattlecardDrawer');
        $response->assertDontSee('Compare & Score');

        // Verify exact 6 regional rows and order via view data
        /** @var array<int, array<string, mixed>> $regionalRows */
        $regionalRows = $response->viewData('regionalRows');
        $this->assertCount(6, $regionalRows);

        $expectedRegional = [
            ['product' => 'Shampoo',   'market_label' => 'Qatar',    'volume' => '400 ml', 'bottle_status_label' => 'Finalized', 'sticker_status_label' => 'Ready for Die-Line', 'is_sticker_blocked' => false],
            ['product' => 'Shampoo',   'market_label' => 'Pakistan', 'volume' => '200 ml', 'bottle_status_label' => 'Finalized', 'sticker_status_label' => 'Ready for Die-Line', 'is_sticker_blocked' => false],
            ['product' => 'Hair Oil',  'market_label' => 'Pakistan', 'volume' => '50 ml',  'bottle_status_label' => 'Finalized', 'sticker_status_label' => 'Ready for Die-Line', 'is_sticker_blocked' => false],
            ['product' => 'Hair Oil',  'market_label' => 'Qatar',    'volume' => '150 ml', 'bottle_status_label' => 'Pending',   'sticker_status_label' => 'Blocked by Bottle',  'is_sticker_blocked' => true],
            ['product' => 'Hair Mask', 'market_label' => 'Qatar',    'volume' => '500 ml', 'bottle_status_label' => 'Pending',   'sticker_status_label' => 'Blocked by Bottle',  'is_sticker_blocked' => true],
            ['product' => 'Hair Mask', 'market_label' => 'Pakistan', 'volume' => '500 ml', 'bottle_status_label' => 'Pending',   'sticker_status_label' => 'Blocked by Bottle',  'is_sticker_blocked' => true],
        ];

        foreach ($expectedRegional as $idx => $exp) {
            $this->assertSame($exp['product'], $regionalRows[$idx]['product']);
            $this->assertSame($exp['market_label'], $regionalRows[$idx]['market_label']);
            $this->assertSame($exp['volume'], $regionalRows[$idx]['volume']);
            $this->assertSame($exp['bottle_status_label'], $regionalRows[$idx]['bottle_status_label']);
            $this->assertSame($exp['sticker_status_label'], $regionalRows[$idx]['sticker_status_label']);
            $this->assertSame($exp['is_sticker_blocked'], $regionalRows[$idx]['is_sticker_blocked']);
        }

        // Verify exact 6 sample rows across Hair Mask, Hair Oil, Shampoo x Fist Industries, Glen
        /** @var array<int, array<string, mixed>> $sampleRows */
        $sampleRows = $response->viewData('sampleRows');
        $this->assertCount(6, $sampleRows);

        $response->assertSee('Ready for Die-Line');
        $response->assertSee('Blocked by Bottle');
        $response->assertSee('Waiting Sample');
    }

    public function test_inline_bottle_and_sticker_dependency_rule_and_volume_updates_persist_cleanly(): void
    {
        $hairOil = ProductRdItem::where('slug', 'hair-oil')->firstOrFail();

        // 1. While Qatar bottle is Pending, sticker status remains locked to Blocked by Bottle even if submitted
        $lockedRes = $this->actingAs($this->admin)->patchJson(
            route('products.rd.items.packaging-status', $hairOil),
            [
                'market'         => 'qatar',
                'sticker_status' => 'in_design',
            ]
        );
        $lockedRes->assertOk()
            ->assertJsonPath('row.bottle_status', 'pending')
            ->assertJsonPath('row.sticker_status', 'blocked_by_bottle')
            ->assertJsonPath('row.sticker_status_label', 'Blocked by Bottle')
            ->assertJsonPath('row.is_sticker_blocked', true);

        // 2. Changing Bottle Status from Pending -> Finalized automatically unlocks Sticker Status -> Ready for Die-Line
        $unlockRes = $this->actingAs($this->admin)->patchJson(
            route('products.rd.items.packaging-status', $hairOil),
            [
                'market'        => 'qatar',
                'bottle_status' => 'finalized',
            ]
        );
        $unlockRes->assertOk()
            ->assertJsonPath('row.bottle_status', 'finalized')
            ->assertJsonPath('row.bottle_status_label', 'Finalized')
            ->assertJsonPath('row.sticker_status', 'ready_for_dieline')
            ->assertJsonPath('row.sticker_status_label', 'Ready for Die-Line')
            ->assertJsonPath('row.is_sticker_blocked', false);

        // 3. Inline edit of Sticker Status -> In Design -> Finalized + Volume edit persists cleanly
        $this->actingAs($this->admin)->patchJson(
            route('products.rd.items.packaging-status', $hairOil),
            [
                'market'         => 'qatar',
                'volume'         => '175 ml',
                'sticker_status' => 'in_design',
            ]
        )->assertOk()
            ->assertJsonPath('row.volume', '175 ml')
            ->assertJsonPath('row.sticker_status', 'in_design')
            ->assertJsonPath('row.sticker_status_label', 'In Design');

        $this->actingAs($this->admin)->patchJson(
            route('products.rd.items.packaging-status', $hairOil),
            [
                'market'         => 'qatar',
                'sticker_status' => 'finalized',
            ]
        )->assertOk()
            ->assertJsonPath('row.sticker_status', 'finalized')
            ->assertJsonPath('row.sticker_status_label', 'Finalized');

        $freshOil = $hairOil->fresh();
        $this->assertSame('175 ml', $freshOil->qatar_bottle_size);
        $this->assertSame('finalized', $freshOil->qatar_bottle_status);
        $this->assertSame('finalized', $freshOil->qatar_sticker_status);

        // 4. Reverting Bottle Status to Pending re-locks Sticker Status -> Blocked by Bottle
        $this->actingAs($this->admin)->patchJson(
            route('products.rd.items.packaging-status', $hairOil),
            [
                'market'        => 'qatar',
                'bottle_status' => 'pending',
            ]
        )->assertOk()
            ->assertJsonPath('row.bottle_status', 'pending')
            ->assertJsonPath('row.sticker_status', 'blocked_by_bottle')
            ->assertJsonPath('row.is_sticker_blocked', true);
    }

    public function test_inline_supplier_sample_evaluation_criteria_and_verdict_persist_without_affecting_retail_inventory(): void
    {
        $retailProduct = Product::create([
            'name'                => 'Laleen Keratin Serum',
            'sku'                 => 'LAL-SER-01',
            'price'               => 120.00,
            'stock_quantity'      => 9,
            'low_stock_threshold' => 3,
        ]);

        $shampoo = ProductRdItem::where('slug', 'shampoo')->firstOrFail();
        $fist = ProductRdSupplier::where('name', 'Fist Industries')->firstOrFail();
        $glen = ProductRdSupplier::where('name', 'Glen')->firstOrFail();

        $fistSample = ProductRdSample::where('product_rd_item_id', $shampoo->id)
            ->where('product_rd_supplier_id', $fist->id)
            ->firstOrFail();

        $glenSample = ProductRdSample::where('product_rd_item_id', $shampoo->id)
            ->where('product_rd_supplier_id', $glen->id)
            ->firstOrFail();

        // Verify HTML renders ONLY —, 1, and 5 option choices (zero 2, 3, or 4 options)
        $rdPage = $this->actingAs($this->admin)->get(route('products.rd.index'));
        $rdPage->assertOk();
        $rdPage->assertSee('<option value="1"', false);
        $rdPage->assertSee('<option value="5"', false);
        $rdPage->assertDontSee('<option value="2"', false);
        $rdPage->assertDontSee('<option value="3"', false);
        $rdPage->assertDontSee('<option value="4"', false);

        // Middle values (2, 3, 4) are strictly rejected with 422
        foreach ([2, 3, 4] as $invalidMiddleScore) {
            $this->actingAs($this->admin)->patchJson(
                route('products.rd.samples.update', $fistSample),
                ['score_smell' => $invalidMiddleScore]
            )->assertStatus(422)->assertJsonValidationErrors(['score_smell']);

            $this->actingAs($this->admin)->patchJson(
                route('products.rd.samples.update', $fistSample),
                ['score_texture' => $invalidMiddleScore]
            )->assertStatus(422)->assertJsonValidationErrors(['score_texture']);

            $this->actingAs($this->admin)->patchJson(
                route('products.rd.samples.update', $fistSample),
                ['score_results' => $invalidMiddleScore]
            )->assertStatus(422)->assertJsonValidationErrors(['score_results']);

            $this->actingAs($this->admin)->patchJson(
                route('products.rd.samples.update', $fistSample),
                ['score_concept_clear' => $invalidMiddleScore]
            )->assertStatus(422)->assertJsonValidationErrors(['score_concept_clear']);

            $this->actingAs($this->admin)->patchJson(
                route('products.rd.samples.update', $fistSample),
                ['score_cost' => $invalidMiddleScore]
            )->assertStatus(422)->assertJsonValidationErrors(['score_cost']);
        }

        // Update Fist Industries sample with valid 1 / 5 scores + Verdict: Selected
        $resFist = $this->actingAs($this->admin)->patchJson(
            route('products.rd.samples.update', $fistSample),
            [
                'status'              => 'testing',
                'score_smell'         => 5,
                'cost'                => '5',
                'score_texture'       => 5,
                'score_results'       => 5,
                'score_concept_clear' => 1,
                'verdict'             => 'selected',
            ]
        );

        $resFist->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('sample.status', 'testing')
            ->assertJsonPath('sample.score_smell', 5)
            ->assertJsonPath('sample.score_cost', 5)
            ->assertJsonPath('sample.cost_display', '5')
            ->assertJsonPath('sample.score_texture', 5)
            ->assertJsonPath('sample.score_results', 5)
            ->assertJsonPath('sample.score_concept_clear', 1)
            ->assertJsonPath('sample.verdict', 'selected')
            ->assertJsonPath('sample.average_score', 4.2);

        $this->assertSame($fist->id, (int) $shampoo->fresh()->winning_supplier_id);

        // Update Glen sample with valid 1 / 5 scores + short text quote for Cost + Revision Needed + Verdict: Rejected
        $resGlen = $this->actingAs($this->admin)->patchJson(
            route('products.rd.samples.update', $glenSample),
            [
                'status'              => 'revision_needed',
                'score_smell'         => 1,
                'cost'                => '16.50 QAR',
                'score_texture'       => 1,
                'score_results'       => 5,
                'score_concept_clear' => 1,
                'verdict'             => 'rejected',
            ]
        );

        $resGlen->assertOk()
            ->assertJsonPath('sample.status', 'revision_needed')
            ->assertJsonPath('sample.status_label', 'Revision Needed')
            ->assertJsonPath('sample.cost_display', '16.50 QAR')
            ->assertJsonPath('sample.verdict', 'rejected');

        // Confirm Retail Products Catalog remains untouched
        $catalogPage = $this->actingAs($this->admin)->get(route('products.index'));
        $catalogPage->assertOk()
            ->assertSee('Retail Products')
            ->assertSee('Laleen Keratin Serum')
            ->assertSee('9 in stock');
        $this->assertSame(9, $retailProduct->fresh()->stock_quantity);
    }

    public function test_per_row_deletion_on_regional_and_sample_tables_preserves_sibling_rows_and_never_reseeds_on_reload(): void
    {
        $hairMask = ProductRdItem::where('slug', 'hair-mask')->firstOrFail();
        $glen = ProductRdSupplier::where('name', 'Glen')->firstOrFail();

        // 1. Delete Hair Mask · Pakistan row in Table 1 -> Hair Mask · Qatar remains intact
        $delRegRes = $this->actingAs($this->admin)->deleteJson(
            route('products.rd.items.regional.destroy', ['item' => $hairMask->id, 'market' => 'pakistan'])
        );
        $delRegRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('item_id', $hairMask->id)
            ->assertJsonPath('market', 'pakistan');

        $this->assertTrue($hairMask->fresh()->qatar_enabled);
        $this->assertFalse($hairMask->fresh()->pakistan_enabled);

        // 2. Delete Hair Mask · Glen row in Table 2 -> only that sample row is deleted
        $maskGlenSample = ProductRdSample::where('product_rd_item_id', $hairMask->id)
            ->where('product_rd_supplier_id', $glen->id)
            ->firstOrFail();

        $delSampleRes = $this->actingAs($this->admin)->deleteJson(
            route('products.rd.samples.destroy', $maskGlenSample)
        );
        $delSampleRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('supplier_heading', 'Fist Industries & Glen');

        $this->assertDatabaseMissing('product_rd_samples', ['id' => $maskGlenSample->id]);
        $this->assertSame(5, ProductRdSample::count());

        // 3. Reload GET /products/rd-sourcing and confirm neither deleted row resurrects
        $reload = $this->actingAs($this->admin)->get(route('products.rd.index'));
        $reload->assertOk();

        /** @var array<int, array<string, mixed>> $regionalRows */
        $regionalRows = $reload->viewData('regionalRows');
        $this->assertCount(5, $regionalRows);
        $this->assertNotEmpty(
            array_filter($regionalRows, fn ($r) => $r['product'] === 'Hair Mask' && $r['market'] === 'qatar')
        );
        $this->assertEmpty(
            array_filter($regionalRows, fn ($r) => $r['product'] === 'Hair Mask' && $r['market'] === 'pakistan')
        );

        /** @var array<int, array<string, mixed>> $sampleRows */
        $sampleRows = $reload->viewData('sampleRows');
        $this->assertCount(5, $sampleRows);
        $this->assertEmpty(
            array_filter($sampleRows, fn ($s) => $s['product'] === 'Hair Mask' && $s['supplier'] === 'Glen')
        );
        $this->assertNotEmpty(
            array_filter($sampleRows, fn ($s) => $s['product'] === 'Hair Mask' && $s['supplier'] === 'Fist Industries')
        );

        // 4. Delete remaining Glen sample rows -> Section 2 heading dynamically updates to only Fist Industries
        foreach (ProductRdSample::where('product_rd_supplier_id', $glen->id)->get() as $remGlenSample) {
            $this->actingAs($this->admin)
                ->deleteJson(route('products.rd.samples.destroy', $remGlenSample))
                ->assertOk();
        }

        $afterAllGlenDeleted = $this->actingAs($this->admin)->get(route('products.rd.index'));
        $afterAllGlenDeleted->assertOk()
            ->assertSee('2. Supplier Sample Evaluation (Fist Industries)')
            ->assertDontSee('Fist Industries & Glen');
        $this->assertSame(3, ProductRdSample::count());
    }
}
