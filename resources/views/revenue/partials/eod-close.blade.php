@php
    $eodBranchLabel = \App\Models\DailyBranchClose::branchLabel($eodBranch);
    $eodIsLocked = $currentEodClose && $currentEodClose->is_locked;
    $eodBadge = $currentEodClose ? $currentEodClose->statusBadge() : null;
    $eodToneMap = [
        'emerald' => ['bg' => 'rgba(52, 211, 153, 0.14)', 'border' => 'rgba(52, 211, 153, 0.34)', 'color' => '#6ee7b7'],
        'amber' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.34)', 'color' => '#fbbf24'],
        'danger' => ['bg' => 'rgba(251, 113, 133, 0.14)', 'border' => 'rgba(251, 113, 133, 0.36)', 'color' => '#fda4af'],
        'muted' => ['bg' => 'rgba(255, 255, 255, 0.06)', 'border' => 'rgba(255, 255, 255, 0.12)', 'color' => '#cbb8b0'],
    ];
    $eodFormatVar = static function (float $val): string {
        if (abs($val) < 0.01) {
            return '0.00 QAR';
        }
        return ($val > 0 ? '+' : '-') . number_format(abs($val), 2) . ' QAR';
    };
    $eodVarColor = static function (float $val): string {
        if (abs($val) < 0.01) {
            return '#6ee7b7';
        }
        return $val < 0 ? '#fda4af' : '#fbbf24';
    };
@endphp

<style>
    .eod-card {
        background: linear-gradient(180deg, rgba(33, 22, 25, 0.94) 0%, rgba(23, 16, 18, 0.98) 100%);
        border: 1px solid rgba(217, 143, 131, 0.22);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .eod-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(217, 143, 131, 0.14);
    }

    .eod-title {
        font-size: 15.5px;
        font-weight: 700;
        color: #f8efe6;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .eod-sub {
        font-size: 12.5px;
        color: #b8a29a;
        margin-top: 2px;
    }

    .eod-body {
        padding: 18px 20px;
    }

    .eod-num {
        font-variant-numeric: tabular-nums;
    }

    .eod-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 700;
        white-space: nowrap;
        line-height: 1.3;
    }

    .eod-input, .eod-select {
        background-color: #140e10 !important;
        border: 1px solid rgba(217, 143, 131, 0.26) !important;
        color: #f8efe6 !important;
        border-radius: 10px !important;
        font-size: 13.5px;
        color-scheme: dark;
    }

    .eod-select {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23e5b8a8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 12px 10px !important;
        padding-right: 2.2rem !important;
    }

    .eod-select option {
        background-color: #140e10;
        color: #f8efe6;
    }

    .eod-input:focus, .eod-select:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }

    .eod-label {
        font-size: 12px;
        font-weight: 600;
        color: #d6c3bc;
        margin-bottom: 5px;
        display: block;
    }

    .eod-helper {
        font-size: 11.5px;
        color: #9e8780;
        margin-top: 4px;
    }

    .eod-submit-btn {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border: none;
        color: #1b1315;
        font-weight: 700;
        font-size: 13.5px;
        padding: 9px 22px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: filter 0.15s ease;
    }

    .eod-submit-btn:hover {
        filter: brightness(1.06);
        color: #1b1315;
    }

    .eod-recon-table {
        width: 100%;
        border-collapse: collapse;
    }

    .eod-recon-table th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #a8938b;
        padding: 9px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .eod-recon-table td {
        padding: 11px 12px;
        font-size: 13.5px;
        color: #f8efe6;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .eod-recon-table tr.is-total td {
        border-top: 1px solid rgba(217, 143, 131, 0.24);
        border-bottom: none;
        font-weight: 700;
        background: rgba(217, 143, 131, 0.05);
    }

    .eod-admin-ref {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        background: rgba(212, 163, 115, 0.08);
        border: 1px solid rgba(212, 163, 115, 0.22);
        font-size: 12.5px;
        color: #e8cfa9;
        margin-bottom: 16px;
    }
</style>

{{-- BLIND END-OF-DAY (EOD) CASH & POS CLOSE CARD --}}
<div class="eod-card" id="eodCloseSection">
    <div class="eod-head">
        <div>
            <h6 class="eod-title">
                <i class="bx bx-lock-open-alt" style="color:#d98f83;"></i>
                <span>End-of-Day Cash &amp; POS Close</span>
                @if ($eodIsLocked && $eodBadge)
                    @php $t = $eodToneMap[$eodBadge['tone']] ?? $eodToneMap['muted']; @endphp
                    <span class="eod-pill eod-num" style="background:{{ $t['bg'] }}; border:1px solid {{ $t['border'] }}; color:{{ $t['color'] }};">
                        {{ $eodBadge['label'] }}
                    </span>
                @elseif ($currentEodClose && !$currentEodClose->is_locked)
                    <span class="eod-pill" style="background:rgba(245,158,11,0.14); border:1px solid rgba(245,158,11,0.34); color:#fbbf24;">
                        <i class="bx bx-edit-alt"></i> Unlocked for Correction
                    </span>
                @else
                    <span class="eod-pill" style="background:rgba(217,143,131,0.12); border:1px solid rgba(217,143,131,0.28); color:#e79a91;">
                        Pending Submission
                    </span>
                @endif
            </h6>
            <div class="eod-sub">
                Blind physical cash drawer &amp; POS terminal Z-Report close &middot; {{ $eodBranchLabel }} &middot; {{ \Illuminate\Support\Carbon::parse($eodDate, 'Asia/Qatar')->format('D, d M Y') }} (Qatar)
            </div>
        </div>

        {{-- Branch & Date Picker for EOD Close --}}
        <form method="GET" action="{{ route('appointments.revenue.index') }}" class="d-flex align-items-center flex-wrap gap-2">
            <input type="hidden" name="tab" value="sales-ledger">
            @if (request()->filled('from'))
                <input type="hidden" name="from" value="{{ request('from') }}">
            @endif
            @if (request()->filled('to'))
                <input type="hidden" name="to" value="{{ request('to') }}">
            @endif
            @if (request()->filled('branch'))
                <input type="hidden" name="branch" value="{{ request('branch') }}">
            @endif

            @if ($isAdminViewer)
                <select name="eod_branch" class="form-select form-select-sm eod-select" style="width:auto; min-width:142px;" onchange="this.form.submit()" aria-label="EOD Close Branch">
                    @foreach (\App\Models\DailyBranchClose::BRANCHES as $bKey => $bLabel)
                        <option value="{{ $bKey }}" {{ $eodBranch === $bKey ? 'selected' : '' }}>{{ $bLabel }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="eod_branch" value="{{ $eodBranch }}">
                <span class="eod-pill" style="background:rgba(212,163,115,0.13); border:1px solid rgba(212,163,115,0.28); color:#f3d9a4;">
                    <i class="bx bx-store"></i> {{ $eodBranchLabel }}
                </span>
            @endif

            <input type="date" name="eod_date" value="{{ $eodDate }}" class="form-control form-control-sm eod-input eod-num" style="width:auto;" onchange="this.form.submit()" aria-label="EOD Close Date">
        </form>
    </div>

    <div class="eod-body">
        @if ($errors->any())
            <div class="alert alert-danger py-2 px-3 mb-3" style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.34); color:#fda4af; border-radius:10px; font-size:13px;">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$eodIsLocked)
            {{-- BLIND ENTRY FORM (Shown before submission or when unlocked by Admin) --}}
            @if ($isAdminViewer && !empty($adminEodLiveExpected))
                <div class="eod-admin-ref eod-num">
                    <i class="bx bx-shield-quarter fs-5"></i>
                    <span>
                        <strong>Admin Live Reference ({{ $eodBranchLabel }} &middot; {{ $eodDate }}):</strong>
                        Cash <b>{{ number_format($adminEodLiveExpected['cash'], 2) }} QAR</b> &middot;
                        Card <b>{{ number_format($adminEodLiveExpected['card'], 2) }} QAR</b> &middot;
                        Transfer <b>{{ number_format($adminEodLiveExpected['transfer'], 2) }} QAR</b>
                        ({{ $adminEodLiveExpected['sales_count'] }} {{ Str::plural('sale', $adminEodLiveExpected['sales_count']) }})
                    </span>
                </div>
            @elseif ($isSalonManagerViewer)
                <div class="mb-3 px-3 py-2 d-flex align-items-center gap-2" style="border-radius:10px; background:rgba(217,143,131,0.08); border:1px solid rgba(217,143,131,0.2); font-size:12.5px; color:#d6c3bc;">
                    <i class="bx bx-info-circle fs-5" style="color:#d98f83;"></i>
                    <span>Count the physical cash drawer and print your POS Z-Report batch slip first. CRM expected totals and variance will reveal immediately after you submit.</span>
                </div>
            @endif

            <form method="POST" action="{{ route('finance.eod-close.store') }}" enctype="multipart/form-data" id="eodBlindCloseForm">
                @csrf
                <input type="hidden" name="branch" value="{{ $eodBranch }}">
                <input type="hidden" name="close_date" value="{{ $eodDate }}">

                <div class="row g-3">
                    <div class="col-sm-6 col-xl-3">
                        <label class="eod-label" for="eodOpeningFloat">Opening Float (QAR)</label>
                        <input type="number" step="0.01" min="0" name="opening_float_qar" id="eodOpeningFloat"
                            class="form-control eod-input eod-num"
                            value="{{ old('opening_float_qar', $currentEodClose ? number_format($currentEodClose->opening_float_qar, 2, '.', '') : '0.00') }}">
                        <div class="eod-helper">Starting petty cash left in drawer</div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <label class="eod-label" for="eodEnteredCash">Physical Cash Counted (QAR) <span style="color:#fda4af;">*</span></label>
                        <input type="number" step="0.01" min="0" name="entered_cash_qar" id="eodEnteredCash"
                            class="form-control eod-input eod-num" required
                            placeholder="0.00"
                            value="{{ old('entered_cash_qar', $currentEodClose ? number_format($currentEodClose->entered_cash_qar, 2, '.', '') : '') }}">
                        <div class="eod-helper">Total cash counted (net cash subtracts float)</div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <label class="eod-label" for="eodEnteredCard">POS Card Batch Total (QAR) <span style="color:#fda4af;">*</span></label>
                        <input type="number" step="0.01" min="0" name="entered_card_qar" id="eodEnteredCard"
                            class="form-control eod-input eod-num" required
                            placeholder="0.00"
                            value="{{ old('entered_card_qar', $currentEodClose ? number_format($currentEodClose->entered_card_qar, 2, '.', '') : '') }}">
                        <div class="eod-helper">Exact settlement total from POS terminal</div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <label class="eod-label" for="eodEnteredTransfer">Bank Transfer / Link Total (QAR)</label>
                        <input type="number" step="0.01" min="0" name="entered_transfer_qar" id="eodEnteredTransfer"
                            class="form-control eod-input eod-num"
                            value="{{ old('entered_transfer_qar', $currentEodClose ? number_format($currentEodClose->entered_transfer_qar, 2, '.', '') : '0.00') }}">
                        <div class="eod-helper">Verified transfers &amp; payment links</div>
                    </div>

                    <div class="col-md-6">
                        <label class="eod-label" for="eodZReport">POS Z-Report Slip Upload (Photo or PDF)</label>
                        <input type="file" name="z_report" id="eodZReport" class="form-control eod-input" accept=".jpg,.jpeg,.png,.webp,.pdf">
                        <div class="eod-helper">
                            Required whenever card payments &gt; 0 (JPG, PNG, WebP, PDF &middot; max 10 MB).
                            @if ($currentEodClose && $currentEodClose->z_report_path)
                                <span style="color:#6ee7b7;">Existing slip attached — upload a new file only to replace it.</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="eod-label" for="eodVarianceNote">Shift Notes / Variance Explanation</label>
                        <textarea name="variance_note" id="eodVarianceNote" rows="2" class="form-control eod-input"
                            placeholder="Optional notes on cash difference, manual card settlement, or refunds...">{{ old('variance_note', $currentEodClose?->variance_note) }}</textarea>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
                    <span class="small" style="color:#9e8780;">
                        <i class="bx bx-lock-alt me-1"></i> Submitting locks this day&rsquo;s close automatically and compares against the Sales Ledger.
                    </span>
                    <button type="submit" class="eod-submit-btn">
                        <i class="bx bx-check-shield"></i>
                        <span>Submit End-of-Day Close</span>
                    </button>
                </div>
            </form>
        @else
            {{-- SUBMITTED & LOCKED RECONCILIATION SUMMARY --}}
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center flex-wrap gap-2" style="font-size:12.5px; color:#cbb8b0;">
                    <span><i class="bx bx-user-check me-1" style="color:#d98f83;"></i> Submitted by <strong class="text-white">{{ $currentEodClose->submitter?->name ?? 'Manager' }}</strong></span>
                    @if ($currentEodClose->submitted_at)
                        <span>&middot;</span>
                        <span class="eod-num">{{ $currentEodClose->submitted_at->timezone('Asia/Qatar')->format('d M Y, h:i A') }}</span>
                    @endif
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    @if ($currentEodClose->isVerified())
                        <span class="eod-pill" style="background:rgba(52,211,153,0.14); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7;">
                            <i class="bx bx-badge-check"></i> Verified by {{ $currentEodClose->verifier?->name ?? 'Admin' }}
                        </span>
                    @else
                        <span class="eod-pill" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); color:#cbb8b0;">
                            <i class="bx bx-time-five"></i> Awaiting Admin Verification
                        </span>
                    @endif
                    <span class="eod-pill" style="background:rgba(217,143,131,0.10); border:1px solid rgba(217,143,131,0.22); color:#e79a91;">
                        <i class="bx bx-lock-alt"></i> Locked
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="eod-recon-table eod-num">
                    <thead>
                        <tr>
                            <th>Payment Channel</th>
                            <th class="text-end">CRM Expected (QAR)</th>
                            <th class="text-end">Counted / Settled (QAR)</th>
                            <th class="text-end">Variance (QAR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <span class="fw-semibold">Cash (Net of Float)</span>
                                @if ($currentEodClose->opening_float_qar > 0)
                                    <div class="small" style="color:#9e8780;">
                                        Drawer {{ number_format($currentEodClose->entered_cash_qar, 2) }} &minus; Float {{ number_format($currentEodClose->opening_float_qar, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($currentEodClose->expected_cash_qar, 2) }}</td>
                            <td class="text-end">{{ number_format($currentEodClose->netCashEntered(), 2) }}</td>
                            <td class="text-end fw-semibold" style="color:{{ $eodVarColor($currentEodClose->cash_variance_qar) }};">
                                {{ $eodFormatVar($currentEodClose->cash_variance_qar) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">POS Card Batch</td>
                            <td class="text-end">{{ number_format($currentEodClose->expected_card_qar, 2) }}</td>
                            <td class="text-end">{{ number_format($currentEodClose->entered_card_qar, 2) }}</td>
                            <td class="text-end fw-semibold" style="color:{{ $eodVarColor($currentEodClose->card_variance_qar) }};">
                                {{ $eodFormatVar($currentEodClose->card_variance_qar) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Bank Transfer / Payment Link</td>
                            <td class="text-end">{{ number_format($currentEodClose->expected_transfer_qar, 2) }}</td>
                            <td class="text-end">{{ number_format($currentEodClose->entered_transfer_qar, 2) }}</td>
                            <td class="text-end fw-semibold" style="color:{{ $eodVarColor($currentEodClose->transfer_variance_qar) }};">
                                {{ $eodFormatVar($currentEodClose->transfer_variance_qar) }}
                            </td>
                        </tr>
                        <tr class="is-total">
                            <td>Total Daily Close</td>
                            <td class="text-end">{{ number_format($currentEodClose->totalExpected(), 2) }}</td>
                            <td class="text-end">{{ number_format($currentEodClose->totalEntered(), 2) }}</td>
                            <td class="text-end" style="color:{{ $eodVarColor($currentEodClose->total_variance_qar) }};">
                                {{ $eodFormatVar($currentEodClose->total_variance_qar) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($currentEodClose->variance_note)
                <div class="mt-3 px-3 py-2" style="border-radius:10px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); font-size:12.5px; color:#d6c3bc;">
                    <strong style="color:#f3d9a4;">Notes:</strong> {{ $currentEodClose->variance_note }}
                </div>
            @endif

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    @if ($currentEodClose->z_report_path)
                        <a href="{{ route('finance.eod-close.z-report', ['dailyBranchClose' => $currentEodClose->id, 'inline' => 1]) }}"
                           target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-1" style="border-radius:999px; font-size:12px;">
                            <i class="bx bx-receipt"></i> View POS Z-Report
                        </a>
                        <a href="{{ route('finance.eod-close.z-report', $currentEodClose) }}"
                           class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="border-radius:999px; font-size:12px;">
                            <i class="bx bx-download"></i> Download Slip
                        </a>
                    @else
                        <span class="small" style="color:#9e8780;">No POS Z-Report slip attached (0.00 QAR card sales).</span>
                    @endif
                </div>

                @if ($isAdminViewer)
                    <div class="d-flex align-items-center gap-2">
                        @if (!$currentEodClose->isVerified())
                            <form method="POST" action="{{ route('finance.eod-close.verify', $currentEodClose) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm d-inline-flex align-items-center gap-1"
                                    style="background:rgba(52,211,153,0.16); border:1px solid rgba(52,211,153,0.38); color:#6ee7b7; border-radius:999px; font-weight:700; font-size:12px; padding:5px 14px;">
                                    <i class="bx bx-check-double"></i> Verify Close
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('finance.eod-close.unlock', $currentEodClose) }}" class="d-inline"
                              onsubmit="return confirm('Unlock this End-of-Day close so it can be edited and resubmitted?')">
                            @csrf
                            <button type="submit" class="btn btn-sm d-inline-flex align-items-center gap-1"
                                style="background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.34); color:#fbbf24; border-radius:999px; font-weight:700; font-size:12px; padding:5px 14px;">
                                <i class="bx bx-lock-open"></i> Unlock / Re-open
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- RECENT EOD CLOSE HISTORY TABLE --}}
@if ($recentEodCloses->isNotEmpty())
    <div class="fin-section-card mb-3">
        <div class="fin-section-head flex-wrap gap-2">
            <div>
                <h6><i class="bx bx-history me-1"></i> End-of-Day Close History</h6>
                <span class="text-muted small">Latest {{ $recentEodCloses->count() }} branch reconciliations</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table fin-table eod-num">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Submitted By</th>
                        <th class="text-end">Expected</th>
                        <th class="text-end">Counted</th>
                        <th>Status / Variance</th>
                        <th>Z-Report</th>
                        <th>Verification</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentEodCloses as $hist)
                        @php
                            $hBadge = $hist->statusBadge();
                            $ht = $eodToneMap[$hBadge['tone']] ?? $eodToneMap['muted'];
                            $isCurrentRow = $hist->branch === $eodBranch && $hist->close_date->toDateString() === $eodDate;
                        @endphp
                        <tr style="{{ $isCurrentRow ? 'background:rgba(217,143,131,0.06);' : '' }}">
                            <td class="text-nowrap fw-semibold">{{ $hist->close_date->format('d M Y') }}</td>
                            <td>{{ \App\Models\DailyBranchClose::branchLabel($hist->branch) }}</td>
                            <td>{{ $hist->submitter?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($hist->totalExpected(), 2) }}</td>
                            <td class="text-end">{{ number_format($hist->totalEntered(), 2) }}</td>
                            <td>
                                <span class="eod-pill" style="background:{{ $ht['bg'] }}; border:1px solid {{ $ht['border'] }}; color:{{ $ht['color'] }};">
                                    {{ $hBadge['label'] }}
                                </span>
                            </td>
                            <td>
                                @if ($hist->z_report_path)
                                    <a href="{{ route('finance.eod-close.z-report', ['dailyBranchClose' => $hist->id, 'inline' => 1]) }}"
                                       target="_blank" rel="noopener" class="small text-decoration-none" style="color:#e79a91;">
                                        <i class="bx bx-file"></i> Slip
                                    </a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if (!$hist->is_locked)
                                    <span class="eod-pill" style="background:rgba(245,158,11,0.14); border:1px solid rgba(245,158,11,0.3); color:#fbbf24;">Unlocked</span>
                                @elseif ($hist->isVerified())
                                    <span class="eod-pill" style="background:rgba(52,211,153,0.14); border:1px solid rgba(52,211,153,0.3); color:#6ee7b7;">Verified</span>
                                @else
                                    <span class="text-muted small">Pending</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('appointments.revenue.index', ['tab' => 'sales-ledger', 'eod_branch' => $hist->branch, 'eod_date' => $hist->close_date->toDateString()]) }}"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:11.5px;">
                                    Inspect
                                </a>
                                @if ($isAdminViewer && $hist->is_locked && !$hist->isVerified())
                                    <form method="POST" action="{{ route('finance.eod-close.verify', $hist) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm py-0 px-2" style="background:rgba(52,211,153,0.15); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7; font-size:11.5px;">
                                            Verify
                                        </button>
                                    </form>
                                @endif
                                @if ($isAdminViewer && $hist->is_locked)
                                    <form method="POST" action="{{ route('finance.eod-close.unlock', $hist) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm py-0 px-2" style="background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#fbbf24; font-size:11.5px;">
                                            Unlock
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
