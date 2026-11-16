<?php

namespace Tests\Feature;

use App\Models\EcommerceCustomer;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EcommerceHubRegionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecommerce_hub_renders_200_ok_for_both_pk_and_qa_regions_and_migration_backfills_customers_idempotently(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Owner',
            'email' => 'admin-ecom@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->assertTrue(Schema::hasColumn('ecommerce_customers', 'region'));

        $product = EcommerceProduct::create([
            'name' => 'Laleen Hair Elixir',
            'sku' => 'LAL-ELIX-100',
            'stock_pakistan' => 50,
            'stock_qatar' => 50,
        ]);

        // 1. Create untagged legacy rows (region = null) to verify migration backfill & regional scoping
        $pkPhoneCustomerId = DB::table('ecommerce_customers')->insertGetId([
            'name' => 'Ayesha Lahore',
            'phone' => '923001234567',
            'region' => null,
            'total_spend' => 1500,
            'total_orders' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $qaPhoneCustomerId = DB::table('ecommerce_customers')->insertGetId([
            'name' => 'Fatima Doha',
            'phone' => '97455123456',
            'region' => null,
            'total_spend' => 130,
            'total_orders' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $qaChannelCustomerId = DB::table('ecommerce_customers')->insertGetId([
            'name' => 'Rania Salon Buyer',
            'phone' => '962791112233',
            'region' => null,
            'total_spend' => 195,
            'total_orders' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EcommerceSale::create([
            'ecommerce_product_id' => $product->id,
            'ecommerce_customer_id' => $qaChannelCustomerId,
            'order_group_id' => 'ORD-QA-001',
            'customer_name' => 'Rania Salon Buyer',
            'customer_phone' => '962791112233',
            'quantity' => 1,
            'channel' => 'Salon (Old Airport)',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'unit_price' => 195,
            'total_price' => 195,
        ]);

        // Re-running the idempotent migration backfills untagged rows cleanly without error
        $migration = require database_path('migrations/2026_11_16_083500_ensure_region_column_on_ecommerce_customers_table.php');
        $migration->up();
        $migration->up(); // second call proves strict idempotency

        $this->assertSame('PK', EcommerceCustomer::findOrFail($pkPhoneCustomerId)->region);
        $this->assertSame('QA', EcommerceCustomer::findOrFail($qaPhoneCustomerId)->region);
        $this->assertSame('QA', EcommerceCustomer::findOrFail($qaChannelCustomerId)->region);

        // 2. Hit GET /ecommerce (default PK), GET /ecommerce?region=pk, and GET /ecommerce?region=qa
        $this->actingAs($admin)
            ->get('/ecommerce')
            ->assertOk()
            ->assertViewHas('region', 'pk')
            ->assertViewHas('currency', 'PKR')
            ->assertViewHas('totalCustomersCount', 1);

        $this->actingAs($admin)
            ->get('/ecommerce?region=pk')
            ->assertOk()
            ->assertViewHas('region', 'pk')
            ->assertViewHas('totalCustomersCount', 1);

        $this->actingAs($admin)
            ->get('/ecommerce?region=qa')
            ->assertOk()
            ->assertViewHas('region', 'qa')
            ->assertViewHas('currency', 'QAR')
            ->assertViewHas('totalCustomersCount', 2);

        // 3. Also verify Ecommerce Customers directory & Marketing hub return 200 OK for both regions
        $this->actingAs($admin)->get(route('ecommerce.customers.index', ['region' => 'pk']))->assertOk();
        $this->actingAs($admin)->get(route('ecommerce.customers.index', ['region' => 'qa']))->assertOk();
        $this->actingAs($admin)->get(route('ecommerce.marketing', ['region' => 'pk']))->assertOk();
        $this->actingAs($admin)->get(route('ecommerce.marketing', ['region' => 'qa']))->assertOk();
    }
}
