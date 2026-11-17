<?php

namespace App\Http\Controllers;

use App\Models\ProductRdItem;
use App\Models\ProductRdSample;
use App\Models\ProductRdSupplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRdController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = $this->orderedSuppliers();
        $items = ProductRdItem::with(['winningSupplier', 'samples.supplier'])
            ->orderBy('id')
            ->get();

        $regionalRows = $this->buildRegionalRows($items);
        $sampleRows = $this->buildSampleRows($items, $suppliers);
        $activeSupplierNames = collect($sampleRows)
            ->pluck('supplier')
            ->filter()
            ->unique()
            ->values();
        $supplierHeading = $activeSupplierNames->isNotEmpty()
            ? $activeSupplierNames->join(', ', ' & ')
            : '';

        return view('products.rd', [
            'activeTab'       => 'rd',
            'suppliers'       => $suppliers,
            'items'           => $items,
            'regionalRows'    => $regionalRows,
            'sampleRows'      => $sampleRows,
            'supplierHeading' => $supplierHeading,
        ]);
    }

    public function storeItem(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:160',
            'market'                => ['nullable', Rule::in(['both', 'qatar', 'pakistan'])],
            'volume'                => 'nullable|string|max:80',
            'bottle_status'         => ['nullable', Rule::in(['pending', 'finalized', 'not_finalized', 'done'])],
            'qatar_bottle_size'     => 'nullable|string|max:80',
            'pakistan_bottle_size'  => 'nullable|string|max:80',
        ]);

        $marketMode = $validated['market'] ?? 'both';
        $bottleStatus = ProductRdItem::normalizeBottleStatus($validated['bottle_status'] ?? 'pending');
        $stickerStatus = ProductRdItem::normalizeStickerStatus(null, $bottleStatus);

        $defaultVol = trim((string) ($validated['volume'] ?? '')) ?: '250 ml';
        $qaVol = trim((string) ($validated['qatar_bottle_size'] ?? '')) ?: $defaultVol;
        $pkVol = trim((string) ($validated['pakistan_bottle_size'] ?? '')) ?: $defaultVol;

        $item = DB::transaction(function () use ($validated, $marketMode, $bottleStatus, $stickerStatus, $qaVol, $pkVol) {
            $cleanName = trim($validated['name']);
            $existing = ProductRdItem::whereRaw('LOWER(name) = ?', [strtolower($cleanName)])->first();

            if ($existing) {
                if (in_array($marketMode, ['both', 'qatar'], true)) {
                    $existing->qatar_enabled = true;
                    $existing->qatar_bottle_size = $qaVol;
                    $existing->qatar_bottle_status = $bottleStatus;
                    $existing->qatar_sticker_status = $stickerStatus;
                }
                if (in_array($marketMode, ['both', 'pakistan'], true)) {
                    $existing->pakistan_enabled = true;
                    $existing->pakistan_bottle_size = $pkVol;
                    $existing->pakistan_bottle_status = $bottleStatus;
                    $existing->pakistan_sticker_status = $stickerStatus;
                }
                $existing->save();
                return $existing;
            }

            $slugBase = Str::slug($cleanName) ?: 'formula';
            $slug = $slugBase;
            $counter = 2;
            while (ProductRdItem::where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $counter;
                $counter++;
            }

            $item = ProductRdItem::create([
                'name'                    => $cleanName,
                'slug'                    => $slug,
                'qatar_enabled'           => in_array($marketMode, ['both', 'qatar'], true),
                'qatar_bottle_size'       => $qaVol,
                'qatar_bottle_status'     => $bottleStatus,
                'qatar_sticker_status'    => $stickerStatus,
                'pakistan_enabled'        => in_array($marketMode, ['both', 'pakistan'], true),
                'pakistan_bottle_size'    => $pkVol,
                'pakistan_bottle_status'  => $bottleStatus,
                'pakistan_sticker_status' => $stickerStatus,
            ]);

            foreach (ProductRdSupplier::orderBy('id')->get() as $supplier) {
                ProductRdSample::firstOrCreate(
                    [
                        'product_rd_item_id'     => $item->id,
                        'product_rd_supplier_id' => $supplier->id,
                    ],
                    [
                        'version'          => 'V1',
                        'status'           => 'waiting_sample',
                        'verdict'          => 'pending',
                        'next_followup_at' => now()->addDays(3)->toDateString(),
                        'quoted_currency'  => 'QAR',
                    ]
                );
            }

            return $item;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Row added.',
                'item_id' => $item->id,
            ], 201);
        }

        return redirect()->route('products.rd.index')->with('success', 'Row added.');
    }

    public function updateItem(Request $request, ProductRdItem $item)
    {
        $validated = $request->validate([
            'name'                    => 'sometimes|required|string|max:160',
            'winning_supplier_id'     => 'nullable|integer|exists:product_rd_suppliers,id',
            'qatar_bottle_size'       => 'sometimes|required|string|max:80',
            'qatar_bottle_status'     => 'sometimes|required|string|max:32',
            'qatar_sticker_status'    => 'sometimes|required|string|max:32',
            'pakistan_bottle_size'    => 'sometimes|required|string|max:80',
            'pakistan_bottle_status'  => 'sometimes|required|string|max:32',
            'pakistan_sticker_status' => 'sometimes|required|string|max:32',
        ]);

        if (isset($validated['name'])) {
            $item->name = trim($validated['name']);
        }
        if ($request->has('winning_supplier_id')) {
            $item->winning_supplier_id = $validated['winning_supplier_id'] ?: null;
        }

        foreach (['qatar', 'pakistan'] as $market) {
            if (isset($validated["{$market}_bottle_size"])) {
                $item->{"{$market}_bottle_size"} = trim($validated["{$market}_bottle_size"]);
            }
            $prevBottle = $item->normalizedBottleStatus($market);
            $nextBottle = isset($validated["{$market}_bottle_status"])
                ? ProductRdItem::normalizeBottleStatus($validated["{$market}_bottle_status"])
                : $prevBottle;

            $item->{"{$market}_bottle_status"} = $nextBottle;

            if ($nextBottle !== 'finalized') {
                $item->{"{$market}_sticker_status"} = 'blocked_by_bottle';
            } elseif (isset($validated["{$market}_sticker_status"])) {
                $item->{"{$market}_sticker_status"} = ProductRdItem::normalizeStickerStatus($validated["{$market}_sticker_status"], 'finalized');
            } elseif ($prevBottle !== 'finalized') {
                $item->{"{$market}_sticker_status"} = 'ready_for_dieline';
            }
        }

        $item->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Saved',
                'qatar'   => $this->serializeRegionalRow($item, 'qatar'),
                'pakistan' => $this->serializeRegionalRow($item, 'pakistan'),
            ]);
        }

        return redirect()->route('products.rd.index');
    }

    public function destroyRegionalRow(Request $request, ProductRdItem $item, string $market)
    {
        $marketKey = strtolower(trim($market));
        if (!in_array($marketKey, ['qatar', 'pakistan'], true)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Invalid market.'], 422);
            }
            return redirect()->route('products.rd.index');
        }

        DB::transaction(function () use ($item, $marketKey) {
            if ($marketKey === 'pakistan') {
                $item->pakistan_enabled = false;
            } else {
                $item->qatar_enabled = false;
            }
            $item->save();

            if (!$item->qatar_enabled && !$item->pakistan_enabled && $item->samples()->count() === 0) {
                $item->delete();
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Row deleted',
                'item_id' => $item->id,
                'market'  => $marketKey,
            ]);
        }

        return redirect()->route('products.rd.index');
    }

    public function destroyItem(Request $request, ProductRdItem $item)
    {
        $item->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Removed']);
        }

        return redirect()->route('products.rd.index');
    }

    /**
     * Inline AJAX update for Section 1 (Regional SKU & Packaging Baseline):
     * Handles Volume, Bottle Status (Pending | Finalized), and Sticker Status
     * with automatic dependency enforcement.
     */
    public function cyclePackagingStatus(Request $request, ProductRdItem $item)
    {
        $validated = $request->validate([
            'market'         => ['required', Rule::in(['qatar', 'pakistan'])],
            'volume'         => 'nullable|string|max:80',
            'size'           => 'nullable|string|max:80',
            'bottle_status'  => ['nullable', Rule::in(['pending', 'finalized', 'not_finalized', 'done'])],
            'sticker_status' => ['nullable', Rule::in([
                'blocked_by_bottle',
                'ready_for_dieline',
                'in_design',
                'finalized',
                'not_started',
                'designing',
                'done',
            ])],
        ]);

        $market = $validated['market'];
        $volInput = $validated['volume'] ?? $validated['size'] ?? null;

        if ($volInput !== null && trim($volInput) !== '') {
            $item->{"{$market}_bottle_size"} = trim($volInput);
        }

        $prevBottle = $item->normalizedBottleStatus($market);
        $nextBottle = array_key_exists('bottle_status', $validated) && $validated['bottle_status'] !== null
            ? ProductRdItem::normalizeBottleStatus($validated['bottle_status'])
            : $prevBottle;

        $item->{"{$market}_bottle_status"} = $nextBottle;

        if ($nextBottle !== 'finalized') {
            // Whenever Bottle Status is Pending, automatically lock Sticker Status to Blocked by Bottle
            $item->{"{$market}_sticker_status"} = 'blocked_by_bottle';
        } else {
            if (array_key_exists('sticker_status', $validated) && $validated['sticker_status'] !== null) {
                $item->{"{$market}_sticker_status"} = ProductRdItem::normalizeStickerStatus($validated['sticker_status'], 'finalized');
            } elseif ($prevBottle !== 'finalized' || $item->normalizedStickerStatus($market) === 'blocked_by_bottle') {
                // As soon as Bottle Status switches Pending -> Finalized, switch Sticker Status -> Ready for Die-Line
                $item->{"{$market}_sticker_status"} = 'ready_for_dieline';
            } else {
                $item->{"{$market}_sticker_status"} = $item->normalizedStickerStatus($market);
            }
        }

        $item->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Saved',
                'row'     => $this->serializeRegionalRow($item->fresh(), $market),
            ]);
        }

        return redirect()->route('products.rd.index');
    }

    public function saveBattlecard(Request $request, ProductRdItem $item)
    {
        return $this->updateItem($request, $item);
    }

    /**
     * Inline AJAX update for Section 2 (Supplier Sample Evaluation):
     * Handles Sample Status, Smell, Cost (1 or 5, or short text/quote), Texture, Results, Concept Clear, and Verdict.
     */
    public function updateSample(Request $request, ProductRdSample $sample)
    {
        $validated = $request->validate([
            'status'              => ['nullable', Rule::in(array_keys(ProductRdSample::STATUSES))],
            'verdict'             => ['nullable', Rule::in(array_keys(ProductRdSample::VERDICTS))],
            'score_smell'         => 'nullable|integer|in:1,5',
            'score_cost'          => 'nullable|integer|in:1,5',
            'cost'                => 'nullable|string|max:120',
            'cost_note'           => 'nullable|string|max:120',
            'score_texture'       => 'nullable|integer|in:1,5',
            'score_results'       => 'nullable|integer|in:1,5',
            'score_concept_clear' => 'nullable|integer|in:1,5',
        ]);

        DB::transaction(function () use ($request, $sample, $validated) {
            if (array_key_exists('status', $validated) && $validated['status'] !== null) {
                $sample->status = ProductRdSample::normalizeStatus($validated['status']);
            }

            foreach (['score_smell', 'score_texture', 'score_results', 'score_concept_clear'] as $field) {
                if ($request->has($field)) {
                    $raw = $request->input($field);
                    $sample->{$field} = ($raw === '' || $raw === null) ? null : (int) $raw;
                }
            }

            // Support Cost as either 1/5 score OR short text/quote (e.g. "5" or "14.50 QAR")
            if ($request->has('cost') || $request->has('cost_note') || $request->has('score_cost')) {
                if ($request->has('cost') || $request->has('cost_note')) {
                    $rawCost = trim((string) ($request->input('cost') ?? $request->input('cost_note') ?? ''));
                    if ($rawCost === '' || $rawCost === '—') {
                        $sample->cost_note = null;
                        $sample->score_cost = null;
                    } elseif (in_array($rawCost, ['1', '5'], true)) {
                        $sample->score_cost = (int) $rawCost;
                        $sample->cost_note = $rawCost;
                    } else {
                        $sample->cost_note = $rawCost;
                        if ($request->has('score_cost') && $validated['score_cost'] !== null) {
                            $sample->score_cost = (int) $validated['score_cost'];
                        } else {
                            $sample->score_cost = null;
                        }
                    }
                } elseif ($request->has('score_cost')) {
                    $rawScoreCost = $request->input('score_cost');
                    $sample->score_cost = ($rawScoreCost === '' || $rawScoreCost === null) ? null : (int) $rawScoreCost;
                    $sample->cost_note = $sample->score_cost !== null ? (string) $sample->score_cost : null;
                }
            }

            if (array_key_exists('verdict', $validated) && $validated['verdict'] !== null) {
                $verdict = ProductRdSample::normalizeVerdict($validated['verdict']);
                $sample->verdict = $verdict;

                $item = $sample->item;
                if ($item) {
                    if ($verdict === 'selected') {
                        $item->winning_supplier_id = $sample->product_rd_supplier_id;
                        $item->save();
                    } elseif ((int) $item->winning_supplier_id === (int) $sample->product_rd_supplier_id) {
                        $otherSelected = ProductRdSample::where('product_rd_item_id', $item->id)
                            ->where('id', '!=', $sample->id)
                            ->where('verdict', 'selected')
                            ->first();
                        $item->winning_supplier_id = $otherSelected?->product_rd_supplier_id;
                        $item->save();
                    }
                }
            }

            $sample->save();
        });

        $fresh = $sample->fresh(['item', 'supplier']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Saved',
                'sample'  => $this->serializeSampleRow($fresh),
            ]);
        }

        return redirect()->route('products.rd.index');
    }

    public function destroySample(Request $request, ProductRdSample $sample)
    {
        DB::transaction(function () use ($sample) {
            $item = $sample->item;
            $supplierId = (int) $sample->product_rd_supplier_id;
            $sample->delete();

            if ($item && (int) $item->winning_supplier_id === $supplierId) {
                $otherSelected = ProductRdSample::where('product_rd_item_id', $item->id)
                    ->where('verdict', 'selected')
                    ->first();
                $item->winning_supplier_id = $otherSelected?->product_rd_supplier_id;
                $item->save();
            }

            if ($item && !$item->qatar_enabled && !$item->pakistan_enabled && $item->samples()->count() === 0) {
                $item->delete();
            }
        });

        $suppliers = $this->orderedSuppliers();
        $items = ProductRdItem::with(['samples.supplier'])->orderBy('id')->get();
        $remainingSampleRows = $this->buildSampleRows($items, $suppliers);
        $activeSupplierNames = collect($remainingSampleRows)
            ->pluck('supplier')
            ->filter()
            ->unique()
            ->values();
        $supplierHeading = $activeSupplierNames->isNotEmpty()
            ? $activeSupplierNames->join(', ', ' & ')
            : '';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'               => true,
                'message'          => 'Row deleted',
                'supplier_heading' => $supplierHeading,
            ]);
        }

        return redirect()->route('products.rd.index');
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:140|unique:product_rd_suppliers,name',
            'contact_person'    => 'nullable|string|max:140',
            'phone_or_whatsapp' => 'nullable|string|max:80',
            'country'           => 'nullable|string|max:80',
        ]);

        $supplier = DB::transaction(function () use ($validated) {
            $supplier = ProductRdSupplier::create([
                'name'              => trim($validated['name']),
                'contact_person'    => $this->nullIfBlank($validated['contact_person'] ?? null),
                'phone_or_whatsapp' => $this->nullIfBlank($validated['phone_or_whatsapp'] ?? null),
                'country'           => $this->nullIfBlank($validated['country'] ?? null),
            ]);

            foreach (ProductRdItem::orderBy('id')->get() as $item) {
                ProductRdSample::firstOrCreate(
                    [
                        'product_rd_item_id'     => $item->id,
                        'product_rd_supplier_id' => $supplier->id,
                    ],
                    [
                        'version'          => 'V1',
                        'status'           => 'waiting_sample',
                        'verdict'          => 'pending',
                        'next_followup_at' => now()->addDays(3)->toDateString(),
                        'quoted_currency'  => 'QAR',
                    ]
                );
            }

            return $supplier;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => 'Supplier added.',
                'supplier' => $supplier,
            ], 201);
        }

        return redirect()->route('products.rd.index')->with('success', 'Supplier added.');
    }

    public function updateSupplier(Request $request, ProductRdSupplier $supplier)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140', Rule::unique('product_rd_suppliers', 'name')->ignore($supplier->id)],
        ]);

        $supplier->update(['name' => trim($validated['name'])]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'supplier' => $supplier->fresh()]);
        }

        return redirect()->route('products.rd.index');
    }

    public function destroySupplier(Request $request, ProductRdSupplier $supplier)
    {
        if ($supplier->wonItems()->exists()) {
            $msg = sprintf('Cannot delete "%s" while selected as a winning supplier.', $supplier->name);
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()->route('products.rd.index')->with('error', $msg);
        }

        $supplier->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('products.rd.index');
    }

    private function orderedSuppliers()
    {
        return ProductRdSupplier::all()->sortBy(function (ProductRdSupplier $s) {
            $lower = strtolower(trim($s->name));
            if ($lower === 'fist industries') {
                return '00_fist';
            }
            if ($lower === 'glen') {
                return '01_glen';
            }
            return '10_' . sprintf('%06d', $s->id);
        })->values();
    }

    /**
     * Builds Section 1 rows in exact required sequence:
     * 1. Shampoo | Qatar
     * 2. Shampoo | Pakistan
     * 3. Hair Oil | Pakistan
     * 4. Hair Oil | Qatar
     * 5. Hair Mask | Qatar
     * 6. Hair Mask | Pakistan
     * followed by any additional custom SKU rows.
     */
    private function buildRegionalRows($items): array
    {
        $bySlug = $items->keyBy('slug');
        $rows = [];
        $seen = [];

        $pinnedSequence = [
            ['slug' => 'shampoo',   'market' => 'qatar'],
            ['slug' => 'shampoo',   'market' => 'pakistan'],
            ['slug' => 'hair-oil',  'market' => 'pakistan'],
            ['slug' => 'hair-oil',  'market' => 'qatar'],
            ['slug' => 'hair-mask', 'market' => 'qatar'],
            ['slug' => 'hair-mask', 'market' => 'pakistan'],
        ];

        foreach ($pinnedSequence as $pair) {
            $item = $bySlug->get($pair['slug']);
            if (!$item) {
                continue;
            }
            $enabledField = $pair['market'] === 'pakistan' ? 'pakistan_enabled' : 'qatar_enabled';
            if ($item->{$enabledField} === false) {
                continue;
            }
            $rows[] = $this->serializeRegionalRow($item, $pair['market']);
            $seen["{$item->id}:{$pair['market']}"] = true;
        }

        foreach ($items as $item) {
            foreach (['qatar', 'pakistan'] as $market) {
                $key = "{$item->id}:{$market}";
                if (isset($seen[$key])) {
                    continue;
                }
                $enabledField = $market === 'pakistan' ? 'pakistan_enabled' : 'qatar_enabled';
                if ($item->{$enabledField} === false) {
                    continue;
                }
                $rows[] = $this->serializeRegionalRow($item, $market);
            }
        }

        return $rows;
    }

    private function serializeRegionalRow(ProductRdItem $item, string $market): array
    {
        $bottleStatus = $item->normalizedBottleStatus($market);
        $stickerStatus = $item->normalizedStickerStatus($market);
        $sizeField = $market === 'pakistan' ? 'pakistan_bottle_size' : 'qatar_bottle_size';

        return [
            'row_key'              => "{$item->id}_{$market}",
            'item_id'              => (int) $item->id,
            'product'              => $item->name,
            'market'               => $market,
            'market_label'         => $market === 'pakistan' ? 'Pakistan' : 'Qatar',
            'volume'               => $item->{$sizeField},
            'bottle_status'        => $bottleStatus,
            'bottle_status_label'  => ProductRdItem::BOTTLE_STATUSES[$bottleStatus] ?? 'Pending',
            'sticker_status'       => $stickerStatus,
            'sticker_status_label' => ProductRdItem::STICKER_ALL_STATUSES[$stickerStatus] ?? 'Blocked by Bottle',
            'is_sticker_blocked'   => $bottleStatus !== 'finalized',
        ];
    }

    private function buildSampleRows($items, $suppliers): array
    {
        $orderedItems = $items->sortBy(function (ProductRdItem $item) {
            return match ($item->slug) {
                'hair-mask' => '00_hair_mask',
                'hair-oil'  => '01_hair_oil',
                'shampoo'   => '02_shampoo',
                default     => '10_' . sprintf('%06d', $item->id),
            };
        })->values();

        $rows = [];
        foreach ($orderedItems as $item) {
            foreach ($suppliers as $supplier) {
                $sample = $item->samples->firstWhere('product_rd_supplier_id', $supplier->id);
                if ($sample) {
                    $sample->setRelation('item', $item);
                    $sample->setRelation('supplier', $supplier);
                    $rows[] = $this->serializeSampleRow($sample);
                }
            }
        }

        return $rows;
    }

    private function serializeSampleRow(ProductRdSample $sample): array
    {
        $status = ProductRdSample::normalizeStatus($sample->status);
        $verdict = ProductRdSample::normalizeVerdict($sample->verdict);

        return [
            'id'                  => (int) $sample->id,
            'item_id'             => (int) $sample->product_rd_item_id,
            'product'             => $sample->item?->name ?? '',
            'supplier_id'         => (int) $sample->product_rd_supplier_id,
            'supplier'            => $sample->supplier?->name ?? '',
            'status'              => $status,
            'status_label'        => ProductRdSample::STATUSES[$status] ?? 'Waiting Sample',
            'score_smell'         => $sample->score_smell,
            'score_cost'          => $sample->score_cost,
            'cost_display'        => $sample->costDisplay(),
            'score_texture'       => $sample->score_texture,
            'score_results'       => $sample->score_results,
            'score_concept_clear' => $sample->score_concept_clear,
            'verdict'             => $verdict,
            'verdict_label'       => ProductRdSample::VERDICTS[$verdict] ?? 'Pending',
            'average_score'       => $sample->averageScore(),
        ];
    }

    private function nullIfBlank(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $trimmed = trim((string) $val);

        return $trimmed === '' ? null : $trimmed;
    }
}
