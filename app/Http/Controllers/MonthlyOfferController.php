<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\MonthlyOffer;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'services:id,name,category_id,price,duration',
            'services.category:id,name',
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
        $catalogServices = $canEditOffers
            ? Service::with('category:id,name')->orderBy('name')->get(['id', 'name', 'category_id', 'price', 'duration'])
            : collect();

        $layoutView = $user->isSalonStaff() ? 'layouts.portal' : 'layouts.app';

        return view('services.offers-standalone', compact(
            'monthlyOffers',
            'todayQatar',
            'canEditOffers',
            'combos',
            'freebiePackages',
            'catalogServices',
            'layoutView'
        ));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->canEdit('services'), 403, 'You do not have permission to create monthly offers.');

        [$attributes, $serviceIds] = $this->validateOffer($request);
        $attributes['created_by'] = Auth::id();
        $attributes['is_active'] = $request->boolean('is_active', true);

        DB::transaction(function () use ($attributes, $serviceIds) {
            $offer = MonthlyOffer::create($attributes);
            $offer->services()->sync($serviceIds);
        });

        return redirect()->route('services.index', ['tab' => 'offers'])
            ->with('success', 'Monthly offer published successfully.');
    }

    public function update(Request $request, MonthlyOffer $monthlyOffer)
    {
        abort_unless($request->user()?->canEdit('services'), 403, 'You do not have permission to edit monthly offers.');

        [$attributes, $serviceIds] = $this->validateOffer($request);
        $attributes['is_active'] = $request->boolean('is_active', $monthlyOffer->is_active);

        DB::transaction(function () use ($monthlyOffer, $attributes, $serviceIds) {
            $monthlyOffer->update($attributes);
            $monthlyOffer->services()->sync($serviceIds);
        });

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

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
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
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['required', 'integer', 'exists:services,id'],
            'promo_price_qar' => ['nullable', 'required_if:offer_type,standalone', 'numeric', 'min:0', 'max:999999.99'],
            'branch_scope' => ['required', Rule::in(array_keys(MonthlyOffer::BRANCH_SCOPES))],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'terms_and_conditions' => ['required', 'string', 'max:5000'],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
            'dream_outcome' => ['required', 'string', 'max:5000'],
            'problem_solved' => ['required', 'string', 'max:5000'],
            'core_value_additions' => ['required', 'string', 'max:5000'],
            'value_stack_bonuses' => ['required', 'string', 'max:5000'],
            'conditional_guarantee' => ['required', 'string', 'max:5000'],
            'transparent_pricing' => ['required', 'string', 'max:5000'],
            'capacity_scarcity' => ['required', 'string', 'max:5000'],
            'whatsapp_script' => ['nullable', 'string', 'max:30000'],
            'video_shooting_script' => ['nullable', 'string', 'max:30000'],
        ]);

        $offerType = $validated['offer_type'];
        $blankErrors = [];

        $terms = trim((string) $validated['terms_and_conditions']);
        if ($terms === '') {
            $blankErrors['terms_and_conditions'] = ['Terms & Conditions are required for every monthly offer.'];
        }

        $cleanedPillars = [];
        foreach (MonthlyOffer::PILLAR_DEFINITIONS as $pillar) {
            $key = $pillar['key'];
            $val = trim((string) ($validated[$key] ?? ''));
            if ($val === '') {
                $blankErrors[$key] = ["Pillar {$pillar['number']} ({$pillar['label']}) is required."];
            }
            $cleanedPillars[$key] = $val;
        }

        if (!empty($blankErrors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($blankErrors);
        }

        $waScript = isset($validated['whatsapp_script']) && trim((string) $validated['whatsapp_script']) !== ''
            ? trim((string) $validated['whatsapp_script'])
            : null;
        $videoScript = isset($validated['video_shooting_script']) && trim((string) $validated['video_shooting_script']) !== ''
            ? trim((string) $validated['video_shooting_script'])
            : null;

        $serviceIds = $offerType === MonthlyOffer::TYPE_STANDALONE
            ? array_values(array_unique(array_map('intval', $validated['service_ids'] ?? [])))
            : [];

        $attributes = array_merge([
            'title' => trim((string) $validated['title']),
            'offer_type' => $offerType,
            'combo_id' => $offerType === MonthlyOffer::TYPE_COMBO ? (int) $validated['combo_id'] : null,
            'service_freebie_id' => $offerType === MonthlyOffer::TYPE_SERVICE_FREEBIE ? (int) $validated['service_freebie_id'] : null,
            'promo_price_qar' => $offerType === MonthlyOffer::TYPE_STANDALONE
                ? round((float) $validated['promo_price_qar'], 2)
                : (isset($validated['promo_price_qar']) && $validated['promo_price_qar'] !== '' ? round((float) $validated['promo_price_qar'], 2) : null),
            'branch_scope' => $validated['branch_scope'],
            'valid_from' => Carbon::parse($validated['valid_from'], 'Asia/Qatar')->toDateString(),
            'valid_until' => Carbon::parse($validated['valid_until'], 'Asia/Qatar')->toDateString(),
            'terms_and_conditions' => $terms,
            'staff_notes' => isset($validated['staff_notes']) && trim((string) $validated['staff_notes']) !== ''
                ? trim((string) $validated['staff_notes'])
                : null,
            'whatsapp_script' => $waScript,
            'video_shooting_script' => $videoScript,
        ], $cleanedPillars);

        return [$attributes, $serviceIds];
    }
}
