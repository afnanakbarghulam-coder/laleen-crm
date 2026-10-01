<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use Illuminate\Http\Request;

class EcommerceExpenseController extends Controller
{
    public function index()
    {
        $expenses = EcommerceExpense::with('creator')->orderByDesc('expense_date')->get();

        return view('ecommerce.expenses', [
            'expenses' => $expenses,
            'categories' => EcommerceExpense::CATEGORIES,
            'totalExpenses' => (float) $expenses->sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|in:' . implode(',', EcommerceExpense::CATEGORIES),
            'vendor' => 'nullable|string|max:255',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $receiptPath = $this->handleReceipt($request);

        EcommerceExpense::create([
            ...$validated,
            'receipt_path' => $receiptPath,
            'created_by' => auth()->id(),
        ]);

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
