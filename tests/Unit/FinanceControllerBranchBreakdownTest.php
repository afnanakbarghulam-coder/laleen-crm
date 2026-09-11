<?php

namespace Tests\Unit;

use App\Http\Controllers\FinanceController;
use App\Models\Expense;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Stage 3C regression guard: FinanceController::branchBreakdown() was
 * refactored to delegate to BranchFinancialSummaryService. This confirms
 * its external contract - the exact return shape and values every
 * existing caller (UserController::dashboard(), revenue/index.blade.php)
 * relies on - is unchanged after the extraction.
 */
class FinanceControllerBranchBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeSale(string $branch, float $services, float $products): Sale
    {
        return Sale::create([
            'branch' => $branch,
            'services_total' => $services,
            'products_total' => $products,
            'total_amount' => $services + $products,
        ]);
    }

    private function makeExpense(string $branch, float $amount): Expense
    {
        return Expense::create([
            'branch' => $branch,
            'category' => 'Other',
            'amount' => $amount,
            'expense_date' => now(),
        ]);
    }

    public function test_return_shape_still_has_every_key_existing_callers_read(): void
    {
        $this->makeSale('old_airport', 1000, 0);
        $this->makeExpense('old_airport', 400);

        $result = FinanceController::branchBreakdown(now()->startOfMonth(), now()->endOfDay());

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Old Airport, Al Wakrah - home_service still excluded

        foreach ($result as $row) {
            foreach (['key', 'label', 'sales', 'expenses', 'profit'] as $requiredKey) {
                $this->assertArrayHasKey($requiredKey, $row);
            }
        }
    }

    public function test_values_match_what_the_pre_extraction_formula_would_produce(): void
    {
        $this->makeSale('old_airport', 1000, 200);
        $this->makeExpense('old_airport', 300);
        $this->makeSale('wakrah', 500, 0);
        $this->makeExpense('wakrah', 600);

        $result = FinanceController::branchBreakdown(now()->startOfMonth(), now()->endOfDay());
        $byKey = collect($result)->keyBy('key');

        // Old Airport: sales 1000+200=1200, expenses 300, profit 900.
        $this->assertSame('Old Airport', $byKey['old_airport']['label']);
        $this->assertSame(1200.0, $byKey['old_airport']['sales']);
        $this->assertSame(300.0, $byKey['old_airport']['expenses']);
        $this->assertSame(900.0, $byKey['old_airport']['profit']);

        // Al Wakrah: sales 500, expenses 600, profit -100 (a loss, not hidden).
        $this->assertSame('Al Wakrah', $byKey['wakrah']['label']);
        $this->assertSame(500.0, $byKey['wakrah']['sales']);
        $this->assertSame(600.0, $byKey['wakrah']['expenses']);
        $this->assertSame(-100.0, $byKey['wakrah']['profit']);
    }

    public function test_branch_order_and_keys_match_the_original_branches_constant(): void
    {
        $result = FinanceController::branchBreakdown(now()->startOfMonth(), now()->endOfDay());

        $this->assertSame(['old_airport', 'wakrah'], array_column($result, 'key'));
        $this->assertSame(['Old Airport', 'Al Wakrah'], array_column($result, 'label'));
    }
}
