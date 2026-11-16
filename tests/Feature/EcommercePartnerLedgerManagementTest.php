<?php

namespace Tests\Feature;

use App\Models\EcommerceExpense;
use App\Models\EcommerceTransactionCategory;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommercePartnerLedgerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Owner',
            'email' => 'admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_dynamic_partner_add_renders_across_pk_and_qa_populates_transaction_dropdown_and_rejects_case_insensitive_duplicates(): void
    {
        $afnan = Partner::create(['name' => 'AFNAN', 'equity_percentage' => 33.3333]);
        PartnerTransaction::create([
            'partner_id' => $afnan->id,
            'type' => 'injection',
            'amount' => 120000,
            'currency' => 'PKR',
            'category' => 'Initial Inventory',
            'transaction_date' => '2026-11-10',
        ]);
        PartnerTransaction::create([
            'partner_id' => $afnan->id,
            'type' => 'injection',
            'amount' => 5000,
            'currency' => 'QAR',
            'category' => 'Initial Inventory',
            'transaction_date' => '2026-11-10',
        ]);

        // Add new partner dynamically via + Add Partner endpoint
        $storeRes = $this->actingAs($this->admin)->post(route('ecommerce.partners.store'), [
            'name' => '  ZAYD  ',
            'email' => 'zayd@laleen.qa',
            'equity_percentage' => 25,
            'region' => 'pk',
        ]);
        $storeRes->assertRedirect(route('ecommerce.partners.index', ['region' => 'pk']));

        $zayd = Partner::where('name', 'ZAYD')->first();
        $this->assertNotNull($zayd);

        // Verify PK view renders ZAYD's Partner Card (0.00 balance + capital matching) & Add Transaction select option
        $pkPage = $this->actingAs($this->admin)->get(route('ecommerce.partners.index', ['region' => 'pk']));
        $pkPage->assertOk()
            ->assertSee('ZAYD')
            ->assertSee('PKR 0.00')
            ->assertSee('Owes PKR 120,000.00 to match the top investor')
            ->assertSee(route('ecommerce.partners.transactions.store', $zayd->id), false);

        // Verify QA view also renders ZAYD cleanly with QAR capital matching
        $qaPage = $this->actingAs($this->admin)->get(route('ecommerce.partners.index', ['region' => 'qa']));
        $qaPage->assertOk()
            ->assertSee('ZAYD')
            ->assertSee('QAR 0.00')
            ->assertSee('Owes QAR 5,000.00 to match the top investor')
            ->assertSee(route('ecommerce.partners.transactions.store', $zayd->id), false);

        // Case-insensitive duplicate partner name must be rejected cleanly with 422
        $dupJson = $this->actingAs($this->admin)->postJson(route('ecommerce.partners.store'), [
            'name' => '  zayd ',
            'equity_percentage' => 25,
        ]);
        $dupJson->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Partner::whereRaw('LOWER(name) = ?', ['zayd'])->count());
    }

    public function test_deleting_partner_cascades_transactions_and_recalculates_pool_runway_and_equalization_with_custom_modal(): void
    {
        $afnan = Partner::create(['name' => 'AFNAN', 'equity_percentage' => 33.3333]);
        $laden = Partner::create(['name' => 'LADEN', 'equity_percentage' => 33.3333]);
        $sundus = Partner::create(['name' => 'SUNDUS', 'equity_percentage' => 33.3334]);

        // AFNAN injects PKR 200,000; LADEN injects PKR 80,000; SUNDUS has 0 transactions
        PartnerTransaction::create([
            'partner_id' => $afnan->id,
            'type' => 'injection',
            'amount' => 200000,
            'currency' => 'PKR',
            'category' => 'Initial Inventory',
            'transaction_date' => '2026-11-01',
        ]);
        PartnerTransaction::create([
            'partner_id' => $laden->id,
            'type' => 'injection',
            'amount' => 80000,
            'currency' => 'PKR',
            'category' => 'Ad Budget',
            'transaction_date' => '2026-11-02',
        ]);

        // Partner-funded expense of PKR 30,000
        EcommerceExpense::create([
            'expense_date' => '2026-11-05',
            'category' => 'Marketing / Ads',
            'title' => 'Meta Launch Campaign',
            'amount' => 30000,
            'currency' => 'PKR',
            'funding_source' => 'partner_ledger',
        ]);

        // Initial check: Pool = 280,000, Runway = 250,000, LADEN owes 120,000, zero raw confirm()
        $initialRes = $this->actingAs($this->admin)->get(route('ecommerce.partners.index', ['region' => 'pk']));
        $initialRes->assertOk()
            ->assertSee('PKR 280,000.00')
            ->assertSee('PKR 250,000.00')
            ->assertSee('Owes PKR 120,000.00 to match the top investor')
            ->assertSee('id="deletePartnerModal"', false)
            ->assertSee('data-partner-name="AFNAN"', false)
            ->assertSee('data-tx-count="1"', false)
            ->assertSee('data-tx-summary="PKR 200,000.00"', false)
            ->assertSee('data-tx-count="0"', false)
            ->assertDontSee('confirm(', false);

        // 1. Delete SUNDUS (0 transactions)
        $this->actingAs($this->admin)
            ->delete(route('ecommerce.partners.destroy', $sundus), ['region' => 'pk'])
            ->assertRedirect(route('ecommerce.partners.index', ['region' => 'pk']));
        $this->assertDatabaseMissing('partners', ['id' => $sundus->id]);

        // 2. Delete AFNAN (top investor with PKR 200,000 transaction)
        $this->actingAs($this->admin)
            ->delete(route('ecommerce.partners.destroy', $afnan), ['region' => 'pk'])
            ->assertRedirect(route('ecommerce.partners.index', ['region' => 'pk']));

        $this->assertDatabaseMissing('partners', ['id' => $afnan->id]);
        $this->assertDatabaseMissing('partner_transactions', ['partner_id' => $afnan->id]);

        // Verify recalculated Ledger: Total Pool = PKR 80,000.00, Runway = PKR 50,000.00, LADEN is now Fully Matched!
        $recalcRes = $this->actingAs($this->admin)->get(route('ecommerce.partners.index', ['region' => 'pk']));
        $recalcRes->assertOk()
            ->assertDontSee('data-partner-name="AFNAN"', false)
            ->assertSee('PKR 80,000.00')
            ->assertSee('PKR 50,000.00')
            ->assertSee('Fully Matched');
    }

    public function test_inline_custom_category_creation_and_transaction_submission_persists_for_future_use(): void
    {
        $partner = Partner::create(['name' => 'AFNAN', 'equity_percentage' => 50]);

        // 1. Inline AJAX category endpoint creates & deduplicates custom category
        $catRes = $this->actingAs($this->admin)->postJson(route('ecommerce.partners.categories.store'), [
            'name' => '  Influencer Seeding  ',
        ]);
        $catRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('category', 'Influencer Seeding');

        $this->assertDatabaseHas('ecommerce_transaction_categories', [
            'name' => 'Influencer Seeding',
        ]);

        // Case-insensitive duplicate returns canonical existing name without throwing or duplicating
        $dupCatRes = $this->actingAs($this->admin)->postJson(route('ecommerce.partners.categories.store'), [
            'name' => 'influencer seeding',
        ]);
        $dupCatRes->assertOk()
            ->assertJsonPath('category', 'Influencer Seeding');
        $this->assertSame(1, EcommerceTransactionCategory::count());

        // 2. Page load includes all 5 defaults + 'Influencer Seeding' + inline '+ New category' UI
        $ledgerRes = $this->actingAs($this->admin)->get(route('ecommerce.partners.index', ['region' => 'qa']));
        $ledgerRes->assertOk()
            ->assertSee('Initial Inventory')
            ->assertSee('Ad Budget')
            ->assertSee('Machinery/Filling Equipment')
            ->assertSee('Packaging Run')
            ->assertSee('Other')
            ->assertSee('<option value="Influencer Seeding">Influencer Seeding</option>', false)
            ->assertSee('id="toggleNewCategoryBtn"', false)
            ->assertSee('id="inlineNewCategoryInput"', false);

        // 3. Submitting a transaction with another brand-new custom category ('Lab Testing') succeeds & persists
        $txRes = $this->actingAs($this->admin)->post(route('ecommerce.partners.transactions.store', $partner), [
            'type' => 'injection',
            'amount' => 3500.50,
            'currency' => 'QAR',
            'category' => 'Lab Testing',
            'reference_note' => 'Dermatological stability test',
            'transaction_date' => '2026-11-16',
            'region' => 'qa',
        ]);
        $txRes->assertRedirect(route('ecommerce.partners.index', ['region' => 'qa']));

        $this->assertDatabaseHas('partner_transactions', [
            'partner_id' => $partner->id,
            'currency' => 'QAR',
            'category' => 'Lab Testing',
            'reference_note' => 'Dermatological stability test',
        ]);
        $this->assertDatabaseHas('ecommerce_transaction_categories', [
            'name' => 'Lab Testing',
        ]);

        // Subsequent page load shows both 'Influencer Seeding' and 'Lab Testing' in dropdown & table
        $this->actingAs($this->admin)->get(route('ecommerce.partners.index', ['region' => 'qa']))
            ->assertOk()
            ->assertSee('<option value="Lab Testing">Lab Testing</option>', false)
            ->assertSee('Dermatological stability test');

        // 4. Empty category is rejected with 422 validation error
        $this->actingAs($this->admin)->postJson(route('ecommerce.partners.transactions.store', $partner), [
            'type' => 'injection',
            'amount' => 500,
            'currency' => 'QAR',
            'category' => '   ',
            'transaction_date' => '2026-11-16',
        ])->assertStatus(422)->assertJsonValidationErrors('category');
    }
}
