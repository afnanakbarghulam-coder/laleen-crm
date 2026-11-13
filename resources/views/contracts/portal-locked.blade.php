@php
    $resolvedLogoUrl = $logoUrl ?? \App\Models\StaffContract::logoPublicUrl();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Laleen Official Agreement — {{ $staffName }}</title>
    <style>
        :root {
            --lc-bg: #120b0e;
            --lc-card-top: rgba(33, 21, 26, 0.95);
            --lc-card-btm: rgba(19, 12, 16, 0.98);
            --lc-border: rgba(229, 184, 168, 0.18);
            --lc-hairline: rgba(255, 255, 255, 0.06);
            --lc-cream: #f8efe6;
            --lc-taupe: #b8a29a;
            --lc-rose: #e5b8a8;
            --lc-champagne: #d4a373;
            --lc-emerald: #6ee7b7;
            --lc-ruby: #fda4af;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 16px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, sans-serif;
            background: radial-gradient(circle at 50% 0%, #26171d 0%, var(--lc-bg) 70%);
            color: var(--lc-cream);
        }

        .lc-cover-card {
            width: 100%;
            max-width: 440px;
            background: linear-gradient(180deg, var(--lc-card-top) 0%, var(--lc-card-btm) 100%);
            border: 1px solid var(--lc-border);
            border-radius: 20px;
            padding: 36px 32px 30px;
            text-align: center;
            box-shadow: 0 28px 70px rgba(0, 0, 0, 0.58);
        }

        .lc-logo-medallion {
            width: 82px;
            height: 82px;
            margin: 0 auto 16px;
            border-radius: 18px;
            background: #ffffff;
            padding: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(229, 184, 168, 0.32);
        }

        .lc-logo-medallion img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            border-radius: 12px;
        }

        .lc-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--lc-rose);
            margin-bottom: 10px;
        }

        .lc-staff-name {
            margin: 0 0 12px;
            font-size: 24px;
            font-weight: 700;
            color: var(--lc-cream);
            letter-spacing: -0.01em;
        }

        .lc-badge-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 7px;
            padding-bottom: 24px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--lc-hairline);
        }

        .lc-chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(229, 184, 168, 0.09);
            color: var(--lc-rose);
            border: 1px solid rgba(229, 184, 168, 0.18);
        }

        .lc-status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .lc-status-pill.active {
            background: rgba(110, 231, 183, 0.12);
            color: var(--lc-emerald);
            border: 1px solid rgba(110, 231, 183, 0.28);
        }

        .lc-status-pill.pending_signature {
            background: rgba(212, 163, 115, 0.14);
            color: var(--lc-champagne);
            border: 1px solid rgba(212, 163, 115, 0.32);
        }

        .lc-status-pill.draft {
            background: rgba(184, 162, 154, 0.12);
            color: var(--lc-taupe);
            border: 1px solid rgba(184, 162, 154, 0.24);
        }

        .lc-pin-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--lc-taupe);
            margin-bottom: 12px;
            letter-spacing: 0.02em;
        }

        .lc-pin-input {
            width: 100%;
            background: rgba(13, 8, 10, 0.88);
            border: 1px solid rgba(229, 184, 168, 0.28);
            border-radius: 12px;
            padding: 13px 16px;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.55em;
            text-indent: 0.55em;
            text-align: center;
            color: var(--lc-cream);
            outline: none;
            margin-bottom: 12px;
            transition: border-color 0.16s ease, box-shadow 0.16s ease;
        }

        .lc-pin-input:focus {
            border-color: var(--lc-rose);
            box-shadow: 0 0 0 3px rgba(229, 184, 168, 0.12);
        }

        .lc-btn-unlock {
            width: 100%;
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f;
            font-weight: 700;
            font-size: 14px;
            border: none;
            border-radius: 12px;
            padding: 13px 22px;
            cursor: pointer;
            transition: filter 0.15s ease;
        }

        .lc-btn-unlock:hover { filter: brightness(1.06); }
        .lc-btn-unlock:disabled { opacity: 0.5; cursor: not-allowed; }

        .lc-error-line {
            margin-top: 12px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--lc-ruby);
        }

        .lc-footnote {
            margin-top: 18px;
            font-size: 11.5px;
            color: rgba(184, 162, 154, 0.78);
            line-height: 1.45;
        }

        @media print {
            body { background: #ffffff !important; color: #111111 !important; }
            .lc-cover-card { background: #ffffff !important; color: #111111 !important; border: 1px solid #cccccc !important; box-shadow: none !important; }
            form, .lc-footnote { display: none !important; }
        }
    </style>
</head>
<body>
    <main class="lc-cover-card">
        <div class="lc-logo-medallion">
            <img src="{{ $resolvedLogoUrl }}" alt="Laleen Beauty Salon">
        </div>

        <div class="lc-eyebrow">Laleen Beauty Salon &middot; Confidential Agreement</div>
        <h1 class="lc-staff-name">{{ $staffName }}</h1>

        <div class="lc-badge-row">
            <span class="lc-chip">{{ $roleTitle }}</span>
            <span class="lc-chip">{{ $branchLabel }}</span>
            <span class="lc-status-pill {{ $statusKey }}">{{ $statusLabel }}</span>
        </div>

        <form method="POST" action="{{ $unlockUrl }}" autocomplete="off">
            @csrf
            <label class="lc-pin-label" for="contractPinInput">Enter 4-Digit Security PIN</label>

            <input
                type="password"
                id="contractPinInput"
                name="pin"
                class="lc-pin-input"
                inputmode="numeric"
                pattern="\d{4}"
                maxlength="4"
                placeholder="••••"
                required
                autofocus
                {{ !empty($isThrottled) ? 'disabled' : '' }}
            >

            <button type="submit" class="lc-btn-unlock" {{ !empty($isThrottled) ? 'disabled' : '' }}>
                Unlock Agreement
            </button>

            @if (!empty($pinError))
                <div class="lc-error-line">{{ $pinError }}</div>
            @elseif ($errors->has('pin'))
                <div class="lc-error-line">{{ $errors->first('pin') }}</div>
            @elseif (!empty($isThrottled))
                <div class="lc-error-line">Too many failed PIN attempts. Please wait {{ $retryAfterSeconds }}s before trying again.</div>
            @endif
        </form>

        <div class="lc-footnote">
            Compensation terms, clauses, and digital sign-off unlock privately for 10 minutes upon verification.
        </div>
    </main>
</body>
</html>
