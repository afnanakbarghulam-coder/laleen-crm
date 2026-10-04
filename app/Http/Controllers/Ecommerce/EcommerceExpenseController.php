<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceComponentType;
use App\Models\EcommerceExpense;
use App\Models\EcommerceProductLine;
use App\Models\EcommerceRawMaterial;
use App\Models\PartnerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceExpenseController extends Controller
{
    public function index()
    {
        $expenses = EcommerceExpense::with('creator')->orderByDesc('expense_date')->get();

        $totalExpenses = (float) $expenses->sum('amount');
        $totalPool = (float) PartnerTransaction::where('type', 'injection')->sum('amount');
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

        $productLines = EcommerceProductLine::orderBy('name')->get();
        $componentTypes = EcommerceComponentType::orderBy('name')->get();

        return view('ecommerce.expenses', [
            'expenses' => $expenses,
            'categories' => $categories,
            'productLines' => $productLines,
            'componentTypes' => $componentTypes,
            'fundingSources' => EcommerceExpense::FUNDING_SOURCES,
            'totalExpenses' => $totalExpenses,
            'totalPool' => $totalPool,
            'remainingBalance' => $remainingBalance,
        ]);
    }

    public function store(Request $request)
    {
        // The category combobox and its "+ Add New Category" text input swap
        // which element owns name="category" client-side, so exactly one of
        // them is ever present here — the selected category or the typed one.
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
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
        $isRestock = $request->boolean('includes_raw_material_restock')
            && !empty($validated['product_line_id'])
            && !empty($validated['component_type_id'])
            && !empty($validated['quantity_received']);

        DB::transaction(function () use ($validated, $receiptPath, $isRestock) {
            EcommerceExpense::create([
                'expense_date' => $validated['expense_date'],
                'title' => $validated['title'],
                'amount' => $validated['amount'],
                'category' => $validated['category'],
                'funding_source' => $validated['funding_source'],
                'vendor' => $validated['vendor'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'receipt_path' => $receiptPath,
                'created_by' => auth()->id(),
            ]);

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
                        'last_purchased_unit_cost' => $unitCost,
                    ]);
                } else {
                    $productLine = EcommerceProductLine::find($validated['product_line_id']);
                    $componentType = EcommerceComponentType::find($validated['component_type_id']);

                    EcommerceRawMaterial::create([
                        'name' => $productLine->name . ' - ' . $componentType->name,
                        'product_line_id' => $productLine->id,
                        'component_type_id' => $componentType->id,
                        'current_stock' => $quantityReceived,
                        'unit_of_measure' => 'units',
                        'last_purchased_unit_cost' => $unitCost,
                    ]);
                }
            }
        });

        return back()->with('success', 'Expense logged.');
    }

    public function destroy(EcommerceExpense $ecommerceExpense)
    {
        $ecommerceExpense->delete();

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
