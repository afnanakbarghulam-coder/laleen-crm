<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EcommerceStockController extends Controller
{
    /** Trailing window used to measure how fast each SKU actually moves. */
    private const VELOCITY_WINDOW_DAYS = 30;

    /** Flag a SKU for reorder once cover drops to this many days. */
    private const REORDER_THRESHOLD_DAYS = 21;

    /** Stand-in "effectively infinite" cover for a SKU with no recent movement. */
    private const NO_VELOCITY_DAYS_REMAINING = 999;

    /**
     * Daily sales velocity and days-of-cover per product.
     *
     * Resolved as ONE grouped aggregate rather than a query per row — the
     * obvious $product->sales()->sum() inside the Blade loop is an N+1.
     *
     * Every channel counts, including Backbar Use and Damage/Expiry: those
     * deplete real stock, so they belong in a reorder forecast even though
     * they are excluded from revenue elsewhere.
     */
    private function velocityByProduct($products): array
    {
        $since = now()->subDays(self::VELOCITY_WINDOW_DAYS);

        $unitsByProduct = EcommerceSale::where('created_at', '>=', $since)
            ->groupBy('ecommerce_product_id')
            ->selectRaw('ecommerce_product_id, SUM(quantity) as units')
            ->pluck('units', 'ecommerce_product_id');

        return $products->mapWithKeys(function (EcommerceProduct $product) use ($unitsByProduct) {
            $units = (float) ($unitsByProduct[$product->id] ?? 0);
            $dailyVelocity = $units / self::VELOCITY_WINDOW_DAYS;
            $stockOnHand = (float) $product->stock_pakistan + (float) $product->stock_qatar;

            $daysRemaining = $dailyVelocity > 0
                ? $stockOnHand / $dailyVelocity
                : self::NO_VELOCITY_DAYS_REMAINING;

            return [$product->id => [
                'units_sold' => $units,
                'daily_velocity' => $dailyVelocity,
                'stock_on_hand' => $stockOnHand,
                'days_remaining' => $daysRemaining,
                'needs_reorder' => $daysRemaining <= self::REORDER_THRESHOLD_DAYS,
                'is_stalled' => $dailyVelocity <= 0,
            ]];
        })->all();
    }

    public function index(Request $request)
    {
        // Defaults to month-to-date, matching the Sales & Usage / Net Profit
        // filters. This range scopes historical data ONLY (the Batch History
        // modal) — current stock counts and Total Capital are always live.
        $from = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $products = EcommerceProduct::with(['productionRuns' => function ($query) {
            $query->with('creator')->orderByDesc('created_at');
        }])->orderBy('name')->get();

        // Keyed by product id so the view can look up each product's saved
        // BOM cost (Liquid + Bottle + Label + Box + Pump) from the Pricing &
        // Profit sandbox — products with no saved pricing model cost PKR 0.
        $pricingModels = EcommercePricingModel::all()->keyBy('ecommerce_product_id');

        // Total Capital in Inventory: every unsold unit (Pakistan + Qatar)
        // valued at its BOM cost per unit, summed across all finished goods.
        // Always live/unfiltered — never scoped by the date range above.
        $totalCapitalInInventory = $products->sum(function (EcommerceProduct $product) use ($pricingModels) {
            $unitCost = $pricingModels->get($product->id)?->bomUnitCost() ?? 0.0;
            $totalStock = (float) $product->stock_pakistan + (float) $product->stock_qatar;

            return $totalStock * $unitCost;
        });

        return view('ecommerce.stock', [
            'products' => $products,
            'pricingModels' => $pricingModels,
            'velocity' => $this->velocityByProduct($products),
            'velocityWindowDays' => self::VELOCITY_WINDOW_DAYS,
            'reorderThresholdDays' => self::REORDER_THRESHOLD_DAYS,
            'totalCapitalInInventory' => $totalCapitalInInventory,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
            'historyRangeFrom' => $from,
            'historyRangeTo' => $to,
        ]);
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $quantity = (float) $validated['quantity'];

        try {
            DB::transaction(function () use ($validated, $quantity) {
                // The sufficiency check has to happen AFTER the row lock and
                // inside the transaction — checking first and locking second
                // let two concurrent transfers both pass validation against
                // the same pre-decrement stock and overdraw Pakistan.
                $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);

                if ($quantity > (float) $product->stock_pakistan) {
                    throw new RuntimeException("Insufficient stock in Pakistan for {$product->name}. Requested: " . number_format($quantity, 2) . ', Available: ' . number_format((float) $product->stock_pakistan, 2) . '.');
                }

                $product->decrement('stock_pakistan', $quantity);
                $product->increment('stock_qatar', $quantity);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Stock transferred to Qatar.');
    }
}
