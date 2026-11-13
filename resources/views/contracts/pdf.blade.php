@php
    $logoDataUri = \App\Models\StaffContract::logoDataUri();
    $staffName = $contract->staff?->name ?? 'Team Member';
    $currency = strtoupper((string) ($contract->currency ?: 'QAR'));
    $monthlyComp = number_format($contract->monthlyCompensation(), 2);
    $startLabel = $contract->start_date ? $contract->start_date->format('d M Y') : 'Immediate';
    $expiryLabel = $contract->expiry_date ? $contract->expiry_date->format('d M Y') : 'Open-Ended';
    $clauses = $contract->parsedClauses();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Laleen Contract {{ $contract->contract_number }} — {{ $staffName }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #1c110f;
            font-size: 11px;
            line-height: 1.55;
            margin: 0;
            padding: 24px 28px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #d98f83;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .logo-img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }
        .eyebrow {
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #9e5c52;
        }
        .emp-name {
            font-size: 18px;
            font-weight: bold;
            color: #1c110f;
            margin: 3px 0 6px;
        }
        .meta-pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 12px;
            border: 1px solid #dcc0b7;
            background: #fbf6f4;
            font-size: 9.5px;
            font-weight: bold;
            color: #5c3a35;
            margin-right: 5px;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: #fcf8f6;
            border: 1px solid #e6cfc8;
        }
        .stats-table td {
            width: 33.33%;
            padding: 12px 14px;
            border-right: 1px solid #ead8d2;
            vertical-align: top;
        }
        .stats-table td:last-child { border-right: none; }
        .stat-label {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #7d645e;
            margin-bottom: 4px;
        }
        .stat-val {
            font-size: 13.5px;
            font-weight: bold;
            color: #1c110f;
        }
        .clause-row {
            padding: 10px 0;
            border-bottom: 1px solid #efe4e0;
        }
        .clause-head {
            font-size: 10.5px;
            font-weight: bold;
            color: #8c4d43;
            margin-bottom: 4px;
        }
        .clause-num {
            color: #c4776b;
            margin-right: 6px;
        }
        .sig-box {
            margin-top: 22px;
            padding: 14px 16px;
            border: 1px solid #d98f83;
            background: #fcf8f6;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            @if ($logoDataUri !== '')
                <td style="width: 82px; vertical-align: middle;">
                    <img src="{{ $logoDataUri }}" alt="Laleen Beauty Salon" class="logo-img">
                </td>
            @endif
            <td style="vertical-align: middle;">
                <div class="eyebrow">LALEEN BEAUTY SALON &middot; OFFICIAL AGREEMENT</div>
                <div class="emp-name">{{ $staffName }}</div>
                <div>
                    <span class="meta-pill">{{ $contract->contract_number }}</span>
                    <span class="meta-pill">{{ $contract->designationLabel() }}</span>
                    <span class="meta-pill">{{ $contract->branchLabel() }}</span>
                    <span class="meta-pill">{{ $contract->statusLabel() }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="stats-table">
        <tr>
            <td>
                <div class="stat-label">Monthly Compensation</div>
                <div class="stat-val">{{ $currency }} {{ $monthlyComp }}</div>
            </td>
            <td>
                <div class="stat-label">Branch / Location</div>
                <div class="stat-val">{{ $contract->branchLabel() }}</div>
            </td>
            <td>
                <div class="stat-label">Effective Term</div>
                <div class="stat-val" style="font-size: 11.5px;">{{ $startLabel }} &rarr; {{ $expiryLabel }}</div>
            </td>
        </tr>
    </table>

    @foreach ($clauses as $clause)
        <div class="clause-row">
            <div class="clause-head">
                <span class="clause-num">{{ $clause['index'] }}</span> {{ $clause['title'] }}
            </div>
            <div>{!! nl2br(e($clause['body'])) !!}</div>
        </div>
    @endforeach

    <div class="sig-box">
        <strong>Signed By:</strong> {{ $contract->isLocked() ? ($contract->signed_name ?: $staffName) : 'Awaiting Employee Signature' }}<br>
        <strong>Status:</strong> {{ $contract->statusLabel() }}
        @if ($contract->signed_at)
            &middot; {{ $contract->signed_at->copy()->timezone('Asia/Qatar')->format('d M Y, h:i A') }} (Doha)
        @endif
    </div>
</body>
</html>
