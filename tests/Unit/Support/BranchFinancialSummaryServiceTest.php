<?php

namespace Tests\Unit\Support;

use App\Models\Expense;
use App\Models\Sale;
use App\Support\BranchFinancialSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Stage 3C: the extracted branch profit/margin calculation. This project
 * has no isolated test database, so RefreshDatabase wraps every test
 * in a transaction that is always rolled back - no fake Sale/Expense row
 * this suite creates is ever actually persisted.
 */
class BranchFinancialSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FROZEN_NOW = '2026-06-15 12:00:00';

    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));
        $this->from = now()->startOfMonth();
        $this->to = now()->endOfDay();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeSale(string $branch, float $services, float $products, ?Carbon $when = null): Sale
    {
        // 'created_at' isn't in Sale::$fillable, so it can't be set via
        // create()'s mass assignment - it would silently be dropped and
        // then stamped with "now" by Eloquent's own timestamp handling.
        // Setting it directly before save() marks it dirty, which is what
        // stops updateTimestamps() from overwriting it.
        $sale = new Sale([
            'branch' => $branch,
            'services_total' => $services,
            'products_total' => $products,
            'total_amount' => $services + $products,
        ]);

        if ($when) {
            $sale->created_at = $when;
        }

        $sale->save();

        return $sale;
    }

    private function makeExpense(string $branch, float $amount, ?Carbon $when = null): Expense
    {
        return Expense::create([
            'branch' => $branch,
            'category' => 'Other',
            'amount' => $amount,
            'expense_date' => $when ?? now(),
        ]);
    }

    public function test_revenue_is_summed_correctly_per_branch(): void
    {
        $this->makeSale('old_airport', 1000, 200);
        $this->makeSale('old_airport', 500, 0);
        $this->makeSale('wakrah', 300, 50);

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        $this->assertSame(1700.0, $byKey['old_airport']['sales']);
        $this->assertSame(350.0, $byKey['wakrah']['sales']);
    }

    public function test_expenses_are_summed_correctly_per_branch(): void
    {
        $this->makeExpense('old_airport', 400);
        $this->makeExpense('old_airport', 100);
        $this->makeExpense('wakrah', 250);

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        $this->assertSame(500.0, $byKey['old_airport']['expenses']);
        $this->assertSame(250.0, $byKey['wakrah']['expenses']);
    }

    public function test_net_profit_is_revenue_minus_expenses(): void
    {
        $this->makeSale('old_airport', 1000, 0);
        $this->makeExpense('old_airport', 400);

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        $this->assertSame(600.0, $byKey['old_airport']['profit']);
    }

    public function test_profit_margin_is_net_profit_over_revenue(): void
    {
        $this->makeSale('old_airport', 1000, 0);
        $this->makeExpense('old_airport', 400);

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        // profit 600 / revenue 1000 * 100 = 60%.
        $this->assertSame(60.0, $byKey['old_airport']['margin_percent']);
    }

    public function test_zero_revenue_yields_a_null_margin_not_zero(): void
    {
        $this->makeExpense('old_airport', 250);
        // No sales at all for old_airport.

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        $this->assertSame(0.0, $byKey['old_airport']['sales']);
        $this->assertSame(-250.0, $byKey['old_airport']['profit']);
        $this->assertNull($byKey['old_airport']['margin_percent']);
    }

    public function test_expenses_exceeding_revenue_produce_a_negative_profit(): void
    {
        $this->makeSale('wakrah', 100, 0);
        $this->makeExpense('wakrah', 900);

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        $this->assertSame(-800.0, $byKey['wakrah']['profit']);
        $this->assertSame(-800.0, $byKey['wakrah']['margin_percent']);
    }

    public function test_branches_do_not_leak_into_each_other(): void
    {
        $this->makeSale('old_airport', 5000, 0);
        $this->makeExpense('old_airport', 1000);
        $this->makeSale('wakrah', 200, 0);
        $this->makeExpense('wakrah', 50);

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        $this->assertSame(5000.0, $byKey['old_airport']['sales']);
        $this->assertSame(1000.0, $byKey['old_airport']['expenses']);
        $this->assertSame(200.0, $byKey['wakrah']['sales']);
        $this->assertSame(50.0, $byKey['wakrah']['expenses']);
    }

    public function test_home_service_is_excluded_matching_the_existing_finance_dashboard(): void
    {
        $this->makeSale('home_service', 10000, 0);
        $this->makeExpense('home_service', 1);

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $keys = collect($result)->pluck('key')->all();

        $this->assertSame(['old_airport', 'wakrah'], $keys);
        $this->assertCount(2, $result);
    }

    public function test_date_window_excludes_sales_and_expenses_outside_the_range(): void
    {
        $this->makeSale('old_airport', 100, 0, Carbon::parse('2026-05-31 23:59:59')); // before month start
        $this->makeSale('old_airport', 200, 0, Carbon::parse('2026-06-01 00:00:00')); // exact start
        $this->makeExpense('old_airport', 30, Carbon::parse('2026-06-16 00:00:00')); // after "to"

        $result = (new BranchFinancialSummaryService())->summarize($this->from, $this->to);
        $byKey = collect($result)->keyBy('key');

        $this->assertSame(200.0, $byKey['old_airport']['sales']);
        $this->assertSame(0.0, $byKey['old_airport']['expenses']);
    }
}
