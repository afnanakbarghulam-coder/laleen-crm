@extends('layouts.app')
@section('title', 'Branch Evaluation Report')

@php
    $sections = [
        [
            'title' => 'SECTION 1 — ATTENDANCE & PUNCTUALITY',
            'score' => 10,
            'max' => 10,
            'questions' => [
                ['num' => 1, 'question' => 'Was the salon open, clean, and ready at opening time?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 2, 'question' => 'Were all staff present and at their assigned stations?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
            ],
        ],
        [
            'title' => 'SECTION 2 — DRESS CODE & HYGIENE',
            'score' => 5,
            'max' => 15,
            'questions' => [
                ['num' => 3, 'question' => 'Were all staff in proper Laleen uniform?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 4, 'question' => 'Did all staff look clean, groomed, and presentable?', 'answer' => '❌ No — open hair', 'score' => '0 / 5', 'status' => 'fail'],
                ['num' => 5, 'question' => 'Was the salon spotless — floors, mirrors, stations, reception?', 'answer' => '❌ No — store & reception messy', 'score' => '0 / 5', 'status' => 'fail'],
            ],
        ],
        [
            'title' => 'SECTION 3 — CUSTOMER CARE & COMMUNICATION',
            'score' => 32,
            'max' => 50,
            'questions' => [
                ['num' => 6, 'question' => 'Were you acknowledged and greeted the moment you walked in?', 'answer' => '✅ Yes', 'score' => '8 / 8', 'status' => 'pass'],
                ['num' => 7, 'question' => 'Were you offered tea, coffee, or water within 2 minutes?', 'answer' => '✅ Yes', 'score' => '8 / 8', 'status' => 'pass'],
                ['num' => 8, 'question' => 'Did the beautician explain the product/service before starting?', 'answer' => '⚠️ 50/50', 'score' => '4 / 8', 'status' => 'warn'],
                ['num' => 9, 'question' => 'Did she check on your comfort during the service?', 'answer' => '✅ Yes', 'score' => '8 / 8', 'status' => 'pass'],
                ['num' => 10, 'question' => 'Did she walk you back to reception and thank you warmly?', 'answer' => '⚠️ Inconsistent', 'score' => '4 / 8', 'status' => 'warn'],
                ['num' => 11, 'question' => 'Did staff stand up and greet when customers arrived?', 'answer' => '❌ No', 'score' => '0 / 10', 'status' => 'fail'],
            ],
        ],
        [
            'title' => 'SECTION 4 — ZERO COMPLAINTS',
            'score' => 15,
            'max' => 15,
            'questions' => [
                ['num' => 12, 'question' => 'Was there any idle staff sitting and ignoring customers?', 'answer' => '✅ No', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 13, 'question' => 'Did anything make you uncomfortable or frustrated?', 'answer' => '✅ No', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 14, 'question' => 'Was there any unprofessional behavior or argument?', 'answer' => '✅ No', 'score' => '5 / 5', 'status' => 'pass'],
            ],
        ],
        [
            'title' => 'SECTION 5 — TEAMWORK & ATTITUDE',
            'score' => 10,
            'max' => 10,
            'questions' => [
                ['num' => 15, 'question' => 'Did staff work together smoothly as a team?', 'answer' => '✅ Yes', 'score' => '4 / 4', 'status' => 'pass'],
                ['num' => 16, 'question' => 'Was the overall salon atmosphere positive, warm, and welcoming?', 'answer' => '✅ Yes', 'score' => '3 / 3', 'status' => 'pass'],
                ['num' => 17, 'question' => 'Did any staff member seem disinterested, bored, or lazy?', 'answer' => '✅ No', 'score' => '3 / 3', 'status' => 'pass'],
            ],
        ],
        [
            'title' => 'SECTION 6 — MANAGER EVALUATION (FAYE)',
            'score' => 25,
            'max' => 40,
            'questions' => [
                ['num' => 18, 'question' => 'Did the manager greet you with a genuine warm smile?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 19, 'question' => 'Did she proactively offer refreshments without being asked?', 'answer' => '❌ No', 'score' => '0 / 5', 'status' => 'fail'],
                ['num' => 20, 'question' => 'Did she check on your satisfaction during or after the service?', 'answer' => '❌ No', 'score' => '0 / 5', 'status' => 'fail'],
                ['num' => 21, 'question' => 'Was she actively managing bookings and operations?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 22, 'question' => 'Was she monitoring staff and ensuring KPIs were followed?', 'answer' => '❌ No', 'score' => '0 / 5', 'status' => 'fail'],
                ['num' => 23, 'question' => 'Did she recommend any additional service or product naturally?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 24, 'question' => 'Did she create positive energy and a welcoming atmosphere?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 25, 'question' => 'Was she fully present — not on her phone or distracted?', 'answer' => '✅ Yes — Active', 'score' => '5 / 5', 'status' => 'pass'],
            ],
        ],
        [
            'title' => 'SECTION 7 — BONUS OBSERVATIONS',
            'score' => 10,
            'max' => 10,
            'questions' => [
                ['num' => 26, 'question' => 'Did anyone recommend an additional service or product?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
                ['num' => 27, 'question' => 'Would you return as a real customer?', 'answer' => '✅ Yes', 'score' => '5 / 5', 'status' => 'pass'],
            ],
        ],
    ];

    $totalScore = collect($sections)->sum('score');
    $totalMax = collect($sections)->sum('max');
    $totalPct = round($totalScore / $totalMax * 100, 1);
@endphp

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Branch Evaluation Report — Old Airport</h4>
            <p>Mystery Shopper Assessment &middot; Manager: Faye &middot; 16 June 2026</p>
        </div>
        <a href="{{ route('kpi.salon-staff.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Salon Staff</a>
    </div>

    <div class="be-pill-row">
        <div class="be-pill">
            <div class="be-pill-label">Branch</div>
            <div class="be-pill-value">Old Airport</div>
        </div>
        <div class="be-pill">
            <div class="be-pill-label">Manager</div>
            <div class="be-pill-value">Faye</div>
        </div>
        <div class="be-pill">
            <div class="be-pill-label">Visit</div>
            <div class="be-pill-value">Visit 1 of 3</div>
        </div>
        <div class="be-pill">
            <div class="be-pill-label">Date</div>
            <div class="be-pill-value">16 Jun 2026</div>
        </div>
    </div>

    <div class="be-warning-banner">
        <i class="bx bx-error"></i>
        <div>
            <div class="be-warning-title">Warning — {{ $totalPct }}% &middot; Formal coaching required within 48 hours</div>
            <div class="be-warning-sub">Driven primarily by hygiene failures (open hair, messy store &amp; reception) and gaps in manager hospitality (no proactive refreshments, no satisfaction check-ins).</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Total Score</div>
                <div class="kpi-stat-value">{{ $totalScore }} <small class="fs-6">/ {{ $totalMax }}</small></div>
                <div class="kpi-stat-sub">{{ $totalPct }}% overall</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Grade</div>
                <div class="kpi-stat-value" style="color: var(--luxe-warning); font-size: 20px;">Warning</div>
                <div class="kpi-stat-sub">Target: 75%+</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Critical Sections</div>
                <div class="kpi-stat-value" style="color: #d4948c; font-size: 20px;">2</div>
                <div class="kpi-stat-sub">Hygiene + Manager</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Perfect Sections</div>
                <div class="kpi-stat-value" style="color: #b7cdb3; font-size: 20px;">4</div>
                <div class="kpi-stat-sub">Attendance, Complaints, Teamwork, Bonus</div>
            </div>
        </div>
    </div>

    @foreach ($sections as $section)
        @include('kpi.branch-evaluations._section', $section)
    @endforeach

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="kpi-panel h-100">
                <h6>Section Breakdown</h6>
                @foreach ($sections as $section)
                    @php
                        $sPct = $section['max'] > 0 ? round($section['score'] / $section['max'] * 100, 1) : 0;
                        $sPctDisplay = rtrim(rtrim(number_format($sPct, 1), '0'), '.');
                    @endphp
                    <div class="be-breakdown-row">
                        <span class="be-breakdown-title">{{ $section['title'] }}</span>
                        <span class="be-breakdown-value">{{ $section['score'] }}/{{ $section['max'] }} &middot; {{ $sPctDisplay }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-lg-6">
            <div class="kpi-panel h-100">
                <h6>Action Plan for Faye</h6>

                <div class="be-action-item">
                    <span class="kpi-badge kpi-badge-red">Urgent</span>
                    <span>Staff hygiene — open hair is not acceptable. Faye must conduct a uniform and grooming check every morning before opening. No exceptions.</span>
                </div>
                <div class="be-action-item">
                    <span class="kpi-badge kpi-badge-red">Urgent</span>
                    <span>Salon cleanliness — messy store and reception directly contributed to the 5,000 QAR municipality fine. Faye is responsible for this.</span>
                </div>
                <div class="be-action-item">
                    <span class="kpi-badge kpi-badge-amber">Train</span>
                    <span>Staff must stand up when a client enters — scored 0/10. This is the highest-weighted customer care question and a direct repeat of the fine violation.</span>
                </div>
                <div class="be-action-item">
                    <span class="kpi-badge kpi-badge-amber">Train</span>
                    <span>Faye must offer tea/coffee herself to clients — not just delegate it to staff. She scored 0/5 on hospitality and 0/5 on client feedback.</span>
                </div>
                <div class="be-action-item">
                    <span class="kpi-badge kpi-badge-green">Positive</span>
                    <span>Bookings managed well, energy positive, upselling done, present and active. Good foundation — hygiene and hospitality will get her to Pass.</span>
                </div>
            </div>
        </div>
    </div>

    <style>
        .be-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .be-pill {
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-radius: 999px;
            padding: 8px 20px;
        }

        .be-pill-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--luxe-muted);
        }

        .be-pill-value {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--luxe-ink);
        }

        .be-warning-banner {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: rgba(201, 166, 107, .12);
            border: 1px solid rgba(201, 166, 107, .4);
            border-radius: var(--luxe-radius-sm);
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .be-warning-banner > .bx {
            font-size: 24px;
            line-height: 1.3;
            color: var(--luxe-warning);
            flex-shrink: 0;
        }

        .be-warning-title {
            font-weight: 700;
            font-size: 15px;
            color: var(--luxe-warning);
            margin-bottom: 4px;
        }

        .be-warning-sub {
            font-size: 13px;
            line-height: 1.5;
            color: var(--luxe-muted);
        }

        .be-section {
            margin-bottom: 20px;
        }

        .be-section-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-left: 5px solid var(--luxe-accent);
            border-radius: var(--luxe-radius) var(--luxe-radius) 0 0;
            padding: 14px 20px;
        }

        .be-section-header.tier-green {
            border-left-color: var(--luxe-success);
        }

        .be-section-header.tier-red {
            border-left-color: var(--luxe-danger);
        }

        .be-section-header.tier-amber {
            border-left-color: var(--luxe-warning);
        }

        .be-section-title {
            font-weight: 700;
            font-size: 13.5px;
            letter-spacing: .03em;
            color: var(--luxe-ink);
        }

        .be-section-fraction {
            font-size: 13px;
            font-weight: 600;
            color: var(--luxe-muted);
        }

        .be-section-table {
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-top: none;
            border-radius: 0 0 var(--luxe-radius) var(--luxe-radius);
            overflow: hidden;
        }

        .be-answer {
            font-weight: 600;
            font-size: 13px;
        }

        .be-answer.is-pass {
            color: #9fc19a;
        }

        .be-answer.is-fail {
            color: #e2a299;
        }

        .be-answer.is-warn {
            color: var(--luxe-warning);
        }

        .be-breakdown-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--luxe-border);
            font-size: 13px;
        }

        .be-breakdown-row:last-child {
            border-bottom: none;
        }

        .be-breakdown-title {
            color: var(--luxe-body);
            font-weight: 600;
        }

        .be-breakdown-value {
            color: var(--luxe-muted);
            white-space: nowrap;
        }

        .be-action-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--luxe-border);
            font-size: 13px;
            line-height: 1.5;
            color: var(--luxe-muted);
        }

        .be-action-item:last-child {
            border-bottom: none;
        }

        .be-action-item .kpi-badge {
            flex-shrink: 0;
            margin-top: 1px;
        }
    </style>
@endsection
