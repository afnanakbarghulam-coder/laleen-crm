<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use App\Models\EcommerceSale;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EcommercePartnerController extends Controller
{
    public function index(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);

        $partners = Partner::withTransactionTotals($currency)->orderBy('name')->get();

        $equalization = Partner::equalizationSummary($partners, $currency);
        $totalPool = (float) $partners->sum('total_injected');
        $remainingBalance = $totalPool - (float) EcommerceExpense::where('funding_source', 'partner_ledger')
            ->where('currency', $currency)
            ->sum('amount');

        $transactions = PartnerTransaction::with('partner')
            ->where('currency', $currency)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        // Preload lifetime transaction counts & totals per partner across all currencies
        // so the Delete Partner modal warns accurately before cascade deletion.
        $lifetimeGrouped = PartnerTransaction::query()
            ->selectRaw('partner_id, COALESCE(currency, ?) as tx_currency, COUNT(*) as tx_count, SUM(amount) as tx_total', [$currency])
            ->groupBy('partner_id', 'tx_currency')
            ->get()
            ->groupBy('partner_id');

        $partnerDeleteMeta = [];
        foreach ($partners as $partner) {
            $rows = $lifetimeGrouped->get($partner->id, collect());
            $totalCount = (int) $rows->sum('tx_count');
            $amountParts = $rows
                ->filter(fn ($r) => (float) $r->tx_total > 0)
                ->map(fn ($r) => strtoupper((string) $r->tx_currency) . ' ' . number_format((float) $r->tx_total, 2))
                ->values()
                ->all();

            $partnerDeleteMeta[$partner->id] = [
                'count' => $totalCount,
                'summary' => implode(' + ', $amountParts) ?: ($currency . ' 0.00'),
            ];
        }

        return view('ecommerce.partners', [
            'region' => $region,
            'currency' => $currency,
            'partners' => $partners,
            'equalization' => $equalization,
            'totalPool' => $totalPool,
            'remainingBalance' => $remainingBalance,
            'transactions' => $transactions,
            'transactionCategories' => PartnerTransaction::allCategories(),
            'partnerDeleteMeta' => $partnerDeleteMeta,
        ]);
    }

    public function storePartner(Request $request)
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = mb_strtolower(trim((string) $value));
                    if ($normalized !== '' && Partner::whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->exists()) {
                        $fail('A partner with this name already exists in the Partner Ledger.');
                    }
                },
            ],
            'email' => 'nullable|email|max:255',
            'equity_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $partner = Partner::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'equity_percentage' => $validated['equity_percentage'] ?? 33.3333,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'partner' => $partner,
            ], 201);
        }

        return $this->redirectToLedger($request)->with('success', 'Partner added successfully.');
    }

    public function destroyPartner(Request $request, Partner $partner)
    {
        $partnerName = $partner->name;

        DB::transaction(function () use ($partner): void {
            $partner->transactions()->delete();
            $partner->delete();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Partner {$partnerName} removed.",
            ]);
        }

        return $this->redirectToLedger($request)->with('success', "Partner {$partnerName} removed.");
    }

    public function storeCategory(Request $request)
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $canonical = PartnerTransaction::ensureCategoryPersisted(
            $validated['name'],
            $request->user()?->id
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'category' => $canonical,
                'categories' => PartnerTransaction::allCategories(),
            ]);
        }

        return $this->redirectToLedger($request)->with('success', "Category \"{$canonical}\" added.");
    }

    public function storeTransaction(Request $request, Partner $partner)
    {
        $request->merge([
            'category' => trim((string) $request->input('category')),
        ]);

        $validated = $request->validate([
            'type' => 'required|in:injection,distribution',
            'amount' => 'required|numeric|min:0.01',
            'currency' => ['nullable', Rule::in(['PKR', 'QAR'])],
            'category' => 'required|string|max:100',
            'reference_note' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        $validated['currency'] = strtoupper((string) ($validated['currency'] ?? 'PKR'));
        $validated['category'] = PartnerTransaction::ensureCategoryPersisted(
            $validated['category'],
            $request->user()?->id
        );

        $partner->transactions()->create($validated);

        $label = $validated['type'] === 'distribution' ? 'Distribution' : 'Injection';

        return $this->redirectToLedger($request)->with('success', $label . ' recorded.');
    }

    public function destroyTransaction(Request $request, PartnerTransaction $transaction)
    {
        $transaction->delete();

        return $this->redirectToLedger($request)->with('success', 'Transaction deleted.');
    }

    private function redirectToLedger(Request $request)
    {
        if ($request->filled('region')) {
            return redirect()->route('ecommerce.partners.index', [
                'region' => EcommerceSale::normalizeRegion($request->input('region')),
            ]);
        }

        return back();
    }
}
