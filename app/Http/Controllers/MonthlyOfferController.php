<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\MonthlyOffer;
use App\Models\ServiceFreebiePackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MonthlyOfferController extends Controller
{
    /**
     * Universal Read-Only Monthly Offers Board accessible to ALL operational roles
     * (Admin, Salon Manager, Salon Staff, Ops Lead, Sales Coordinator, Chat Agent,
     * Marketing Specialist, Social Media Editor). Strictly HTTP 403 for Legal Team.
     */
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user && $user->canViewMonthlyOffers(), 403, 'You do not have permission to view Monthly Offers.');

        $todayQatar = Carbon::now('Asia/Qatar');
        $monthlyOffers = MonthlyOffer::with([
            'combo.services:id,name,price,duration',
            'freebiePackage.anchorService:id,name,price,duration',
            'freebiePackage.eligibleServices:id,name,price,duration',
            'creator:id,name',
        ])
            ->orderByDesc('is_active')
            ->orderBy('valid_until')
            ->orderByDesc('id')
            ->get();

        $canEditOffers = $user->canEdit('services');
        $combos = $canEditOffers ? Combo::with('services')->orderBy('name')->get() : collect();
        $freebiePackages = $canEditOffers
            ? ServiceFreebiePackage::with(['anchorService', 'eligibleServices'])->orderBy('name')->get()
            : collect();

        $layoutView = $user->isSalonStaff() ? 'layouts.portal' : 'layouts.app';

        return view('services.offers-standalone', compact(
            'monthlyOffers',
            'todayQatar',
            'canEditOffers',
            'combos',
            'freebiePackages',
            'layoutView'
        ));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->canEdit('services'), 403, 'You do not have permission to create monthly offers.');

        $validated = $this->validateOffer($request);
        $validated['created_by'] = Auth::id();
        $validated['is_active'] = $request->boolean('is_active', true);

        MonthlyOffer::create($validated);

        return redirect()->route('services.index', ['tab' => 'offers'])
            ->with('success', 'Monthly offer published successfully.');
    }

    public function update(Request $request, MonthlyOffer $monthlyOffer)
    {
        abort_unless($request->user()?->canEdit('services'), 403, 'You do not have permission to edit monthly offers.');

        $validated = $this->validateOffer($request);
        $validated['is_active'] = $request->boolean('is_active', $monthlyOffer->is_active);

        $monthlyOffer->update($validated);

        return redirect()->route('services.index', ['tab' => 'offers'])
            ->with('success', 'Monthly offer updated successfully.');
    }

    public function toggle(Request $request, MonthlyOffer $monthlyOffer)
    {
        abort_unless($request->user()?->canEdit('services'), 403, 'You do not have permission to update monthly offers.');

        $monthlyOffer->update([
            'is_active' => !$monthlyOffer->is_active,
        ]);

        $label = $monthlyOffer->is_active ? 'activated' : 'archived';

        return redirect()->route('services.index', ['tab' => 'offers'])
            ->with('success', "Monthly offer {$label}.");
    }

    public function destroy(Request $request, MonthlyOffer $monthlyOffer)
    {
        abort_unless($request->user()?->canEdit('services'), 403, 'You do not have permission to delete monthly offers.');

        $monthlyOffer->delete();

        return redirect()->route('services.index', ['tab' => 'offers'])
            ->with('success', 'Monthly offer deleted.');
    }

    private function validateOffer(Request $request): array
    {
        $rawScope = strtolower(trim((string) $request->input('branch_scope', 'all')));
        if ($rawScope === 'wakrah') {
            $request->merge(['branch_scope' => 'al_wakrah']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'offer_type' => ['required', Rule::in(array_keys(MonthlyOffer::OFFER_TYPES))],
            'combo_id' => ['nullable', 'required_if:offer_type,combo', 'integer', 'exists:combos,id'],
            'service_freebie_id' => ['nullable', 'required_if:offer_type,service_freebie', 'integer', 'exists:service_freebie_packages,id'],
            'promo_price_qar' => ['nullable', 'required_if:offer_type,standalone', 'numeric', 'min:0', 'max:999999.99'],
            'original_value_qar' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'branch_scope' => ['required', Rule::in(array_keys(MonthlyOffer::BRANCH_SCOPES))],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'terms_and_conditions' => ['required', 'string', 'max:5000'],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $offerType = $validated['offer_type'];
        $terms = trim((string) $validated['terms_and_conditions']);
        if ($terms === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'terms_and_conditions' => ['Terms & Conditions are required for every monthly offer.'],
            ]);
        }

        return [
            'title' => trim((string) $validated['title']),
            'offer_type' => $offerType,
            'combo_id' => $offerType === MonthlyOffer::TYPE_COMBO ? (int) $validated['combo_id'] : null,
            'service_freebie_id' => $offerType === MonthlyOffer::TYPE_SERVICE_FREEBIE ? (int) $validated['service_freebie_id'] : null,
            'promo_price_qar' => $offerType === MonthlyOffer::TYPE_STANDALONE
                ? round((float) $validated['promo_price_qar'], 2)
                : (isset($validated['promo_price_qar']) && $validated['promo_price_qar'] !== '' ? round((float) $validated['promo_price_qar'], 2) : null),
            'original_value_qar' => isset($validated['original_value_qar']) && $validated['original_value_qar'] !== ''
                ? round((float) $validated['original_value_qar'], 2)
                : null,
            'branch_scope' => $validated['branch_scope'],
            'valid_from' => Carbon::parse($validated['valid_from'], 'Asia/Qatar')->toDateString(),
            'valid_until' => Carbon::parse($validated['valid_until'], 'Asia/Qatar')->toDateString(),
            'terms_and_conditions' => $terms,
            'staff_notes' => isset($validated['staff_notes']) && trim((string) $validated['staff_notes']) !== ''
                ? trim((string) $validated['staff_notes'])
                : null,
        ];
    }
}
