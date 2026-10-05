<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceStockController extends Controller
{
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
            $unitCost = $this->bomUnitCost($pricingModels->get($product->id));
            $totalStock = (float) $product->stock_pakistan + (float) $product->stock_qatar;

            return $totalStock * $unitCost;
        });

        return view('ecommerce.stock', [
            'products' => $products,
            'pricingModels' => $pricingModels,
            'totalCapitalInInventory' => $totalCapitalInInventory,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
            'historyRangeFrom' => $from,
            'historyRangeTo' => $to,
        ]);
    }

    private function bomUnitCost(?EcommercePricingModel $pricingModel): float
    {
        if (!$pricingModel) {
            return 0.0;
        }

        return (float) $pricingModel->liquid_cost
            + (float) $pricingModel->bottle_cost
            + (float) $pricingModel->label_cost
            + (float) $pricingModel->box_cost
            + (float) $pricingModel->pump_cost;
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $quantity = (float) $validated['quantity'];
        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        if ($quantity > (float) $product->stock_pakistan) {
            return back()->withErrors(['error' => "Insufficient stock in Pakistan for {$product->name}. Requested: " . number_format($quantity, 2) . ', Available: ' . number_format((float) $product->stock_pakistan, 2) . '.']);
        }

        DB::transaction(function () use ($validated, $quantity) {
            $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
            $product->decrement('stock_pakistan', $quantity);
            $product->increment('stock_qatar', $quantity);
        });

        return back()->with('success', 'Stock transferred to Qatar.');
    }
}
