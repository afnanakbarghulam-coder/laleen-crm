<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use Illuminate\Http\Request;

class EcommercePartnerController extends Controller
{
    public function index()
    {
        $partners = Partner::with(['transactions' => function ($query) {
            $query->orderByDesc('transaction_date');
        }])->orderBy('name')->get();

        $equalization = Partner::equalizationSummary();
        $totalPool = (float) $partners->sum('total_injected');
        $remainingBalance = $totalPool - (float) EcommerceExpense::where('funding_source', 'partner_ledger')->sum('amount');

        $transactions = PartnerTransaction::with('partner')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return view('ecommerce.partners', [
            'partners' => $partners,
            'equalization' => $equalization,
            'totalPool' => $totalPool,
            'remainingBalance' => $remainingBalance,
            'transactions' => $transactions,
            'transactionCategories' => PartnerTransaction::CATEGORIES,
        ]);
    }

    public function storePartner(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'equity_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        Partner::create($validated + ['equity_percentage' => $validated['equity_percentage'] ?? 33.3333]);

        return back()->with('success', 'Partner added successfully.');
    }

    public function destroyPartner(Partner $partner)
    {
        $partner->delete();

        return back()->with('success', 'Partner removed.');
    }

    public function storeTransaction(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'type' => 'required|in:injection',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'nullable|string|max:100',
            'reference_note' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        $partner->transactions()->create($validated);

        return back()->with('success', 'Transaction recorded.');
    }

    public function destroyTransaction(PartnerTransaction $transaction)
    {
        $transaction->delete();

        return back()->with('success', 'Transaction deleted.');
    }
}
