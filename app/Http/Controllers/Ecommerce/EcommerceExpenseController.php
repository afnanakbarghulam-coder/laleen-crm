<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceComponentType;
use App\Models\EcommerceExpense;
use App\Models\EcommerceProductLine;
use App\Models\EcommerceRawMaterial;
use App\Models\EcommerceSale;
use App\Models\PartnerTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class EcommerceExpenseController extends Controller
{
    public function index(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);

        $expenses = EcommerceExpense::with('creator')
            ->where('currency', $currency)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $totalExpenses = (float) $expenses->sum('amount');
        $totalPool = (float) PartnerTransaction::where('type', 'injection')
            ->where('currency', $currency)
            ->sum('amount');
        $partnerLedgerExpenses = (float) $expenses->where('funding_source', 'partner_ledger')->sum('amount');
        $remainingBalance = $totalPool - $partnerLedgerExpenses;

        $categories = EcommerceExpense::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->merge(EcommerceExpense::NET_PROFIT_DEDUCTION_CATEGORIES)
            ->unique()
            ->sort()
            ->values();

        $vendors = EcommerceExpense::query()
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->distinct()
            ->pluck('vendor')
            ->sort()
            ->values();

        $productLines = EcommerceProductLine::whereIn('name', array_keys(EcommerceProductLine::COMPONENT_TYPES_BY_PRODUCT_LINE))
            ->orderBy('name')
            ->get();

        $componentTypes = EcommerceComponentType::orderBy('name')->get();

        $componentTypesByProductLine = $productLines->mapWithKeys(function (EcommerceProductLine $productLine) use ($componentTypes) {
            $allowedNames = EcommerceProductLine::COMPONENT_TYPES_BY_PRODUCT_LINE[$productLine->name] ?? [];

            $allowed = $componentTypes->whereIn('name', $allowedNames)
                ->map(fn (EcommerceComponentType $componentType) => ['id' => $componentType->id, 'name' => $componentType->name])
                ->values();

            return [$productLine->id => $allowed];
        });

        return view('ecommerce.expenses', [
            'region' => $region,
            'currency' => $currency,
            'expenses' => $expenses,
            'categories' => $categories,
            'vendors' => $vendors,
            'productLines' => $productLines,
            'componentTypes' => $componentTypes,
            'componentTypesByProductLine' => $componentTypesByProductLine,
            'fundingSources' => EcommerceExpense::FUNDING_SOURCES,
            'totalExpenses' => $totalExpenses,
            'totalPool' => $totalPool,
            'remainingBalance' => $remainingBalance,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => ['nullable', Rule::in(['PKR', 'QAR'])],
            'category' => 'required|string|max:100',
            'funding_source' => 'required|in:partner_ledger,sales',
            'vendor' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'includes_raw_material_restock' => 'nullable|boolean',
            'product_line_id' => 'required_if:includes_raw_material_restock,1|nullable|exists:ecommerce_product_lines,id',
            'component_type_id' => 'required_if:includes_raw_material_restock,1|nullable|exists:ecommerce_component_types,id',
            'quantity_received' => 'required_if:includes_raw_material_restock,1|nullable|numeric|min:0.01',
        ]);

        $receiptPath = $this->handleReceipt($request);
        $currency = strtoupper((string) ($validated['currency'] ?? 'PKR'));
        $isRestock = $request->boolean('includes_raw_material_restock')
            && !empty($validated['product_line_id'])
            && !empty($validated['component_type_id'])
            && !empty($validated['quantity_received']);

        $title = $validated['category'] . ' - ' . Carbon::parse($validated['expense_date'])->format('d M Y');

        DB::transaction(function () use ($validated, $currency, $receiptPath, $isRestock, $title) {
            $rawMaterialId = null;
            $quantityReceived = null;

            if ($isRestock) {
                $quantityReceived = (float) $validated['quantity_received'];
                $unitCost = (float) $validated['amount'] / $quantityReceived;

                $rawMaterial = EcommerceRawMaterial::where('product_line_id', $validated['product_line_id'])
                    ->where('component_type_id', $validated['component_type_id'])
                    ->lockForUpdate()
                    ->first();

                if ($rawMaterial) {
                    $rawMaterial->update([
                        'current_stock' => (float) $rawMaterial->current_stock + $quantityReceived,
                        'initial_stock' => (float) $rawMaterial->initial_stock + $quantityReceived,
                        'last_purchased_unit_cost' => $unitCost,
                    ]);
                } else {
                    $productLine = EcommerceProductLine::find($validated['product_line_id']);
                    $componentType = EcommerceComponentType::find($validated['component_type_id']);

                    $rawMaterial = EcommerceRawMaterial::create([
                        'name' => $productLine->name . ' - ' . $componentType->name,
                        'product_line_id' => $productLine->id,
                        'component_type_id' => $componentType->id,
                        'current_stock' => $quantityReceived,
                        'unit_of_measure' => 'units',
                        'last_purchased_unit_cost' => $unitCost,
                    ]);
                }

                $rawMaterialId = $rawMaterial->id;
            }

            EcommerceExpense::create([
                'expense_date' => $validated['expense_date'],
                'title' => $title,
                'amount' => $validated['amount'],
                'currency' => $currency,
                'category' => $validated['category'],
                'funding_source' => $validated['funding_source'],
                'vendor' => $validated['vendor'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'receipt_path' => $receiptPath,
                'includes_raw_material_restock' => $isRestock,
                'ecommerce_raw_material_id' => $rawMaterialId,
                'quantity_received' => $quantityReceived,
                'created_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Expense logged.');
    }

    /**
     * Guard raw material restock deletion:
     * If the expense added raw material stock, verify that those units have not already been
     * consumed by production runs before decrementing current_stock & initial_stock and deleting.
     */
    public function destroy(EcommerceExpense $ecommerceExpense)
    {
        try {
            DB::transaction(function () use ($ecommerceExpense) {
                if ($ecommerceExpense->includes_raw_material_restock && $ecommerceExpense->ecommerce_raw_material_id) {
                    $rawMaterial = EcommerceRawMaterial::lockForUpdate()->find($ecommerceExpense->ecommerce_raw_material_id);
                    $qtyReceived = (float) ($ecommerceExpense->quantity_received ?? 0);

                    if ($rawMaterial && $qtyReceived > 0) {
                        $currentStock = (float) $rawMaterial->current_stock;
                        if ($currentStock < $qtyReceived) {
                            $consumed = round($qtyReceived - $currentStock, 2);
                            throw new RuntimeException(
                                "Cannot delete this restock expense because {$consumed} units of this raw material have already been consumed in production runs."
                            );
                        }

                        $rawMaterial->update([
                            'current_stock' => max(0.0, $currentStock - $qtyReceived),
                            'initial_stock' => max(0.0, (float) $rawMaterial->initial_stock - $qtyReceived),
                        ]);
                    }
                }

                $ecommerceExpense->delete();
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Expense deleted.');
    }

    private function handleReceipt(Request $request): ?string
    {
        if (!$request->hasFile('receipt')) {
            return null;
        }

        $destinationPath = public_path('ecommerce/receipts');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file = $request->file('receipt');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->move($destinationPath, $fileName);

        return 'ecommerce/receipts/' . $fileName;
    }
}
