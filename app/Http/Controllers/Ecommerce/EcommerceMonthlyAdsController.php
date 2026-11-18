<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceMonthlyAd;
use App\Models\EcommerceSale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceMonthlyAdsController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = $request->query('month', now()->format('Y-m'));
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);

        $availableMonths = EcommerceMonthlyAd::query()
            ->forRegion($region)
            ->whereNotNull('plan_month')
            ->distinct()
            ->pluck('plan_month')
            ->push(now()->format('Y-m'))
            ->push(now()->addMonthNoOverflow()->format('Y-m'))
            ->unique()
            ->sort()
            ->values();

        $query = EcommerceMonthlyAd::forRegion($region)->orderByDesc('id');
        if ($selectedMonth !== 'all') {
            $query->where('plan_month', $selectedMonth);
        }
        $ads = $query->get();

        $adsByPhase = collect(EcommerceMonthlyAd::BOARD_PHASES)
            ->mapWithKeys(fn ($phase) => [
                $phase => $ads->filter(fn (EcommerceMonthlyAd $ad) => $ad->normalizedPhase() === $phase)->values(),
            ]);

        $killedAds = $ads->filter(fn (EcommerceMonthlyAd $ad) => $ad->normalizedPhase() === 'Killed')->values();

        $totalPlannedBudget = $ads->sum('total_budget');
        $staff = User::orderBy('name')->get(['id', 'name']);
        $phaseLabels = EcommerceMonthlyAd::BOARD_PHASE_LABELS;

        return view('ecommerce.marketing.ads-plan', compact(
            'adsByPhase',
            'phaseLabels',
            'killedAds',
            'staff',
            'selectedMonth',
            'availableMonths',
            'totalPlannedBudget',
            'region',
            'currency'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['phase'] = 'Planning';

        $regionParam = $request->input('region', $request->query('region'));
        if ($regionParam !== null && trim((string) $regionParam) !== '') {
            $data['region'] = strtoupper(EcommerceSale::normalizeRegion((string) $regionParam));
        } elseif (in_array($data['target_branch'] ?? '', ['Old Airport', 'Wakrah'], true)) {
            $data['region'] = 'QA';
        } else {
            $data['region'] = 'PK';
        }

        $ad = EcommerceMonthlyAd::create($data);

        $params = ['region' => strtolower($data['region'])];
        if ($ad->plan_month && $ad->plan_month !== now()->format('Y-m')) {
            $params['month'] = $ad->plan_month;
        }

        return redirect()->route('ecommerce.marketing.ads-plan.index', $params)->with('success', 'Ad added.');
    }

    public function update(Request $request, EcommerceMonthlyAd $ad)
    {
        $validated = $this->validated($request);
        $ad->update($validated);

        $params = [];
        if ($ad->region) {
            $params['region'] = strtolower($ad->region);
        }
        if ($ad->plan_month && $ad->plan_month !== now()->format('Y-m')) {
            $params['month'] = $ad->plan_month;
        }

        return redirect()->route('ecommerce.marketing.ads-plan.index', $params)->with('success', 'Ad updated.');
    }

    public function destroy(EcommerceMonthlyAd $ad)
    {
        $region = strtolower((string) ($ad->region ?: 'pk'));
        $ad->delete();

        return redirect()->route('ecommerce.marketing.ads-plan.index', ['region' => $region])->with('success', 'Ad deleted.');
    }

    public function completePlanning(Request $request, EcommerceMonthlyAd $ad)
    {
        return $this->completeProduction($request, $ad);
    }

    public function completeProduction(Request $request, EcommerceMonthlyAd $ad)
    {
        if ($ad->normalizedPhase() !== 'Planning') {
            abort(403, 'Only an ad in Planning & Production can move to Testing.');
        }

        if (count($ad->resolvedCreatives()) < 1 && empty($ad->hook_text) && empty($ad->spoken_script)) {
            return redirect()->back()->with('error', 'Add at least 1 creative before moving this ad to Testing.');
        }

        $ad->update([
            'phase'                 => 'Testing',
            'production_started_at' => $ad->production_started_at?->toDateString() ?? now()->toDateString(),
            'testing_started_at'    => now()->toDateString(),
            'testing_ends_at'       => $ad->testing_ends_at?->toDateString() ?? now()->addDays(7)->toDateString(),
        ]);

        $region = strtolower((string) ($ad->region ?: 'pk'));

        return redirect()->route('ecommerce.marketing.ads-plan.index', ['region' => $region])->with('success', 'Moved to Testing.');
    }

    public function scale(Request $request, EcommerceMonthlyAd $ad)
    {
        if ($ad->normalizedPhase() !== 'Testing') {
            abort(403, 'Only an ad in Testing can be scaled.');
        }

        $payload = [
            'phase'      => 'Scaling',
            'verdict_at' => now()->toDateString(),
        ];

        if (empty($ad->winning_creative)) {
            $creatives = $ad->resolvedCreatives();
            if (count($creatives) === 1) {
                $payload['winning_creative'] = 'Creative 1';
            }
        }

        $ad->update($payload);

        $region = strtolower((string) ($ad->region ?: 'pk'));

        return redirect()->route('ecommerce.marketing.ads-plan.index', ['region' => $region])->with('success', 'Moved to Scaling.');
    }

    public function kill(Request $request, EcommerceMonthlyAd $ad)
    {
        if (!in_array($ad->normalizedPhase(), ['Planning', 'Testing', 'Scaling'], true)) {
            abort(403, 'This ad is already in the Graveyard.');
        }

        $ad->update([
            'phase'      => 'Killed',
            'verdict_at' => now()->toDateString(),
        ]);

        $region = strtolower((string) ($ad->region ?: 'pk'));

        return redirect()->route('ecommerce.marketing.ads-plan.index', ['region' => $region])->with('success', 'Ad moved to the Graveyard.');
    }

    private function validated(Request $request): array
    {
        $hasCreativesArray = $request->has('creatives');

        if ($hasCreativesArray && is_array($request->input('creatives'))) {
            $cleanedCreatives = [];
            foreach (array_values($request->input('creatives')) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $type = strtolower(trim((string) ($row['creative_type'] ?? 'reel')));
                $media = strtolower(trim((string) ($row['media_type'] ?? 'video')));
                $size = trim((string) ($row['size'] ?? EcommerceMonthlyAd::defaultSizeForType($type)));
                $refUrl = trim((string) ($row['reference_url'] ?? ''));

                $cleanedCreatives[] = [
                    'name'          => trim((string) ($row['name'] ?? '')),
                    'creative_type' => $type !== '' ? $type : 'reel',
                    'media_type'    => $media !== '' ? $media : 'video',
                    'size'          => $size !== '' ? $size : EcommerceMonthlyAd::defaultSizeForType($type),
                    'reference_url' => $refUrl !== '' ? $refUrl : null,
                ];
            }

            $request->merge(['creatives' => $cleanedCreatives]);
        }

        $rules = [
            'plan_month'              => 'required|date_format:Y-m',
            'ad_name'                 => 'required|string|max:255',
            'person_responsible'      => 'required|string|max:255',
            'pre_filled_message'      => 'nullable|string',
            'offer'                   => 'nullable|string',
            'target_branch'           => ['required', Rule::in(EcommerceMonthlyAd::BRANCHES)],
            'offer_price'             => 'nullable|numeric|min:0',
            'live_date'               => 'nullable|date',
            'raw_data_status'         => ['nullable', Rule::in(EcommerceMonthlyAd::RAW_DATA_STATUSES)],
            'raw_data_deadline'       => 'nullable|date|before_or_equal:final_creative_deadline',
            'final_creative_deadline' => 'nullable|date|before_or_equal:live_date',
            'total_budget'            => 'nullable|numeric|min:0',
            'creative_1_name'         => 'nullable|string|max:255',
            'creative_asset_link'     => 'nullable|string|max:2048',
            'creative_2_name'         => 'nullable|string|max:255',
            'creative_asset_link_2'   => 'nullable|string|max:2048',
            'creative_3_name'         => 'nullable|string|max:255',
            'creative_asset_link_3'   => 'nullable|string|max:2048',
            'txt_hook'                => 'nullable|string',
            'txt_value'               => 'nullable|string',
            'txt_risk'                => 'nullable|string',
            'txt_cta'                 => 'nullable|string',
            'winning_creative'        => ['nullable', 'string', 'max:60', 'regex:/^(Creative\s+\d+|None)$/i'],
            'cost_per_message'        => 'nullable|numeric|min:0',
            'testing_ends_at'         => 'nullable|date',
            'sales_verdict'           => 'nullable|string|max:150',
            'sales_verdict_custom'    => 'nullable|string|max:150',
            'testing_notes'           => 'nullable|string',
        ];

        if ($hasCreativesArray) {
            $rules['creatives'] = 'required|array|min:1|max:30';
            $rules['creatives.*.name'] = 'required|string|max:255';
            $rules['creatives.*.creative_type'] = ['required', Rule::in(array_keys(EcommerceMonthlyAd::CREATIVE_TYPES))];
            $rules['creatives.*.media_type'] = ['required', Rule::in(array_keys(EcommerceMonthlyAd::MEDIA_TYPES))];
            $rules['creatives.*.size'] = ['required', Rule::in(array_keys(EcommerceMonthlyAd::CREATIVE_SIZES))];
            $rules['creatives.*.reference_url'] = 'nullable|url|max:2048';
        }

        $validated = $request->validate($rules, [
            'final_creative_deadline.before_or_equal' => 'The final creative deadline cannot be after the Ad Live Date.',
            'raw_data_deadline.before_or_equal'       => 'The raw data deadline cannot be after the Final Creative Deadline.',
            'creatives.min'                           => 'Please add at least 1 creative.',
            'creatives.*.name.required'               => 'Each creative requires a name or hook.',
            'creatives.*.reference_url.url'           => 'Each reference link must be a valid URL starting with https:// or http://.',
        ]);

        if ($hasCreativesArray) {
            $storedCreatives = [];
            foreach (array_values($validated['creatives']) as $idx => $c) {
                $norm = EcommerceMonthlyAd::normalizeCreativeItem($c, $idx + 1);
                $storedCreatives[] = [
                    'name'          => $norm['name'],
                    'creative_type' => $norm['creative_type'],
                    'media_type'    => $norm['media_type'],
                    'size'          => $norm['size'],
                    'reference_url' => $norm['reference_url'],
                ];
            }
            $validated['creatives'] = $storedCreatives;
            $validated = array_merge($validated, EcommerceMonthlyAd::legacyCreativeColumnsFromList($storedCreatives));
        } elseif (
            !empty($validated['creative_1_name'])
            || !empty($validated['creative_2_name'])
            || !empty($validated['creative_3_name'])
        ) {
            $legacy = [];
            foreach ([
                ['name' => $validated['creative_1_name'] ?? null, 'reference_url' => $validated['creative_asset_link'] ?? null],
                ['name' => $validated['creative_2_name'] ?? null, 'reference_url' => $validated['creative_asset_link_2'] ?? null],
                ['name' => $validated['creative_3_name'] ?? null, 'reference_url' => $validated['creative_asset_link_3'] ?? null],
            ] as $p) {
                $n = trim((string) ($p['name'] ?? ''));
                $u = trim((string) ($p['reference_url'] ?? ''));
                if ($n !== '' || $u !== '') {
                    $legacy[] = [
                        'name'          => $n !== '' ? $n : 'Creative ' . (count($legacy) + 1),
                        'creative_type' => 'reel',
                        'media_type'    => 'video',
                        'size'          => '9:16',
                        'reference_url' => filter_var($u, FILTER_VALIDATE_URL) ? $u : null,
                    ];
                }
            }
            if (!empty($legacy)) {
                $validated['creatives'] = $legacy;
            }
        }

        $validated['raw_data_status'] = ($validated['raw_data_status'] ?? null) ?: 'scheduled';
        if ($validated['raw_data_status'] === 'available') {
            $validated['raw_data_deadline'] = null;
        }

        if ($request->input('sales_verdict') === '__custom__') {
            $request->validate(['sales_verdict_custom' => 'required|string|max:150']);
            $validated['sales_verdict'] = $request->input('sales_verdict_custom');
        }
        unset($validated['sales_verdict_custom']);

        $validated['chk_hook'] = $request->boolean('chk_hook');
        $validated['chk_value'] = $request->boolean('chk_value');
        $validated['chk_risk'] = $request->boolean('chk_risk');
        $validated['chk_cta'] = $request->boolean('chk_cta');

        return $validated;
    }
}
