<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use App\Models\PartnerTransaction;
use Illuminate\Http\Request;

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
            ->orderBy('category')
            ->pluck('category');

        return view('ecommerce.expenses', [
            'expenses' => $expenses,
            'categories' => $categories,
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
