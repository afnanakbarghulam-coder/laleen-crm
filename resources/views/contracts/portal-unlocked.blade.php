@php
    $resolvedLogoUrl = $logoUrl ?? \App\Models\StaffContract::logoPublicUrl();
    $structuredClauses = $contract->parsedClauses();
    $monthlyCompFormatted = number_format($contract->monthlyCompensation(), 2);
    $startDisplay = $contract->start_date ? $contract->start_date->format('d M Y') : 'Immediate';
    $expiryDisplay = $contract->expiry_date ? $contract->expiry_date->format('d M Y') : 'Open-Ended';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Laleen Contract {{ $contract->contract_number }} — {{ $staffName }}</title>
    <style>
        :root {
            --lc-bg: #120b0e;
            --lc-card-top: rgba(34, 22, 27, 0.94);
            --lc-card-btm: rgba(20, 13, 17, 0.98);
            --lc-border: rgba(229, 184, 168, 0.15);
            --lc-hairline: rgba(255, 255, 255, 0.06);
            --lc-cream: #f8efe6;
            --lc-taupe: #b8a29a;
            --lc-rose: #e5b8a8;
            --lc-rose-deep: #d98f83;
            --lc-champagne: #d4a373;
            --lc-emerald: #6ee7b7;
            --lc-ruby: #fda4af;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 32px 16px 56px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, sans-serif;
            background: radial-gradient(circle at 50% 0%, #24161b 0%, var(--lc-bg) 65%);
            color: var(--lc-cream);
        }

        .lc-container {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .lc-card {
            background: linear-gradient(180deg, var(--lc-card-top) 0%, var(--lc-card-btm) 100%);
            border: 1px solid var(--lc-border);
            border-radius: 18px;
            padding: 28px 32px;
        }

        .lc-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            padding-bottom: 22px;
            border-bottom: 1px solid var(--lc-hairline);
        }

        .lc-brand-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .lc-logo-badge {
            width: 66px;
            height: 66px;
            border-radius: 15px;
            background: #ffffff;
            padding: 5px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.34), 0 0 0 1px rgba(229, 184, 168, 0.3);
        }

        .lc-logo-badge img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 10px;
            display: block;
        }

        .lc-brand-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--lc-rose);
            margin-bottom: 5px;
        }

        .lc-title {
            margin: 0 0 9px;
            font-size: 24px;
            font-weight: 700;
            color: var(--lc-cream);
            letter-spacing: -0.01em;
        }

        .lc-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .ui-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(229, 184, 168, 0.09);
            color: var(--lc-rose);
            border: 1px solid rgba(229, 184, 168, 0.18);
        }

        .ui-chip.status-active {
            background: rgba(110, 231, 183, 0.12);
            color: var(--lc-emerald);
            border-color: rgba(110, 231, 183, 0.28);
        }

        .ui-chip.status-pending_signature {
            background: rgba(212, 163, 115, 0.14);
            color: var(--lc-champagne);
            border-color: rgba(212, 163, 115, 0.32);
        }

        .ui-chip.status-draft {
            background: rgba(184, 162, 154, 0.12);
            color: var(--lc-taupe);
            border-color: rgba(184, 162, 154, 0.24);
        }

        .lc-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            border: none;
            border-radius: 999px;
            padding: 10px 20px;
            cursor: pointer;
            white-space: nowrap;
            transition: filter 0.15s ease;
        }

        .lc-btn-primary:hover { filter: brightness(1.06); }

        .lc-btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: rgba(229, 184, 168, 0.08);
            color: var(--lc-cream);
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            border: 1px solid rgba(229, 184, 168, 0.24);
            border-radius: 999px;
            padding: 9px 16px;
            cursor: pointer;
            white-space: nowrap;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .lc-btn-ghost:hover {
            border-color: var(--lc-rose);
            background: rgba(229, 184, 168, 0.14);
        }

        /* Clean 3-Stat Executive Strip */
        .lc-stat-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid var(--lc-hairline);
        }

        @media (max-width: 680px) {
            .lc-stat-strip { grid-template-columns: 1fr; gap: 14px; }
        }

        .lc-metric-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--lc-taupe);
            margin-bottom: 5px;
        }

        .lc-metric-val {
            font-size: 18px;
            font-weight: 700;
            color: var(--lc-cream);
        }

        .lc-metric-val.highlight {
            color: var(--lc-rose);
        }

        /* Editorial Numbered Clauses */
        .lc-clauses-list {
            display: flex;
            flex-direction: column;
            margin-top: 6px;
        }

        .lc-clause-item {
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 14px;
            padding: 18px 0;
            border-bottom: 1px solid var(--lc-hairline);
        }

        .lc-clause-item:last-child {
            border-bottom: none;
            padding-bottom: 4px;
        }

        .lc-clause-num {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--lc-rose-deep);
            padding-top: 1px;
        }

        .lc-clause-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--lc-rose);
            margin-bottom: 6px;
        }

        .lc-clause-text {
            font-size: 13.5px;
            line-height: 1.68;
            color: rgba(248, 239, 230, 0.92);
            white-space: pre-line;
        }

        /* Executive Sign-Off Footer */
        .lc-sign-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
        }

        .lc-seg-toggle {
            display: inline-flex;
            padding: 3px;
            border-radius: 999px;
            background: rgba(14, 9, 11, 0.82);
            border: 1px solid rgba(229, 184, 168, 0.18);
        }

        .lc-seg-btn {
            border: none;
            background: transparent;
            color: var(--lc-taupe);
            font-size: 12px;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .lc-seg-btn.active-mode {
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f;
            font-weight: 700;
        }

        .lc-canvas-frame {
            position: relative;
            border-radius: 12px;
            background: rgba(13, 8, 10, 0.85);
            border: 1px solid rgba(229, 184, 168, 0.24);
            overflow: hidden;
            margin-bottom: 14px;
        }

        .lc-canvas-pad {
            width: 100%;
            height: 124px;
            cursor: crosshair;
            touch-action: none;
            display: block;
        }

        .lc-canvas-clear {
            position: absolute;
            top: 8px;
            right: 10px;
            background: rgba(34, 22, 27, 0.82);
            border: 1px solid rgba(229, 184, 168, 0.2);
            color: var(--lc-taupe);
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.15s ease, border-color 0.15s ease;
        }

        .lc-canvas-clear:hover {
            color: var(--lc-cream);
            border-color: rgba(229, 184, 168, 0.42);
        }

        .lc-typed-box {
            border-radius: 12px;
            background: rgba(13, 8, 10, 0.85);
            border: 1px solid rgba(229, 184, 168, 0.24);
            padding: 18px 20px;
            margin-bottom: 14px;
            min-height: 82px;
            display: flex;
            align-items: center;
        }

        .lc-typed-preview {
            font-family: "Georgia", "Times New Roman", serif;
            font-style: italic;
            font-size: 26px;
            color: var(--lc-rose);
        }

        .lc-sign-action-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .lc-name-field-wrap {
            flex: 1 1 260px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(13, 8, 10, 0.85);
            border: 1px solid rgba(229, 184, 168, 0.24);
            border-radius: 999px;
            padding: 5px 16px;
        }

        .lc-name-field-label {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--lc-taupe);
            white-space: nowrap;
        }

        .lc-input-bare {
            flex: 1;
            background: transparent;
            border: none;
            padding: 6px 0;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--lc-cream);
            outline: none;
        }

        .lc-helper-note {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--lc-hairline);
            font-size: 11.5px;
            color: var(--lc-taupe);
        }

        .lc-flash {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .lc-flash.success {
            background: rgba(229, 184, 168, 0.12);
            color: var(--lc-cream);
            border: 1px solid rgba(229, 184, 168, 0.28);
        }

        .lc-flash.error {
            background: rgba(253, 164, 175, 0.12);
            color: var(--lc-ruby);
            border: 1px solid rgba(253, 164, 175, 0.28);
        }

        .lc-sig-img {
            max-height: 78px;
            max-width: 260px;
            margin-top: 10px;
            filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.35));
        }

        @media print {
            body { background: #ffffff !important; color: #111111 !important; padding: 0 !important; }
            .lc-card { background: #ffffff !important; color: #111111 !important; border: 1px solid #dddddd !important; }
            .lc-title, .lc-metric-val, .lc-clause-text { color: #111111 !important; }
            .lc-clause-title, .lc-clause-num { color: #6e3b34 !important; }
            .lc-no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="lc-container">
        @if (session('success'))
            <div class="lc-flash success lc-no-print">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="lc-flash error lc-no-print">{{ $errors->first() }}</div>
        @endif

        <section class="lc-card">
            <div class="lc-header-row">
                <div class="lc-brand-left">
                    <div class="lc-logo-badge">
                        <img src="{{ $resolvedLogoUrl }}" alt="Laleen Beauty Salon">
                    </div>
                    <div>
                        <div class="lc-brand-eyebrow">Laleen Beauty Salon &middot; Official Agreement</div>
                        <h1 class="lc-title">{{ $staffName }}</h1>
                        <div class="lc-pill-row">
                            <span class="ui-chip">{{ $contract->contract_number }}</span>
                            <span class="ui-chip">{{ $contract->designationLabel() }}</span>
                            <span class="ui-chip">{{ $contract->branchLabel() }}</span>
                            <span class="ui-chip status-{{ $contract->status }}">{{ $contract->statusLabel() }}</span>
                        </div>
                    </div>
                </div>

                <div class="lc-pill-row lc-no-print">
                    @if (($isAdminViewer ?? false) && auth()->user()?->isAdmin())
                        <span class="ui-chip">Admin View</span>
                        <button type="button" class="lc-btn-ghost" id="lcOpenEditModalBtn" onclick="openPortalEditModal()">Edit Contract</button>
                    @else
                        <span class="ui-chip" id="lcTimerChip">Unlocked &middot; {{ ceil($remainingSeconds / 60) }}m window</span>
                    @endif
                    <a href="{{ $pdfUrl }}" class="lc-btn-primary">Download PDF ↗</a>
                </div>
            </div>

            <div class="lc-stat-strip">
                <div>
                    <div class="lc-metric-label">Monthly Compensation</div>
                    <div class="lc-metric-val highlight">{{ $contract->currency }} {{ $monthlyCompFormatted }}</div>
                </div>
                <div>
                    <div class="lc-metric-label">Branch / Location</div>
                    <div class="lc-metric-val">{{ $contract->branchLabel() }}</div>
                </div>
                <div>
                    <div class="lc-metric-label">Effective Term</div>
                    <div class="lc-metric-val" style="font-size: 15px; padding-top: 2px;">
                        {{ $startDisplay }} &rarr; {{ $expiryDisplay }}
                    </div>
                </div>
            </div>

            <div class="lc-clauses-list">
                @foreach ($structuredClauses as $clause)
                    <div class="lc-clause-item">
                        <div class="lc-clause-num">{{ $clause['index'] }}</div>
                        <div>
                            <div class="lc-clause-title">{{ $clause['title'] }}</div>
                            <div class="lc-clause-text">{{ $clause['body'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($contract->status === \App\Models\StaffContract::STATUS_PENDING_SIGNATURE)
            <section class="lc-card lc-no-print">
                <div class="lc-sign-top">
                    <div>
                        <h2 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--lc-cream);">Digital Sign-Off &amp; Acceptance</h2>
                        <div style="font-size: 12px; color: var(--lc-taupe); margin-top: 2px;">Confirm your legal name and signature below to execute this agreement.</div>
                    </div>
                    <div class="lc-seg-toggle" role="tablist" aria-label="Signature Mode">
                        <button type="button" class="lc-seg-btn active-mode" id="modeDrawBtn" onclick="switchSigMode('drawn')">Draw</button>
                        <button type="button" class="lc-seg-btn" id="modeTypeBtn" onclick="switchSigMode('typed')">Type</button>
                    </div>
                </div>

                <form method="POST" action="{{ $signUrl }}" id="contractSignForm">
                    @csrf
                    <input type="hidden" name="signature_type" id="sigTypeInput" value="drawn">
                    <input type="hidden" name="signature_data" id="sigDataInput" value="">

                    <div id="drawPadWrap" class="lc-canvas-frame">
                        <canvas id="sigCanvas" class="lc-canvas-pad" width="840" height="124"></canvas>
                        <button type="button" class="lc-canvas-clear" onclick="clearSigCanvas()">Clear</button>
                    </div>

                    <div id="typePadWrap" class="lc-typed-box" style="display:none;">
                        <div class="lc-typed-preview" id="typedSigPreview">{{ $staffName }}</div>
                    </div>

                    <div class="lc-sign-action-bar">
                        <label class="lc-name-field-wrap" for="signedNameInput">
                            <span class="lc-name-field-label">Full Legal Name</span>
                            <input
                                type="text"
                                id="signedNameInput"
                                name="signed_name"
                                class="lc-input-bare"
                                value="{{ old('signed_name', $staffName) }}"
                                placeholder="Enter full legal name"
                                required
                            >
                        </label>

                        <button type="submit" class="lc-btn-primary">Sign &amp; Accept</button>
                    </div>
                </form>

                <div class="lc-helper-note">
                    Executing this agreement permanently locks your contract as Active and enables immediate PDF download for your records.
                </div>
            </section>
        @elseif ($contract->status === \App\Models\StaffContract::STATUS_ACTIVE)
            <section class="lc-card">
                <div class="lc-sign-top" style="margin-bottom: 6px;">
                    <div>
                        <h2 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--lc-rose);">Executed &amp; Permanently Locked (Active)</h2>
                        <div style="font-size: 12.5px; color: var(--lc-taupe); margin-top: 4px;">
                            Signed by <strong style="color: var(--lc-cream);">{{ $contract->signed_name ?: $staffName }}</strong>
                            @if ($contract->signed_at)
                                &middot; {{ $contract->signed_at->copy()->timezone('Asia/Qatar')->format('d M Y, h:i A') }} (Doha)
                            @endif
                        </div>
                    </div>
                    <a href="{{ $pdfUrl }}" class="lc-btn-primary lc-no-print">Download Signed PDF ↗</a>
                </div>

                @if ($contract->signature_type === 'drawn' && $contract->signature_data && str_starts_with($contract->signature_data, 'data:image/'))
                    <img src="{{ $contract->signature_data }}" alt="Employee Signature" class="lc-sig-img">
                @else
                    <div class="lc-typed-preview" style="padding-top: 8px;">{{ $contract->signed_name ?: $staffName }}</div>
                @endif
            </section>
        @else
            <section class="lc-card">
                <div style="font-size: 13.5px; color: var(--lc-taupe);">
                    This agreement is currently in <strong style="color: var(--lc-cream);">Draft</strong> status and will open for digital sign-off once issued by Management.
                </div>
            </section>
        @endif
    </div>

    <script>
        (function () {
            const canvas = document.getElementById('sigCanvas');
            const sigDataInput = document.getElementById('sigDataInput');
            const sigTypeInput = document.getElementById('sigTypeInput');
            const signedNameInput = document.getElementById('signedNameInput');
            const typedPreview = document.getElementById('typedSigPreview');
            const form = document.getElementById('contractSignForm');

            let hasDrawnStroke = false;
            let drawing = false;

            if (signedNameInput && typedPreview) {
                signedNameInput.addEventListener('input', function () {
                    typedPreview.textContent = this.value.trim() || 'Signature Preview';
                });
            }

            window.switchSigMode = function (mode) {
                if (!sigTypeInput) return;
                sigTypeInput.value = mode;
                document.getElementById('drawPadWrap').style.display = mode === 'drawn' ? 'block' : 'none';
                document.getElementById('typePadWrap').style.display = mode === 'typed' ? 'flex' : 'none';
                document.getElementById('modeDrawBtn').classList.toggle('active-mode', mode === 'drawn');
                document.getElementById('modeTypeBtn').classList.toggle('active-mode', mode === 'typed');
            };

            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.strokeStyle = '#f8efe6';
                ctx.lineWidth = 2.4;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';

                function pos(e) {
                    const rect = canvas.getBoundingClientRect();
                    const scaleX = canvas.width / rect.width;
                    const scaleY = canvas.height / rect.height;
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
                }

                canvas.addEventListener('pointerdown', function (e) {
                    drawing = true;
                    hasDrawnStroke = true;
                    const p = pos(e);
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                });

                canvas.addEventListener('pointermove', function (e) {
                    if (!drawing) return;
                    const p = pos(e);
                    ctx.lineTo(p.x, p.y);
                    ctx.stroke();
                });

                window.addEventListener('pointerup', function () {
                    drawing = false;
                });

                window.clearSigCanvas = function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hasDrawnStroke = false;
                    if (sigDataInput) sigDataInput.value = '';
                };
            }

            if (form) {
                form.addEventListener('submit', function () {
                    if (sigTypeInput && sigTypeInput.value === 'drawn' && canvas && hasDrawnStroke) {
                        sigDataInput.value = canvas.toDataURL('image/png');
                    } else if (sigDataInput && signedNameInput) {
                        sigTypeInput.value = 'typed';
                        sigDataInput.value = signedNameInput.value.trim();
                    }
                });
            }
        })();
    </script>

    @if (($isAdminViewer ?? false) && auth()->user()?->isAdmin())
        @php
            $pTemplates = $contractTemplates ?? \App\Models\StaffContract::allTemplates();
            $pSalonMembers = $portalSalonMembers ?? [];
            $pAccessMembers = $portalAccessMembers ?? [];
            $pNewCatSentinel = \App\Models\StaffContract::NEW_CATEGORY_SENTINEL;
            $pCurrentMemberKey = $contract->memberKey();
            $pIsActive = $contract->status === \App\Models\StaffContract::STATUS_ACTIVE;
        @endphp
        <style>
            .lc-edit-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 2000;
                background: rgba(10, 6, 8, 0.78);
                backdrop-filter: blur(4px);
                align-items: center;
                justify-content: center;
                padding: 20px 14px;
                overflow-y: auto;
            }
            .lc-edit-backdrop.is-open { display: flex; }
            .lc-edit-dialog {
                width: 100%;
                max-width: 780px;
                background: linear-gradient(180deg, #22161b 0%, #160e12 100%);
                border: 1px solid rgba(229, 184, 168, 0.22);
                border-radius: 18px;
                color: var(--lc-cream);
                box-shadow: 0 24px 60px rgba(0, 0, 0, 0.72);
                margin: auto;
            }
            .lc-edit-header, .lc-edit-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 18px 24px;
                border-bottom: 1px solid var(--lc-hairline);
            }
            .lc-edit-footer {
                border-bottom: none;
                border-top: 1px solid var(--lc-hairline);
                justify-content: flex-end;
            }
            .lc-edit-body {
                padding: 20px 24px;
                display: grid;
                grid-template-columns: repeat(12, 1fr);
                gap: 14px;
            }
            .lc-col-12 { grid-column: span 12; }
            .lc-col-6 { grid-column: span 6; }
            .lc-col-4 { grid-column: span 4; }
            .lc-col-3 { grid-column: span 3; }
            .lc-col-2 { grid-column: span 2; }
            @media (max-width: 680px) {
                .lc-col-6, .lc-col-4, .lc-col-3, .lc-col-2 { grid-column: span 12; }
            }
            .lc-field-label {
                display: block;
                font-size: 11.5px;
                font-weight: 600;
                color: var(--lc-taupe);
                margin-bottom: 6px;
            }
            .lc-field-input, .lc-field-select, .lc-field-textarea {
                width: 100%;
                background: #1b1214;
                color: #f5ebe6;
                border: 1px solid rgba(229, 184, 168, 0.22);
                border-radius: 10px;
                padding: 8px 12px;
                font-size: 13px;
                font-family: inherit;
                color-scheme: dark;
                outline: none;
            }
            .lc-field-select option, .lc-field-select optgroup {
                background: #1b1214;
                color: #f5ebe6;
            }
            .lc-field-input:focus, .lc-field-select:focus, .lc-field-textarea:focus {
                border-color: var(--lc-rose);
                box-shadow: 0 0 0 3px rgba(229, 184, 168, 0.12);
            }
            .lc-resig-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                padding: 11px 14px;
                border-radius: 12px;
                background: rgba(212, 163, 115, 0.09);
                border: 1px solid rgba(212, 163, 115, 0.26);
            }
        </style>

        <div class="lc-edit-backdrop lc-no-print" id="portalEditContractModal" aria-hidden="true">
            <div class="lc-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="portalEditModalTitle">
                <form method="POST" action="{{ route('staff-contracts.update', $contract) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="redirect_to" value="{{ ($adminReturnContext ?? 'portal') === 'admin_show' ? 'admin_show' : 'portal' }}">

                    <div class="lc-edit-header">
                        <h3 id="portalEditModalTitle" style="margin: 0; font-size: 17px; font-weight: 700;">
                            Edit Staff Contract &middot; {{ $contract->contract_number }}
                        </h3>
                        <button type="button" class="lc-btn-ghost" style="padding: 5px 12px; font-size: 12px;" onclick="closePortalEditModal()">✕</button>
                    </div>

                    <div class="lc-edit-body">
                        <div class="lc-col-6">
                            <label class="lc-field-label" for="peTeamMember">Team Member</label>
                            <select name="team_member" id="peTeamMember" class="lc-field-select" required>
                                @if (!empty($pSalonMembers))
                                    <optgroup label="Salon Staff">
                                        @foreach ($pSalonMembers as $m)
                                            <option value="{{ $m['key'] }}" @selected($pCurrentMemberKey === $m['key'])>{{ $m['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if (!empty($pAccessMembers))
                                    <optgroup label="Staff Access · CRM Team">
                                        @foreach ($pAccessMembers as $m)
                                            <option value="{{ $m['key'] }}" @selected($pCurrentMemberKey === $m['key'])>{{ $m['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>

                        <div class="lc-col-6">
                            <label class="lc-field-label" for="peTemplateKey">Contract Template / Category</label>
                            <select name="template_key" id="peTemplateKey" class="lc-field-select" onchange="onPortalTemplateChange(this.value)">
                                @foreach ($pTemplates as $tKey => $tpl)
                                    <option value="{{ $tKey }}" @selected($contract->template_key === $tKey)>
                                        {{ $tpl['label'] }} ({{ implode('/', $tpl['allowed_currencies'] ?? ['QAR']) }})
                                    </option>
                                @endforeach
                                @if (!isset($pTemplates[$contract->template_key]))
                                    <option value="{{ $contract->template_key }}" selected>{{ $contract->designationLabel() }}</option>
                                @endif
                                <option value="{{ $pNewCatSentinel }}">+ Add Category</option>
                            </select>
                        </div>

                        <div class="lc-col-12" id="peNewCatWrap" style="display: none;">
                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; padding: 10px 12px; border-radius: 12px; border: 1px solid rgba(229, 184, 168, 0.2);">
                                <div>
                                    <label class="lc-field-label" for="peCustomCatName">New Category / Designation Name *</label>
                                    <input type="text" name="custom_category_name" id="peCustomCatName" class="lc-field-input" maxlength="120" placeholder="e.g. Nail Technician, Video Editor">
                                </div>
                                <div>
                                    <label class="lc-field-label" for="peCustomCatCurr">Category Currency</label>
                                    <select name="custom_category_currency" id="peCustomCatCurr" class="lc-field-select">
                                        <option value="QAR" @selected($contract->currency === 'QAR')>QAR</option>
                                        <option value="PKR" @selected($contract->currency === 'PKR')>PKR</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="lc-col-4">
                            <label class="lc-field-label" for="peRoleTitle">Designation</label>
                            <input type="text" name="role_title" id="peRoleTitle" class="lc-field-input" maxlength="120" value="{{ $contract->designationLabel() }}" required>
                        </div>

                        <div class="lc-col-3">
                            <label class="lc-field-label" for="peBranch">Branch / Location</label>
                            <select name="branch" id="peBranch" class="lc-field-select">
                                @foreach (\App\Models\StaffContract::BRANCHES as $bKey => $bLabel)
                                    <option value="{{ $bKey }}" @selected($contract->branch === $bKey)>{{ $bLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lc-col-2">
                            <label class="lc-field-label" for="peCurrency">Currency</label>
                            <select name="currency" id="peCurrency" class="lc-field-select">
                                @foreach (\App\Models\StaffContract::CURRENCIES as $curr)
                                    <option value="{{ $curr }}" @selected($contract->currency === $curr)>{{ $curr }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lc-col-3">
                            <label class="lc-field-label" for="peCompensation">Compensation</label>
                            <input type="number" name="compensation" id="peCompensation" class="lc-field-input" min="0" step="0.01" value="{{ number_format($contract->monthlyCompensation(), 2, '.', '') }}" required>
                        </div>

                        <div class="lc-col-3">
                            <label class="lc-field-label" for="peStartDate">Start Date</label>
                            <input type="date" name="start_date" id="peStartDate" class="lc-field-input" value="{{ $contract->start_date ? $contract->start_date->format('Y-m-d') : '' }}">
                        </div>

                        <div class="lc-col-3">
                            <label class="lc-field-label" for="peExpiryDate">Expiry Date</label>
                            <input type="date" name="expiry_date" id="peExpiryDate" class="lc-field-input" value="{{ $contract->expiry_date ? $contract->expiry_date->format('Y-m-d') : '' }}">
                        </div>

                        <div class="lc-col-4">
                            <label class="lc-field-label" for="peStatus">Status</label>
                            <select name="status" id="peStatus" class="lc-field-select">
                                <option value="pending_signature" @selected($contract->status === 'pending_signature' || $pIsActive)>Pending Signature</option>
                                <option value="draft" @selected($contract->status === 'draft')>Draft</option>
                                <option value="active">Active</option>
                            </select>
                        </div>

                        <div class="lc-col-2">
                            <label class="lc-field-label" for="pePin">4-Digit PIN</label>
                            <input type="text" name="pin" id="pePin" class="lc-field-input" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="Keep">
                        </div>

                        @if ($pIsActive)
                            <div class="lc-col-12">
                                <div class="lc-resig-bar">
                                    <label style="display: flex; align-items: center; gap: 9px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                        <input type="hidden" name="require_resignature" value="0">
                                        <input type="checkbox" name="require_resignature" id="peRequireResig" value="1" checked onchange="onPortalResigToggle(this.checked)" style="accent-color: #e5b8a8; width: 16px; height: 16px;">
                                        <span>Require staff re-signature (resets status to Pending Signature &amp; clears prior signature)</span>
                                    </label>
                                    <span class="ui-chip" id="peResigBadge">Resets to Pending Signature</span>
                                </div>
                            </div>
                        @endif

                        <div class="lc-col-12">
                            <label class="lc-field-label" for="peClausesBody">Contract Clauses &amp; Terms</label>
                            <textarea name="clauses_body" id="peClausesBody" class="lc-field-textarea" rows="7" required>{{ $contract->clauses_body }}</textarea>
                        </div>
                    </div>

                    <div class="lc-edit-footer">
                        <button type="button" class="lc-btn-ghost" onclick="closePortalEditModal()">Cancel</button>
                        <button type="submit" class="lc-btn-primary">Save Contract Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                const portalTemplates = {{ \Illuminate\Support\Js::from($pTemplates) }};
                const newCatSentinel = {{ \Illuminate\Support\Js::from($pNewCatSentinel) }};
                const modal = document.getElementById('portalEditContractModal');

                window.openPortalEditModal = function () {
                    if (!modal) return;
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                };

                window.closePortalEditModal = function () {
                    if (!modal) return;
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                };

                window.onPortalTemplateChange = function (val) {
                    const newWrap = document.getElementById('peNewCatWrap');
                    if (newWrap) {
                        newWrap.style.display = (val === newCatSentinel) ? 'block' : 'none';
                    }
                    const tpl = portalTemplates[val];
                    if (tpl) {
                        const roleEl = document.getElementById('peRoleTitle');
                        const currEl = document.getElementById('peCurrency');
                        const clausesEl = document.getElementById('peClausesBody');
                        if (roleEl && tpl.default_role) roleEl.value = tpl.default_role;
                        if (currEl && tpl.default_currency) currEl.value = tpl.default_currency;
                        if (clausesEl && tpl.clauses) clausesEl.value = tpl.clauses;
                    }
                };

                window.onPortalResigToggle = function (checked) {
                    const statusEl = document.getElementById('peStatus');
                    const badgeEl = document.getElementById('peResigBadge');
                    if (statusEl) statusEl.value = checked ? 'pending_signature' : 'active';
                    if (badgeEl) badgeEl.textContent = checked ? 'Resets to Pending Signature' : 'Keeps Active';
                };

                if (modal) {
                    modal.addEventListener('click', function (e) {
                        if (e.target === modal) closePortalEditModal();
                    });
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                            closePortalEditModal();
                        }
                    });
                }
            })();
        </script>
    @endif
</body>
</html>
