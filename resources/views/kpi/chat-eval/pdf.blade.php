@php
    $questionResults = $report->questionResults()->keyBy('num');
    $sectionBreakdown = $report->sectionBreakdown()->keyBy('label');
    $maxScore = \App\Models\KpiChatEvaluation::maxScore();
    $questionCount = count(\App\Models\KpiChatEvaluation::QUESTIONS);
    $passedCount = $report->passedCount();
    $failedCount = $report->failedCount();
    $grade = $report->grade();
    $pctDisplay = rtrim(rtrim(number_format((float) $report->percentage, 2), '0'), '.');

    $gradeColors = match ($grade) {
        'Excellent', 'Pass' => ['bg' => '#E6F4EA', 'text' => '#1E6B38', 'border' => '#A8D5B5'],
        'Warning' => ['bg' => '#FEF5E4', 'text' => '#8F5B00', 'border' => '#EED097'],
        default => ['bg' => '#FCEBEA', 'text' => '#9E2A2B', 'border' => '#EAB4B1'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laleen Ops — Chat Evaluation Report</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 14mm 16mm 14mm;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.45;
            color: #231C18;
            margin: 0;
            padding: 0;
        }

        .header-banner {
            width: 100%;
            border-collapse: collapse;
            background-color: #241C18;
            color: #FAF6F2;
            border-bottom: 3px solid #C89D7C;
            margin-bottom: 14px;
        }

        .header-banner td {
            padding: 14px 16px;
            vertical-align: middle;
        }

        .brand-eyebrow {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: #D7B498;
            margin-bottom: 3px;
        }

        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #FFFFFF;
            margin: 0;
        }

        .header-score-box {
            text-align: right;
        }

        .header-score-num {
            font-size: 15px;
            font-weight: bold;
            color: #FFFFFF;
            margin-bottom: 5px;
        }

        .grade-badge {
            display: inline-block;
            padding: 3px 10px;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-radius: 3px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 1px solid #E3D8CE;
            background-color: #FAF7F4;
        }

        .meta-table td {
            padding: 8px 11px;
            border: 1px solid #E3D8CE;
            vertical-align: top;
        }

        .meta-label {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #7A695E;
            margin-bottom: 2px;
        }

        .meta-value {
            font-size: 10.5px;
            font-weight: bold;
            color: #231C18;
        }

        .kpi-strip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .kpi-strip td {
            width: 25%;
            padding: 8px 10px;
            border: 1px solid #E3D8CE;
            background-color: #FFFFFF;
            text-align: center;
        }

        .kpi-strip-label {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7A695E;
            margin-bottom: 2px;
        }

        .kpi-strip-val {
            font-size: 12.5px;
            font-weight: bold;
            color: #231C18;
        }

        .category-block {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .category-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #DDD1C7;
        }

        .category-header-row td {
            background-color: #F3ECE6;
            border-bottom: 1px solid #DDD1C7;
            border-left: 4px solid #C89D7C;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 10.5px;
            color: #231C18;
        }

        .category-header-score {
            text-align: right;
            color: #6B584B;
            font-size: 10px;
        }

        .q-head-row th {
            background-color: #FAF7F4;
            border-bottom: 1px solid #E6DDD5;
            padding: 5px 8px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7A695E;
            text-align: left;
        }

        .q-row td {
            padding: 6px 8px;
            border-bottom: 1px solid #EFE8E1;
            vertical-align: top;
            font-size: 10px;
        }

        .q-num {
            width: 26px;
            color: #7A695E;
            font-weight: bold;
        }

        .q-max {
            width: 58px;
            text-align: center;
            color: #6B584B;
        }

        .q-status {
            width: 66px;
            text-align: center;
        }

        .q-earned {
            width: 68px;
            text-align: right;
            font-weight: bold;
            color: #231C18;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 7px;
            font-size: 8.5px;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .status-pass {
            background-color: #E6F4EA;
            color: #1E6B38;
            border: 1px solid #A8D5B5;
        }

        .status-fail {
            background-color: #FCEBEA;
            color: #9E2A2B;
            border: 1px solid #EAB4B1;
        }

        .remark-row td {
            padding: 5px 10px 7px 34px;
            background-color: #FCF9F6;
            border-bottom: 1px solid #EFE8E1;
            font-size: 9.5px;
            color: #5C4E45;
            font-style: italic;
        }

        .remark-label {
            font-style: normal;
            font-weight: bold;
            color: #8A654A;
        }

        .notes-box {
            margin-top: 12px;
            border: 1px solid #DDD1C7;
            border-left: 4px solid #C89D7C;
            background-color: #FAF7F4;
            padding: 10px 12px;
            page-break-inside: avoid;
        }

        .notes-title {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #8A654A;
            margin-bottom: 5px;
        }

        .notes-body {
            font-size: 10px;
            color: #2E2520;
            margin: 0;
        }

        .footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #E3D8CE;
            font-size: 8.5px;
            color: #8A7B70;
            width: 100%;
            border-collapse: collapse;
        }
    </style>
</head>
<body>
    <table class="header-banner">
        <tr>
            <td style="width: 65%;">
                <div class="brand-eyebrow">Laleen Beauty Salon &middot; Quality Assurance</div>
                <div class="report-title">Laleen Ops &mdash; Chat Evaluation Report</div>
            </td>
            <td class="header-score-box" style="width: 35%;">
                <div class="header-score-num">{{ $report->total_score }}/{{ $maxScore }} ({{ $pctDisplay }}%)</div>
                <span class="grade-badge" style="background-color: {{ $gradeColors['bg'] }}; color: {{ $gradeColors['text'] }}; border: 1px solid {{ $gradeColors['border'] }};">
                    {{ $grade }}
                </span>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="width: 24%;">
                <div class="meta-label">Evaluation Date</div>
                <div class="meta-value">{{ $report->evaluation_date->format('d M Y') }}</div>
            </td>
            <td style="width: 44%;">
                <div class="meta-label">Evaluated Agent(s)</div>
                <div class="meta-value">{{ $report->formattedAgentsLabel() }}</div>
            </td>
            <td style="width: 32%;">
                <div class="meta-label">Evaluator</div>
                <div class="meta-value">{{ $report->evaluator->name ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="kpi-strip">
        <tr>
            <td>
                <div class="kpi-strip-label">Total Score</div>
                <div class="kpi-strip-val">{{ $report->total_score }} / {{ $maxScore }}</div>
            </td>
            <td>
                <div class="kpi-strip-label">Percentage</div>
                <div class="kpi-strip-val">{{ $pctDisplay }}%</div>
            </td>
            <td>
                <div class="kpi-strip-label">Passed Questions</div>
                <div class="kpi-strip-val" style="color: #1E6B38;">{{ $passedCount }} / {{ $questionCount }}</div>
            </td>
            <td>
                <div class="kpi-strip-label">Failed Questions</div>
                <div class="kpi-strip-val" style="color: #9E2A2B;">{{ $failedCount }} / {{ $questionCount }}</div>
            </td>
        </tr>
    </table>

    @foreach (\App\Models\KpiChatEvaluation::BREAKDOWN_GROUPS as $category => $questionNumbers)
        @php $group = $sectionBreakdown[$category]; @endphp
        <div class="category-block">
            <table class="category-table">
                <tr class="category-header-row">
                    <td colspan="4">{{ $category }}</td>
                    <td class="category-header-score">{{ $group['score'] }} / {{ $group['max'] }} pts</td>
                </tr>
                <tr class="q-head-row">
                    <th class="q-num">#</th>
                    <th>Question</th>
                    <th class="q-max" style="text-align: center;">Max Pts</th>
                    <th class="q-status" style="text-align: center;">Status</th>
                    <th class="q-earned" style="text-align: right;">Earned</th>
                </tr>
                @foreach ($questionNumbers as $num)
                    @php $q = $questionResults[$num]; @endphp
                    <tr class="q-row">
                        <td class="q-num">{{ $num }}</td>
                        <td>{{ $q['text'] }}</td>
                        <td class="q-max">{{ $q['weight'] }}</td>
                        <td class="q-status">
                            <span class="status-pill {{ $q['passed'] ? 'status-pass' : 'status-fail' }}">
                                {{ $q['passed'] ? 'Pass' : 'Fail' }}
                            </span>
                        </td>
                        <td class="q-earned">{{ $q['score'] }} / {{ $q['weight'] }}</td>
                    </tr>
                    @if (! empty($q['remark']))
                        <tr class="remark-row">
                            <td colspan="5">
                                <span class="remark-label">Remark:</span> {{ $q['remark'] }}
                            </td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </div>
    @endforeach

    @if (! empty($report->notes))
        <div class="notes-box">
            <div class="notes-title">Overall Evaluation / Leader Notes</div>
            <p class="notes-body">{!! nl2br(e($report->notes)) !!}</p>
        </div>
    @endif

    <table class="footer">
        <tr>
            <td>Laleen Ops CRM &mdash; Chat Quality Scorecard (15 Questions, {{ $maxScore }} Max Points)</td>
            <td style="text-align: right;">Generated {{ now()->format('d M Y, H:i') }}</td>
        </tr>
    </table>
</body>
</html>
