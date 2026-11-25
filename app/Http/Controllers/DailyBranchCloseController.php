<?php

namespace App\Http\Controllers;

use App\Models\DailyBranchClose;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DailyBranchCloseController extends Controller
{
    /**
     * Submit a Blind End-of-Day (EOD) Cash & POS Close for a branch + Asia/Qatar date.
     * Allowed strictly for Admin (both branches) and Salon Manager (own branch ONLY).
     */
    public function store(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->canAccessBlindEodClose(), 403, 'You do not have permission to submit End-of-Day closes.');

        $rawBranch = (string) $request->input('branch', '');
        $branch = DailyBranchClose::normalizeBranch($rawBranch);
        if (!$branch) {
            throw ValidationException::withMessages([
                'branch' => ['Please select a valid branch (Old Airport or Al Wakrah).'],
            ]);
        }

        if (!$actor->isAdmin()) {
            $managedBranch = $actor->managedCanonicalBranch();
            abort_unless(
                $managedBranch !== null && $managedBranch === $branch,
                403,
                'You can only submit End-of-Day closes for your assigned branch.'
            );
        }

        $validated = $request->validate([
            'close_date' => ['required', 'date'],
            'opening_float_qar' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'entered_cash_qar' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'entered_card_qar' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'entered_transfer_qar' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'variance_note' => ['nullable', 'string', 'max:2000'],
            'z_report' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $closeDate = Carbon::parse($validated['close_date'], 'Asia/Qatar')->toDateString();

        $existing = DailyBranchClose::query()
            ->where('branch', $branch)
            ->whereDate('close_date', $closeDate)
            ->first();

        if ($existing && $existing->is_locked && !$actor->isAdmin()) {
            abort(403, 'This End-of-Day close is locked. Only an Admin can unlock or edit it.');
        }

        // Compute server-side Sales Ledger truth snapshot for this branch + Asia/Qatar date
        $expected = DailyBranchClose::expectedTotalsFor($branch, $closeDate);

        $openingFloat = round((float) ($validated['opening_float_qar'] ?? 0), 2);
        $enteredCash = round((float) $validated['entered_cash_qar'], 2);
        $enteredCard = round((float) $validated['entered_card_qar'], 2);
        $enteredTransfer = round((float) ($validated['entered_transfer_qar'] ?? 0), 2);

        $hasExistingZReport = $existing && filled($existing->z_report_path);
        if (($enteredCard > 0 || $expected['card'] > 0) && !$request->hasFile('z_report') && !$hasExistingZReport) {
            throw ValidationException::withMessages([
                'z_report' => ['A POS Z-Report batch slip (photo or PDF) is required whenever card payments are recorded.'],
            ]);
        }

        $netCashEntered = round($enteredCash - $openingFloat, 2);
        $cashVariance = round($netCashEntered - $expected['cash'], 2);
        $cardVariance = round($enteredCard - $expected['card'], 2);
        $transferVariance = round($enteredTransfer - $expected['transfer'], 2);
        $totalVariance = round($cashVariance + $cardVariance + $transferVariance, 2);

        if (abs($totalVariance) < 0.01) {
            $status = DailyBranchClose::STATUS_BALANCED;
            $totalVariance = 0.0;
        } elseif ($totalVariance < 0) {
            $status = DailyBranchClose::STATUS_SHORTAGE;
        } else {
            $status = DailyBranchClose::STATUS_OVERAGE;
        }

        $varianceNote = isset($validated['variance_note']) && trim((string) $validated['variance_note']) !== ''
            ? trim((string) $validated['variance_note'])
            : null;

        $zReportPath = $existing?->z_report_path;
        if ($request->hasFile('z_report')) {
            if ($zReportPath && Storage::disk('local')->exists($zReportPath)) {
                Storage::disk('local')->delete($zReportPath);
            }
            $file = $request->file('z_report');
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'pdf');
            $storedName = $closeDate . '_' . Str::uuid()->toString() . '.' . $ext;
            $zReportPath = $file->storeAs("private/eod-closes/{$branch}", $storedName, 'local');
        }

        $attributes = [
            'branch' => $branch,
            'close_date' => $closeDate,
            'opening_float_qar' => $openingFloat,
            'entered_cash_qar' => $enteredCash,
            'entered_card_qar' => $enteredCard,
            'entered_transfer_qar' => $enteredTransfer,
            'expected_cash_qar' => $expected['cash'],
            'expected_card_qar' => $expected['card'],
            'expected_transfer_qar' => $expected['transfer'],
            'cash_variance_qar' => $cashVariance,
            'card_variance_qar' => $cardVariance,
            'transfer_variance_qar' => $transferVariance,
            'total_variance_qar' => $totalVariance,
            'status' => $status,
            'z_report_path' => $zReportPath,
            'variance_note' => $varianceNote,
            'submitted_by' => $actor->id,
            'submitted_at' => Carbon::now('Asia/Qatar'),
            'verified_by' => null,
            'verified_at' => null,
            'is_locked' => true,
        ];

        if ($existing) {
            $existing->fill($attributes)->save();
            $close = $existing->fresh();
        } else {
            $close = DailyBranchClose::create($attributes);
        }

        $branchLabel = DailyBranchClose::branchLabel($branch);
        $badge = $close->statusBadge();
        $message = "End-of-Day Close submitted for {$branchLabel} ({$closeDate}): {$badge['label']}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'close' => $close,
            ], 201);
        }

        return redirect()->route('appointments.revenue.index', [
            'tab' => 'sales-ledger',
            'eod_branch' => $branch,
            'eod_date' => $closeDate,
        ])->with('success', $message);
    }

    /**
     * Admin-only: verify a submitted daily close.
     */
    public function verify(Request $request, DailyBranchClose $dailyBranchClose)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can verify End-of-Day closes.');

        $dailyBranchClose->update([
            'verified_by' => $request->user()->id,
            'verified_at' => Carbon::now('Asia/Qatar'),
            'is_locked' => true,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'close' => $dailyBranchClose->fresh()]);
        }

        return redirect()->route('appointments.revenue.index', [
            'tab' => 'sales-ledger',
            'eod_branch' => $dailyBranchClose->branch,
            'eod_date' => $dailyBranchClose->close_date->toDateString(),
        ])->with('success', 'End-of-Day Close verified.');
    }

    /**
     * Admin-only: unlock / re-open a locked daily close for correction.
     */
    public function unlock(Request $request, DailyBranchClose $dailyBranchClose)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can unlock End-of-Day closes.');

        $dailyBranchClose->update([
            'is_locked' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'close' => $dailyBranchClose->fresh()]);
        }

        return redirect()->route('appointments.revenue.index', [
            'tab' => 'sales-ledger',
            'eod_branch' => $dailyBranchClose->branch,
            'eod_date' => $dailyBranchClose->close_date->toDateString(),
        ])->with('success', 'End-of-Day Close unlocked for correction.');
    }

    /**
     * Policy-locked download/preview of POS Z-Report slip.
     * Strictly limited to Admin OR the submitting branch's Salon Manager (HTTP 403 otherwise).
     */
    public function zReport(Request $request, DailyBranchClose $dailyBranchClose)
    {
        /** @var User|null $actor */
        $actor = $request->user();
        $isAllowed = $actor && (
            $actor->isAdmin()
            || ($actor->isSalonManager() && $actor->managedCanonicalBranch() === $dailyBranchClose->branch)
        );
        abort_unless($isAllowed, 403, 'You do not have permission to access this Z-Report.');

        if (empty($dailyBranchClose->z_report_path) || !Storage::disk('local')->exists($dailyBranchClose->z_report_path)) {
            abort(404, 'POS Z-Report file could not be located.');
        }

        $absolutePath = Storage::disk('local')->path($dailyBranchClose->z_report_path);
        $filename = basename($dailyBranchClose->z_report_path);

        if ($request->boolean('inline')) {
            return response()->file($absolutePath);
        }

        return response()->download($absolutePath, $filename);
    }
}
