<?php

namespace Tests\Unit;

use App\Http\Controllers\FinanceController;
use App\Models\Expense;
use App\Models\Sale;
use App\Support\BranchFinancialSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Read-only safety pass: FinanceController::branchBreakdown() was restored
 * to its original, independent implementation - it no longer delegates to
 * BranchFinancialSummaryService (Nova's own read-only copy of the same
 * formula). This test is the parity guard that replaces that delegation:
 * it proves, for identical fixture data and date range, that Nova's
 * calculation never drifts from the CRM's own trusted figure, without
 * either implementation depending on the other.
 */
class BranchFinancialParityTest extends TestCase
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

    public function test_nova_service_and_finance_controller_produce_identical_revenue_expenses_and_profit(): void
    {
        $this->makeSale('old_airport', 1200, 300);
        $this->makeSale('old_airport', 400, 0);
        $this->makeExpense('old_airport', 500);
        $this->makeSale('wakrah', 100, 0);
        $this->makeExpense('wakrah', 900);
        // Home Service data exists but is excluded by both, identically.
        $this->makeSale('home_service', 5000, 0);

        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $crmTruth = collect(FinanceController::branchBreakdown($from, $to))->keyBy('key');
        $novaCopy = collect((new BranchFinancialSummaryService())->summarize($from, $to))->keyBy('key');

        $this->assertSame(['old_airport', 'wakrah'], $crmTruth->keys()->all());
        $this->assertSame(['old_airport', 'wakrah'], $novaCopy->keys()->all());

        foreach (['old_airport', 'wakrah'] as $key) {
            $this->assertSame(
                $crmTruth[$key]['sales'],
                $novaCopy[$key]['sales'],
                "Revenue mismatch for {$key} between FinanceController and BranchFinancialSummaryService."
            );
            $this->assertSame(
                $crmTruth[$key]['expenses'],
                $novaCopy[$key]['expenses'],
                "Expenses mismatch for {$key} between FinanceController and BranchFinancialSummaryService."
            );
            $this->assertSame(
                $crmTruth[$key]['profit'],
                $novaCopy[$key]['profit'],
                "Profit mismatch for {$key} between FinanceController and BranchFinancialSummaryService."
            );
            // FinanceController::branchBreakdown() has never returned a
            // margin - that key is Nova-specific and is not part of the
            // parity contract.
            $this->assertArrayNotHasKey('margin_percent', $crmTruth[$key]);
        }
    }

    public function test_finance_controller_no_longer_delegates_to_the_nova_service(): void
    {
        // Read-only rule regression guard: if branchBreakdown() ever starts
        // calling into BranchFinancialSummaryService again, the two
        // implementations stop being independent and this parity test
        // stops proving anything - fail loudly if that happens.
        $source = file_get_contents(app_path('Http/Controllers/FinanceController.php'));

        $this->assertStringNotContainsString('BranchFinancialSummaryService', $source);
    }
}
