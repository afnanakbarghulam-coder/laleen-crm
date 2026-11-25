<?php

namespace App\Http\Controllers;

use App\Models\DailyBranchClose;
use App\Models\SaleItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockWeek;
use App\Models\StockWeekEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->canView('stock'), 403, 'You do not have permission to access Stock.');

        $activeTab = in_array($request->query('tab'), ['audit', 'order', 'catalog'], true)
            ? (string) $request->query('tab')
            : 'audit';

        $scopedBranch = $actor->isSalonManager() ? $actor->managedCanonicalBranch() : null;
        $branch = $this->resolveBranchOrAbort($actor, $request->query('branch'));

        $bounds = StockWeek::weekBoundsFor($request->query('week'));
        $stockWeek = $this->ensureWeekWithEntries($branch, $bounds['start_str']);
        $stockWeek->load([
            'submitter:id,name',
            'approver:id,name',
            'entries' => fn ($q) => $q->with('item')->orderBy('id'),
        ]);

        // Determine whether Opening (Sat) is editable: Admin can always adjust open/revision weeks;
        // Salon Manager can enter initial Opening (Sat) on their fresh-start Week 1 (no prior approved week).
        $hasPriorApprovedWeek = StockWeek::query()
            ->where('branch', $branch)
            ->where('status', StockWeek::STATUS_APPROVED)
            ->whereDate('week_start_date', '<', $bounds['start_str'])
            ->exists();

        $isAdmin = $actor->isAdmin();
        $isOpsLead = $actor->isOpsLead();
        $isManager = $actor->isSalonManager();
        $canEditStock = $actor->canEdit('stock');

        $canEnterCounts = $canEditStock && (
            ($isAdmin && !$stockWeek->isApproved())
            || ($isManager && $stockWeek->isEditableByManager())
        );
        $canEditOpening = $canEnterCounts && ($isAdmin || !$hasPriorApprovedWeek);
        $canLogRestock = $canEditStock && !$stockWeek->isApproved() && ($isAdmin || $isOpsLead || ($isManager && $stockWeek->isEditableByManager()));
        $canTransfer = $canEditStock && !$stockWeek->isApproved() && ($isAdmin || $isOpsLead || ($isManager && $stockWeek->isEditableByManager()));
        $canManageCatalog = $isAdmin;

        // Sort entries neatly by item_type (backbar first, then retail) then name
        $entries = $stockWeek->entries
            ->filter(fn (StockWeekEntry $e) => $e->item !== null)
            ->sortBy(fn (StockWeekEntry $e) => [
                $e->item->item_type === StockItem::TYPE_BACKBAR ? 0 : 1,
                strtolower((string) $e->item->name),
            ])
            ->values();

        // Check if next week exists and has carried-forward openings
        $nextWeekStartStr = Carbon::parse($bounds['start_str'], 'Asia/Qatar')->addDays(7)->toDateString();
        $prevWeekStartStr = Carbon::parse($bounds['start_str'], 'Asia/Qatar')->subDays(7)->toDateString();
        $nextWeek = StockWeek::where('branch', $branch)->whereDate('week_start_date', $nextWeekStartStr)->first();

        // Build Need to Order list for the active branch (and for both branches if Admin/Ops Lead)
        $orderListByBranch = [];
        $orderBranches = $scopedBranch ? [$scopedBranch] : array_keys(StockWeek::BRANCHES);
        foreach ($orderBranches as $ob) {
            $obWeek = $ob === $branch
                ? $stockWeek
                : $this->ensureWeekWithEntries($ob, $bounds['start_str'])->load('entries.item');

            $rows = [];
            foreach ($obWeek->entries as $entry) {
                if (!$entry->item || !$entry->item->is_active) {
                    continue;
                }
                $par = $entry->item->parForBranch($ob);
                $need = $entry->needToOrderQty($par);
                if ($need > 0) {
                    $rows[] = [
                        'item' => $entry->item,
                        'entry' => $entry,
                        'current_qty' => $entry->currentEffectiveQty(),
                        'par_level' => $par,
                        'order_qty' => $need,
                    ];
                }
            }
            usort($rows, fn ($a, $b) => strcmp(strtolower($a['item']->name), strtolower($b['item']->name)));
            $orderListByBranch[$ob] = [
                'branch' => $ob,
                'branch_label' => StockWeek::branchLabel($ob),
                'week' => $obWeek,
                'rows' => $rows,
                'whatsapp_text' => $this->buildWhatsappOrderText(StockWeek::branchLabel($ob), $obWeek->rangeLabel(), $rows),
            ];
        }

        $recentMovements = StockMovement::with(['item:id,name,unit', 'creator:id,name'])
            ->where('stock_week_id', $stockWeek->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $catalogItems = StockItem::with('product:id,sku,price,stock_quantity')
            ->orderByRaw("CASE WHEN item_type = 'backbar' THEN 0 ELSE 1 END asc")
            ->orderBy('name')
            ->get();

        $pendingApprovalWeeks = $isAdmin
            ? StockWeek::with('submitter:id,name')
                ->where('status', StockWeek::STATUS_SUBMITTED)
                ->orderByDesc('week_start_date')
                ->get()
            : collect();

        return view('stock.index', compact(
            'activeTab',
            'branch',
            'scopedBranch',
            'bounds',
            'stockWeek',
            'entries',
            'nextWeek',
            'nextWeekStartStr',
            'prevWeekStartStr',
            'hasPriorApprovedWeek',
            'isAdmin',
            'isOpsLead',
            'isManager',
            'canEnterCounts',
            'canEditOpening',
            'canLogRestock',
            'canTransfer',
            'canManageCatalog',
            'orderListByBranch',
            'recentMovements',
            'catalogItems',
            'pendingApprovalWeeks'
        ));
    }

    /**
     * Log a `+ Received` restock delivery into the active Sat–Fri week.
     */
    public function storeMovement(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->canEdit('stock'), 403, 'You do not have permission to log stock deliveries.');

        $branch = $this->resolveBranchOrAbort($actor, $request->input('branch'));

        $validated = $request->validate([
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'week_start_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        $bounds = StockWeek::weekBoundsFor($validated['week_start_date'] ?? null);
        $week = $this->ensureWeekWithEntries($branch, $bounds['start_str']);

        if ($week->isApproved()) {
            abort(403, 'Cannot log deliveries into an approved weekly sheet.');
        }
        if ($actor->isSalonManager() && !$week->isEditableByManager()) {
            abort(403, 'This weekly sheet is submitted and locked pending Admin approval.');
        }

        $qty = round((float) $validated['quantity'], 2);
        $item = StockItem::findOrFail((int) $validated['stock_item_id']);

        DB::transaction(function () use ($week, $item, $branch, $qty, $validated, $actor) {
            $entry = StockWeekEntry::firstOrCreate(
                ['stock_week_id' => $week->id, 'stock_item_id' => $item->id],
                ['opening_qty' => 0, 'received_qty' => 0, 'transfer_net_qty' => 0, 'retail_sold_qty' => 0]
            );

            $entry->received_qty = round((float) $entry->received_qty + $qty, 2);
            $entry->used_qty = $entry->computeUsedQty();
            $entry->save();

            StockMovement::create([
                'stock_week_id' => $week->id,
                'stock_item_id' => $item->id,
                'branch' => $branch,
                'type' => StockMovement::TYPE_RECEIVED,
                'quantity' => $qty,
                'reference_note' => isset($validated['reference_note']) && trim((string) $validated['reference_note']) !== ''
                    ? trim((string) $validated['reference_note'])
                    : null,
                'created_by' => $actor->id,
            ]);
        });

        return redirect()->route('stock.index', [
            'tab' => 'audit',
            'branch' => $branch,
            'week' => $bounds['start_str'],
        ])->with('success', "Logged +" . StockWeekEntry::formatQty($qty) . " {$item->unit} received for {$item->name}.");
    }

    /**
     * Atomically transfer stock from one branch to the other for the selected Sat–Fri week.
     */
    public function transfer(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->canEdit('stock'), 403, 'You do not have permission to transfer stock.');

        $fromBranch = StockWeek::normalizeBranch($request->input('from_branch'));
        $toBranch = StockWeek::normalizeBranch($request->input('to_branch'));

        $validated = $request->validate([
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'from_branch' => ['required', 'string'],
            'to_branch' => ['required', 'string'],
            'week_start_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$fromBranch || !$toBranch || $fromBranch === $toBranch) {
            return back()->withErrors(['to_branch' => 'Source and destination branches must be different valid branches.']);
        }

        if ($actor->isSalonManager()) {
            $managed = $actor->managedCanonicalBranch();
            abort_unless(
                $managed !== null && $managed === $fromBranch,
                403,
                'You can only initiate stock transfers out of your assigned branch.'
            );
        }

        $bounds = StockWeek::weekBoundsFor($validated['week_start_date'] ?? null);
        $sourceWeek = $this->ensureWeekWithEntries($fromBranch, $bounds['start_str']);
        $destWeek = $this->ensureWeekWithEntries($toBranch, $bounds['start_str']);

        if ($sourceWeek->isApproved() || $destWeek->isApproved()) {
            abort(403, 'Cannot transfer stock when either branch week is already approved.');
        }
        if ($actor->isSalonManager() && !$sourceWeek->isEditableByManager()) {
            abort(403, 'Your branch weekly sheet is submitted and locked pending Admin approval.');
        }

        $qty = round((float) $validated['quantity'], 2);
        $item = StockItem::findOrFail((int) $validated['stock_item_id']);
        $note = isset($validated['reference_note']) && trim((string) $validated['reference_note']) !== ''
            ? trim((string) $validated['reference_note'])
            : null;

        DB::transaction(function () use ($sourceWeek, $destWeek, $item, $fromBranch, $toBranch, $qty, $note, $actor) {
            $srcEntry = StockWeekEntry::firstOrCreate(
                ['stock_week_id' => $sourceWeek->id, 'stock_item_id' => $item->id],
                ['opening_qty' => 0, 'received_qty' => 0, 'transfer_net_qty' => 0, 'retail_sold_qty' => 0]
            );
            $srcEntry->transfer_net_qty = round((float) $srcEntry->transfer_net_qty - $qty, 2);
            $srcEntry->used_qty = $srcEntry->computeUsedQty();
            $srcEntry->save();

            StockMovement::create([
                'stock_week_id' => $sourceWeek->id,
                'stock_item_id' => $item->id,
                'branch' => $fromBranch,
                'type' => StockMovement::TYPE_TRANSFER_OUT,
                'quantity' => $qty,
                'counterparty_branch' => $toBranch,
                'reference_note' => $note,
                'created_by' => $actor->id,
            ]);

            $dstEntry = StockWeekEntry::firstOrCreate(
                ['stock_week_id' => $destWeek->id, 'stock_item_id' => $item->id],
                ['opening_qty' => 0, 'received_qty' => 0, 'transfer_net_qty' => 0, 'retail_sold_qty' => 0]
            );
            $dstEntry->transfer_net_qty = round((float) $dstEntry->transfer_net_qty + $qty, 2);
            $dstEntry->used_qty = $dstEntry->computeUsedQty();
            $dstEntry->save();

            StockMovement::create([
                'stock_week_id' => $destWeek->id,
                'stock_item_id' => $item->id,
                'branch' => $toBranch,
                'type' => StockMovement::TYPE_TRANSFER_IN,
                'quantity' => $qty,
                'counterparty_branch' => $fromBranch,
                'reference_note' => $note,
                'created_by' => $actor->id,
            ]);
        });

        return redirect()->route('stock.index', [
            'tab' => 'audit',
            'branch' => $fromBranch,
            'week' => $bounds['start_str'],
        ])->with('success', 'Transferred ' . StockWeekEntry::formatQty($qty) . " {$item->unit} of {$item->name} from " . StockWeek::branchLabel($fromBranch) . ' to ' . StockWeek::branchLabel($toBranch) . '.');
    }

    /**
     * Save draft Closing (Fri) counts (and Opening Sat on fresh-start Week 1 or Admin).
     */
    public function saveCounts(Request $request, StockWeek $stockWeek)
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorizeWeekCountMutation($actor, $stockWeek);

        $this->persistEntriesFromRequest($request, $stockWeek, $actor);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Weekly counts saved.']);
        }

        return redirect()->route('stock.index', [
            'tab' => 'audit',
            'branch' => $stockWeek->branch,
            'week' => $stockWeek->week_start_date->toDateString(),
        ])->with('success', 'Weekly stock counts saved.');
    }

    /**
     * Submit weekly count for Admin approval (locks Salon Manager edits).
     */
    public function submit(Request $request, StockWeek $stockWeek)
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorizeWeekCountMutation($actor, $stockWeek);

        if ($request->has('entries')) {
            $this->persistEntriesFromRequest($request, $stockWeek, $actor);
        }

        $stockWeek->update([
            'status' => StockWeek::STATUS_SUBMITTED,
            'submitted_by' => $actor->id,
            'submitted_at' => Carbon::now('Asia/Qatar'),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'status' => $stockWeek->status]);
        }

        return redirect()->route('stock.index', [
            'tab' => 'audit',
            'branch' => $stockWeek->branch,
            'week' => $stockWeek->week_start_date->toDateString(),
        ])->with('success', 'Weekly audit submitted for Admin approval.');
    }

    /**
     * Admin-only: Approve weekly sheet permanently & auto carry-forward Friday Closing -> next Saturday Opening.
     */
    public function approve(Request $request, StockWeek $stockWeek)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403, 'Only an Admin can approve weekly stock sheets.');

        if ($request->has('entries')) {
            $this->persistEntriesFromRequest($request, $stockWeek, $actor);
        }

        $adminNote = $request->filled('admin_note') ? trim((string) $request->input('admin_note')) : $stockWeek->admin_note;

        DB::transaction(function () use ($stockWeek, $actor, $adminNote) {
            $stockWeek->update([
                'status' => StockWeek::STATUS_APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => Carbon::now('Asia/Qatar'),
                'admin_note' => $adminNote,
            ]);

            $nextStart = $stockWeek->week_start_date->copy()->addDays(7);
            $nextEnd = $nextStart->copy()->addDays(6);

            $nextWeek = StockWeek::where('branch', $stockWeek->branch)
                ->whereDate('week_start_date', $nextStart->toDateString())
                ->first();

            if (!$nextWeek) {
                $nextWeek = StockWeek::create([
                    'branch' => $stockWeek->branch,
                    'week_start_date' => $nextStart->toDateString(),
                    'week_end_date' => $nextEnd->toDateString(),
                    'status' => StockWeek::STATUS_OPEN,
                ]);
            }

            $currentEntries = $stockWeek->entries()->get();
            foreach ($currentEntries as $entry) {
                $carryOpening = $entry->closing_qty !== null
                    ? round((float) $entry->closing_qty, 2)
                    : $entry->currentEffectiveQty();

                $nextEntry = StockWeekEntry::firstOrCreate(
                    [
                        'stock_week_id' => $nextWeek->id,
                        'stock_item_id' => $entry->stock_item_id,
                    ],
                    [
                        'opening_qty' => $carryOpening,
                        'received_qty' => 0,
                        'transfer_net_qty' => 0,
                        'retail_sold_qty' => 0,
                    ]
                );

                if (!$nextWeek->isApproved()) {
                    $nextEntry->opening_qty = $carryOpening;
                    $nextEntry->used_qty = $nextEntry->computeUsedQty();
                    $nextEntry->save();
                }
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'status' => StockWeek::STATUS_APPROVED]);
        }

        return redirect()->route('stock.index', [
            'tab' => 'audit',
            'branch' => $stockWeek->branch,
            'week' => $stockWeek->week_start_date->toDateString(),
        ])->with('success', 'Weekly stock audit approved and closing balances carried forward to next Saturday.');
    }

    /**
     * Admin-only: Request revision with a short note, unlocking the sheet for the Salon Manager.
     */
    public function requestRevision(Request $request, StockWeek $stockWeek)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403, 'Only an Admin can request revisions.');

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ]);

        $stockWeek->update([
            'status' => StockWeek::STATUS_REVISION_REQUESTED,
            'admin_note' => trim($validated['admin_note']),
            'approved_by' => null,
            'approved_at' => null,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'status' => StockWeek::STATUS_REVISION_REQUESTED]);
        }

        return redirect()->route('stock.index', [
            'tab' => 'audit',
            'branch' => $stockWeek->branch,
            'week' => $stockWeek->week_start_date->toDateString(),
        ])->with('success', 'Revision requested — sheet unlocked for branch manager.');
    }

    /**
     * Admin-only: Unlock a submitted/approved week back to open.
     */
    public function unlock(Request $request, StockWeek $stockWeek)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only an Admin can unlock weekly stock sheets.');

        $stockWeek->update([
            'status' => StockWeek::STATUS_OPEN,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return redirect()->route('stock.index', [
            'tab' => 'audit',
            'branch' => $stockWeek->branch,
            'week' => $stockWeek->week_start_date->toDateString(),
        ])->with('success', 'Weekly stock sheet unlocked.');
    }

    /**
     * Admin-only: Add a new Backbar Supply item to the Stock Catalog.
     */
    public function storeItem(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only an Admin can manage Stock Catalog items.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(StockItem::CATEGORIES))],
            'unit' => ['required', 'string', 'max:40'],
            'par_level_old_airport' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'par_level_al_wakrah' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);

        StockItem::create([
            'name' => trim($validated['name']),
            'item_type' => StockItem::TYPE_BACKBAR,
            'category' => $validated['category'],
            'unit' => trim($validated['unit']),
            'par_level_old_airport' => round((float) ($validated['par_level_old_airport'] ?? 0), 2),
            'par_level_al_wakrah' => round((float) ($validated['par_level_al_wakrah'] ?? 0), 2),
            'is_active' => true,
        ]);

        return redirect()->route('stock.index', ['tab' => 'catalog'])
            ->with('success', 'Backbar supply item added to Stock Catalog.');
    }

    /**
     * Admin-only: Update item details / branch par levels (supports both Backbar & synced Retail items).
     */
    public function updateItem(Request $request, StockItem $stockItem)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only an Admin can update Stock Catalog items.');

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::in(array_keys(StockItem::CATEGORIES))],
            'unit' => ['sometimes', 'required', 'string', 'max:40'],
            'par_level_old_airport' => ['required', 'numeric', 'min:0', 'max:999999'],
            'par_level_al_wakrah' => ['required', 'numeric', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $payload = [
            'par_level_old_airport' => round((float) $validated['par_level_old_airport'], 2),
            'par_level_al_wakrah' => round((float) $validated['par_level_al_wakrah'], 2),
        ];

        if ($stockItem->item_type === StockItem::TYPE_BACKBAR) {
            if (array_key_exists('name', $validated)) {
                $payload['name'] = trim($validated['name']);
            }
            if (array_key_exists('category', $validated)) {
                $payload['category'] = $validated['category'];
            }
            if (array_key_exists('unit', $validated)) {
                $payload['unit'] = trim($validated['unit']);
            }
            if (array_key_exists('is_active', $validated)) {
                $payload['is_active'] = (bool) $validated['is_active'];
            }
        } elseif (array_key_exists('unit', $validated)) {
            $payload['unit'] = trim($validated['unit']);
        }

        $stockItem->update($payload);

        return redirect()->route('stock.index', ['tab' => 'catalog'])
            ->with('success', "Updated {$stockItem->name} par levels.");
    }

    /**
     * Admin-only: Archive/deactivate a Backbar item.
     */
    public function destroyItem(Request $request, StockItem $stockItem)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only an Admin can remove Stock Catalog items.');

        if ($stockItem->item_type === StockItem::TYPE_RETAIL) {
            return redirect()->route('stock.index', ['tab' => 'catalog'])
                ->with('error', 'Synced Retail Products are managed in the Products catalog.');
        }

        $stockItem->update(['is_active' => false]);

        return redirect()->route('stock.index', ['tab' => 'catalog'])
            ->with('success', "Archived {$stockItem->name} from active stock sheets.");
    }

    private function resolveBranchOrAbort(User $actor, ?string $requested): string
    {
        $norm = $requested !== null && trim($requested) !== ''
            ? StockWeek::normalizeBranch($requested)
            : null;

        if ($actor->isSalonManager()) {
            $managed = $actor->managedCanonicalBranch();
            abort_unless($managed !== null, 403, 'No salon branch assigned to your manager profile.');
            if ($norm !== null && $norm !== $managed) {
                abort(403, 'You can only access Stock for your assigned branch.');
            }

            return $managed;
        }

        return $norm ?? 'old_airport';
    }

    private function authorizeWeekCountMutation(?User $actor, StockWeek $stockWeek): void
    {
        abort_unless($actor && $actor->canEdit('stock'), 403, 'You do not have permission to edit stock counts.');

        if ($actor->isAdmin()) {
            abort_if($stockWeek->isApproved(), 403, 'Unlock this approved week before editing counts.');

            return;
        }

        // Only branch Salon Managers can submit/edit Friday counts (Ops Lead logs restocks & views order lists)
        abort_unless($actor->isSalonManager(), 403, 'Only the branch Salon Manager or Admin can submit weekly closing counts.');

        $managed = $actor->managedCanonicalBranch();
        abort_unless($managed !== null && $managed === $stockWeek->branch, 403, 'You can only edit weekly stock sheets for your assigned branch.');
        abort_unless($stockWeek->isEditableByManager(), 403, 'This weekly sheet is locked after submission or approval.');
    }

    private function persistEntriesFromRequest(Request $request, StockWeek $stockWeek, User $actor): void
    {
        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.opening_qty' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'entries.*.closing_qty' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'entries.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $hasPriorApproved = StockWeek::query()
            ->where('branch', $stockWeek->branch)
            ->where('status', StockWeek::STATUS_APPROVED)
            ->whereDate('week_start_date', '<', $stockWeek->week_start_date->toDateString())
            ->exists();
        $allowOpeningEdit = $actor->isAdmin() || !$hasPriorApproved;

        $existingEntries = $stockWeek->entries()->get()->keyBy('stock_item_id');

        DB::transaction(function () use ($validated, $stockWeek, $existingEntries, $allowOpeningEdit, $actor) {
            foreach ($validated['entries'] as $stockItemId => $row) {
                /** @var StockWeekEntry|null $entry */
                $entry = $existingEntries->get((int) $stockItemId);
                if (!$entry) {
                    continue;
                }

                if ($allowOpeningEdit && array_key_exists('opening_qty', $row) && $row['opening_qty'] !== null && $row['opening_qty'] !== '') {
                    $newOpening = round((float) $row['opening_qty'], 2);
                    if (abs($newOpening - (float) $entry->opening_qty) >= 0.01) {
                        $delta = round($newOpening - (float) $entry->opening_qty, 2);
                        $entry->opening_qty = $newOpening;
                        StockMovement::create([
                            'stock_week_id' => $stockWeek->id,
                            'stock_item_id' => $entry->stock_item_id,
                            'branch' => $stockWeek->branch,
                            'type' => StockMovement::TYPE_OPENING_ADJUSTMENT,
                            'quantity' => $delta,
                            'reference_note' => 'Opening balance set',
                            'created_by' => $actor->id,
                        ]);
                    }
                }

                if (array_key_exists('closing_qty', $row)) {
                    $entry->closing_qty = ($row['closing_qty'] !== null && $row['closing_qty'] !== '')
                        ? round((float) $row['closing_qty'], 2)
                        : null;
                }

                if (array_key_exists('notes', $row)) {
                    $trimmedNote = trim((string) ($row['notes'] ?? ''));
                    $entry->notes = $trimmedNote !== '' ? $trimmedNote : null;
                }

                $entry->used_qty = $entry->computeUsedQty();
                $entry->save();
            }
        });
    }

    private function ensureWeekWithEntries(string $branch, string $weekStartStr): StockWeek
    {
        $bounds = StockWeek::weekBoundsFor($weekStartStr);

        $week = StockWeek::where('branch', $branch)
            ->whereDate('week_start_date', $bounds['start_str'])
            ->first();

        if (!$week) {
            $week = StockWeek::create([
                'branch' => $branch,
                'week_start_date' => $bounds['start_str'],
                'week_end_date' => $bounds['end_str'],
                'status' => StockWeek::STATUS_OPEN,
            ]);
        }

        $activeItems = StockItem::where('is_active', true)->orderBy('id')->get();
        $existingEntries = $week->entries()->get()->keyBy('stock_item_id');

        // Look up most recent prior approved week for carry-forward defaults
        $priorApprovedWeek = StockWeek::where('branch', $branch)
            ->where('status', StockWeek::STATUS_APPROVED)
            ->whereDate('week_start_date', '<', $bounds['start_str'])
            ->orderByDesc('week_start_date')
            ->first();
        $priorEntries = $priorApprovedWeek
            ? $priorApprovedWeek->entries()->get()->keyBy('stock_item_id')
            : collect();

        // Compute Sat–Fri completed retail sales per product_id for this branch
        $retailSoldByProductId = $this->retailSoldQuantities($branch, $bounds['start'], $bounds['end']);

        foreach ($activeItems as $item) {
            $soldQty = $item->product_id
                ? round((float) ($retailSoldByProductId[$item->product_id] ?? 0), 2)
                : 0.0;

            /** @var StockWeekEntry|null $entry */
            $entry = $existingEntries->get($item->id);
            if (!$entry) {
                $prior = $priorEntries->get($item->id);
                $opening = $prior
                    ? ($prior->closing_qty !== null ? round((float) $prior->closing_qty, 2) : $prior->currentEffectiveQty())
                    : 0.0;

                StockWeekEntry::create([
                    'stock_week_id' => $week->id,
                    'stock_item_id' => $item->id,
                    'opening_qty' => $opening,
                    'received_qty' => 0,
                    'transfer_net_qty' => 0,
                    'closing_qty' => null,
                    'used_qty' => null,
                    'retail_sold_qty' => $soldQty,
                ]);
            } elseif (!$week->isApproved() && abs((float) $entry->retail_sold_qty - $soldQty) >= 0.01) {
                $entry->update(['retail_sold_qty' => $soldQty]);
            }
        }

        return $week;
    }

    /**
     * @return Collection<int, float>
     */
    private function retailSoldQuantities(string $branch, Carbon $start, Carbon $end): Collection
    {
        $branchMatchValues = DailyBranchClose::salesBranchMatchValues($branch);

        return SaleItem::query()
            ->where('type', 'product')
            ->whereNotNull('product_id')
            ->whereHas('sale', function ($q) use ($branchMatchValues, $start, $end) {
                $q->whereIn('branch', $branchMatchValues)
                    ->whereBetween('created_at', [$start, $end]);
            })
            ->selectRaw('product_id, SUM(quantity) as qty_sum')
            ->groupBy('product_id')
            ->pluck('qty_sum', 'product_id')
            ->map(fn ($v) => round((float) $v, 2));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function buildWhatsappOrderText(string $branchLabel, string $rangeLabel, array $rows): string
    {
        if (empty($rows)) {
            return "✅ *{$branchLabel} Stock Order* ({$rangeLabel})\nAll items are at or above Par Level.";
        }

        $lines = [
            "📦 *Laleen Stock Order — {$branchLabel}*",
            "📅 Audit Week: {$rangeLabel}",
            '',
        ];

        foreach ($rows as $row) {
            /** @var StockItem $item */
            $item = $row['item'];
            $orderQty = StockWeekEntry::formatQty($row['order_qty']);
            $curQty = StockWeekEntry::formatQty($row['current_qty']);
            $parQty = StockWeekEntry::formatQty($row['par_level']);
            $lines[] = "• {$item->name}: *Order {$orderQty} {$item->unit}* (Current: {$curQty} / Par: {$parQty})";
        }

        return implode("\n", $lines);
    }
}
