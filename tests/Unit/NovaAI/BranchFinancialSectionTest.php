<?php

namespace Tests\Unit\NovaAI;

use App\Models\Expense;
use App\Models\Sale;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 3C: the branch-financial-performance snapshot section. Real
 * Sale/Expense rows via RefreshDatabase (rolled back automatically -
 * this project has no isolated test database). No real Gemini calls.
 */
class BranchFinancialSectionTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_URL_PATTERN = 'generativelanguage.googleapis.com/*';
    private const FROZEN_NOW = '2026-06-15 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-gemini-key']);
        config(['services.gemini.model' => 'gemini-3.5-flash-lite']);

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));
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

    private function fakeSuccess(): void
    {
        Http::fake([
            self::GEMINI_URL_PATTERN => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'ok']]]],
                ],
            ], 200),
        ]);
    }

    private function userTurnText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['contents'][0]['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    private function systemInstructionText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['systemInstruction']['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    public function test_branch_financial_section_appears_with_correct_figures_in_the_user_turn(): void
    {
        $this->makeSale('old_airport', 1000, 0);
        $this->makeExpense('old_airport', 400);
        $this->makeSale('wakrah', 500, 0);
        $this->makeExpense('wakrah', 600);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is branch profitability looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString('BRANCH FINANCIAL PERFORMANCE', $text);
        $this->assertStringContainsString(
            'Old Airport: 1000.00 QAR revenue, 400.00 QAR recorded expenses, CRM net profit 600.00 QAR, CRM profit margin 60%',
            $text
        );
        $this->assertStringContainsString(
            'Al Wakrah: 500.00 QAR revenue, 600.00 QAR recorded expenses, CRM net profit -100.00 QAR, CRM profit margin -20%',
            $text
        );
        $this->assertStringContainsString(
            'Combined (Old Airport + Al Wakrah): 1500.00 QAR revenue, 1000.00 QAR recorded expenses, CRM net profit 500.00 QAR',
            $text
        );
    }

    public function test_zero_revenue_branch_shows_na_margin_not_zero_percent(): void
    {
        $this->makeExpense('old_airport', 250);
        // No sales at all this month - branch financial section must not fabricate 0%.

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is branch profitability looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString(
            'Old Airport: 0.00 QAR revenue, 250.00 QAR recorded expenses, CRM net profit -250.00 QAR, CRM profit margin N/A',
            $text
        );
    }

    public function test_home_service_caveat_and_net_profit_definition_are_present(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How is branch profitability looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString('Home Service is not included in this branch financial breakdown', $text);
        $this->assertStringContainsString('CRM net profit = recorded sales revenue minus recorded expenses', $text);
    }

    public function test_branch_financial_data_is_in_the_user_turn_not_the_system_instruction(): void
    {
        $this->makeSale('old_airport', 1000, 0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is branch profitability looking?');

        $systemText = $this->systemInstructionText();

        $this->assertStringNotContainsString('BRANCH FINANCIAL PERFORMANCE', $systemText);
        $this->assertStringNotContainsString('1000.00 QAR', $systemText);
    }

    public function test_system_instruction_carries_the_crm_net_profit_evidence_caveat(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How is branch profitability looking?');

        $systemText = $this->systemInstructionText();
        $normalized = preg_replace('/\s+/', ' ', $systemText);

        $this->assertStringContainsStringIgnoringCase(
            'not proof that every real-world business cost has been captured',
            $normalized
        );
        $this->assertStringContainsStringIgnoringCase('contribution margin', $normalized);
    }
}
