<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceContentCalendar;
use App\Models\EcommerceMonthlyAd;
use App\Services\Ecommerce\EcommerceAiMarketingGeneratorService;
use Illuminate\Http\Request;

class EcommerceMarketingController extends Controller
{
    public function __construct(
        protected EcommerceAiMarketingGeneratorService $generatorService
    ) {
    }

    /**
     * GET /ecommerce/hub/marketing or /ecommerce/marketing
     */
    public function index(Request $request)
    {
        $contentLive = EcommerceContentCalendar::where(function ($q) {
            $q->where('is_reel_posted', true)
                ->orWhere('are_stories_posted', true)
                ->orWhere('status', 'Live on Shopify/Meta');
        })->count();

        return view('ecommerce.marketing.hub', [
            'adsInFlight' => EcommerceMonthlyAd::whereIn('phase', EcommerceMonthlyAd::BOARD_PHASES)->count(),
            'adsScaling' => EcommerceMonthlyAd::where('phase', 'Scaling')->count(),
            'contentSlots' => EcommerceContentCalendar::count(),
            'contentLive' => $contentLive,
        ]);
    }

    /**
     * POST /ecommerce/hub/marketing/ai-generate-ads
     * Generates or refreshes 20 high-converting video ad scripts in `ecommerce_monthly_ads`.
     */
    public function generateMonthlyAdsWithAi(Request $request)
    {
        $month = (string) ($request->input('plan_month') ?: $request->input('month') ?: now()->format('Y-m'));
        if ($month === 'all' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $region = (string) ($request->input('region') ?: 'pk');
        $ads = $this->generatorService->generateMonthlyAds($month, $region, $request->user()?->name);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'generated_count' => $ads->count(),
                'plan_month' => $month,
                'region' => strtoupper($region),
            ]);
        }

        return redirect()
            ->route('ecommerce.marketing.ads-plan.index', ['month' => $month])
            ->with('success', "⚡ AI Generated {$ads->count()} Monthly Video Ad Scripts for {$month} using Dara Denney, Savannah Sanchez, Ezra Firestone & Alex Hormozi frameworks.");
    }

    /**
     * POST /ecommerce/hub/marketing/ai-generate-calendar
     * Populates all 28 slots (Weeks 1–4, Monday–Sunday) of `ecommerce_content_calendar`.
     */
    public function generateContentCalendarWithAi(Request $request)
    {
        $week = $request->input('week_start_date') ?: $request->input('week') ?: now()->startOfWeek()->toDateString();
        $region = (string) ($request->input('region') ?: 'pk');

        $slots = $this->generatorService->generateFourBySevenContentCalendar((string) $week, $region, $request->user()?->name);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'generated_count' => $slots->count(),
                'week_start_date' => $slots->first()?->week_start_date,
                'region' => strtoupper($region),
            ]);
        }

        return redirect()
            ->route('ecommerce.marketing.content.index', ['week' => $slots->first()?->week_start_date ?? $week])
            ->with('success', "⚡ AI Populated {$slots->count()} Content Calendar Slots (4 Weeks × 7 Days) with Hooks, Visual Direction, and Target SKUs.");
    }
}
