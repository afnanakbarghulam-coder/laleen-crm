@extends('layouts.app')
@section('title', 'Executive KPI Dashboard')

@section('content')
    @php
        $ayan = $scorecard['ayan'] ?? [];
        $laden = $scorecard['laden'] ?? [];
        $sana = $scorecard['sana'] ?? [];
        $ahana = $scorecard['ahana'] ?? [];
        $rhea = $scorecard['rhea'] ?? [];
        $areeba = $scorecard['areeba'] ?? [];
        $zoya = $scorecard['zoya'] ?? [];
        $daysElapsedVal = (int) ($daysElapsed ?? ($scorecard['days_elapsed'] ?? max(1, now('Asia/Qatar')->day)));
        $daysInMonthVal = (int) ($daysInMonth ?? ($scorecard['days_in_month'] ?? 30));
        $effectiveMonthStr = $selectedMonth ?? ($scorecard['month'] ?? now('Asia/Qatar')->format('Y-m'));
        $isCurrentMonthView = isset($isCurrentMonth)
            ? (bool) $isCurrentMonth
            : (bool) ($scorecard['is_current_month'] ?? ($effectiveMonthStr === now('Asia/Qatar')->format('Y-m')));

        $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $effectiveMonthStr . '-01');
        $monthLabel = $selectedMonthLabel ?? $monthCarbon->format('F Y');
        $dateRangeLabel = $periodRangeLabel ?? ($scorecard['period_range_label'] ?? ($monthCarbon->copy()->startOfMonth()->format('M j') . ' – ' . now()->format('M j, Y')));
        $shortPeriodBadge = $shortPeriodBadge ?? ($scorecard['short_period_badge'] ?? ($monthCarbon->copy()->startOfMonth()->format('M j') . ' – ' . now()->format('M j')));

        // Ayan metrics (Month-to-Date 01 – Current Day)
        $oaRev = (float) ($ayan['old_airport_ad_revenue_qar'] ?? 0);
        $oaMtdPct = (float) ($ayan['old_airport_mtd_pct'] ?? ($ayan['old_airport_target_pct'] ?? 0));
        $oaPass = !empty($ayan['old_airport_mtd_passing']);

        $wkRev = (float) ($ayan['wakrah_ad_revenue_qar'] ?? 0);
        $wkMtdPct = (float) ($ayan['wakrah_mtd_pct'] ?? ($ayan['wakrah_target_pct'] ?? 0));
        $wkPass = !empty($ayan['wakrah_mtd_passing']);

        $mtdBranchTarget = (float) ($ayan['mtd_branch_target_qar'] ?? 2500);
        $mtdCombinedTarget = (float) ($ayan['mtd_combined_target_qar'] ?? ($mtdBranchTarget * 2));

        $totalAdRev = (float) ($ayan['total_ad_revenue_qar'] ?? ($oaRev + $wkRev));
        $adConv = (float) ($ayan['ad_conversion_pct'] ?? 0);
        $adConvBarPct = (float) ($ayan['ad_conversion_achievement_pct'] ?? min(100.0, ($adConv / 20.0) * 100));
        $adConvPass = $adConv >= 20.0;
        $pipelineScheduledLeads = (int) ($ayan['pipeline_booked_ad_leads'] ?? 0);

        $ayanOversightPct = (float) ($ayan['social_oversight_pct'] ?? 0);
        $ayanOversightPass = !empty($ayan['social_oversight_passing']);
        $ayanOverallPct = (float) ($ayan['overall_score_pct'] ?? 0);
        $ayanPassingCount = (int) ($ayan['passing_kpis_count'] ?? 0);
        $ayanTotalKpis = (int) ($ayan['total_kpis_count'] ?? 4);

        // Laden metrics (Month-to-Date 01 – Current Day)
        $ladenDailyBkTarget = (int) ($laden['daily_booking_target'] ?? 26);
        $todayBk = (int) ($laden['today_bookings_count'] ?? 0);
        $mtdBk = (int) ($laden['mtd_bookings_count'] ?? 0);
        $mtdBkTarget = (int) ($laden['mtd_target_bookings'] ?? (26 * $daysElapsedVal));
        $mtdBkPct = (float) ($laden['mtd_booking_quota_pct'] ?? 0);
        $fullMoBkTarget = (int) ($laden['full_month_target_bookings'] ?? 780);
        $bkPass = !empty($laden['booking_passing']);

        $chatAvg = (float) ($laden['chat_eval_avg_pct'] ?? 0);
        $chatBarPct = (float) ($laden['chat_eval_achievement_pct'] ?? min(100.0, ($chatAvg / 85.0) * 100));
        $chatPass = !empty($laden['chat_passing']);

        $leadConv = (float) ($laden['lead_conversion_pct'] ?? 0);
        $leadConvBarPct = (float) ($laden['lead_conv_achievement_pct'] ?? min(100.0, ($leadConv / 30.0) * 100));
        $bookedLeadsCnt = (int) ($laden['booked_leads_count'] ?? 0);
        $totalLeadsCnt = (int) ($laden['total_leads_count'] ?? 0);
        $leadPass = !empty($laden['lead_conv_passing']);
        $unassignedCnt = (int) ($laden['unassigned_leads_count'] ?? 0);
        $overdueCnt = (int) ($laden['overdue_followups_count'] ?? 0);

        $cmpPct = (float) ($laden['complaint_sla_pct'] ?? 100);
        $cmpPass = !empty($laden['complaint_passing']);
        $openCmp = (int) ($laden['open_complaints_count'] ?? 0);

        $ladenSelectedMonth = $selectedMonth ?? ($scorecard['month'] ?? now()->format('Y-m'));
        $ladenPeriodStart = $scorecard['period_start'] ?? $monthCarbon->copy()->startOfMonth()->toDateString();
        $ladenPeriodEnd = $scorecard['period_end'] ?? now()->toDateString();

        $ladenOverallPct = (float) ($laden['overall_score_pct'] ?? 0);
        $ladenPassingCount = (int) ($laden['passing_kpis_count'] ?? 0);
        $ladenTotalKpis = (int) ($laden['total_kpis_count'] ?? 4);

        // Sana metrics (strict same-month MTD)
        $sanaReels = (int) ($sana['mtd_published_reels'] ?? 0);
        $sanaReelsTarget = (int) ($sana['mtd_reels_target'] ?? 0);
        $sanaStories = (int) ($sana['mtd_published_stories'] ?? 0);
        $sanaStoriesTarget = (int) ($sana['mtd_stories_target'] ?? 0);
        $sanaPostedTotal = (int) ($sana['mtd_items_completed'] ?? ($sanaReels + $sanaStories));
        $sanaCumulativeTarget = (int) ($sana['mtd_items_target'] ?? ($sana['cumulative_mtd_target'] ?? 0));
        $sanaInMonthWeek = $sana['in_month_week_start'] ?? ($sana['week_start_date'] ?? now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString());
        $execPct = (float) ($sana['weekly_execution_pct'] ?? 0);
        $contentPass = !empty($sana['content_passing']);

        $lateCount = (int) ($sana['late_replies_count'] ?? 0);
        $lateReplyLogs = collect($sana['late_reply_logs'] ?? []);
        $repliesPass = !empty($sana['replies_sla_passing']);
        $isAdminUser = (bool) (auth()->user()?->isAdmin());

        $sanaOverallPct = (float) ($sana['overall_score_pct'] ?? 0);
        $sanaPassingCount = (int) ($sana['passing_kpis_count'] ?? 0);
        $sanaTotalKpis = (int) ($sana['total_kpis_count'] ?? 2);

        // Ahana metrics (Old Airport Branch Manager)
        $ahanaExpectedEvals = (int) ($ahana['expected_evaluations_mtd'] ?? 0);
        $ahanaCompletedEvals = (int) ($ahana['completed_evaluations_mtd'] ?? 0);
        $ahanaMondaysElapsed = (int) ($ahana['mondays_elapsed_count'] ?? ($scorecard['mondays_elapsed_count'] ?? 0));
        $ahanaEvalCompliancePct = (float) ($ahana['eval_compliance_pct'] ?? 0);
        $ahanaEvalPass = !empty($ahana['eval_compliance_passing']);

        $ahanaAuditsCount = (int) ($ahana['audits_count_mtd'] ?? 0);
        $ahanaMysteryAvgPct = (float) ($ahana['mystery_shopper_avg_pct'] ?? 0);
        $ahanaMysteryAttainmentPct = (float) ($ahana['mystery_shopper_attainment_pct'] ?? 0);
        $ahanaMysteryPass = !empty($ahana['mystery_shopper_passing']);

        $ahanaOverallPct = (float) ($ahana['overall_score_pct'] ?? 0);
        $ahanaPassingCount = (int) ($ahana['passing_kpis_count'] ?? 0);
        $ahanaTotalKpis = (int) ($ahana['total_kpis_count'] ?? 2);

        // Rhea metrics (Wakrah Branch Manager)
        $rheaExpectedEvals = (int) ($rhea['expected_evaluations_mtd'] ?? 0);
        $rheaCompletedEvals = (int) ($rhea['completed_evaluations_mtd'] ?? 0);
        $rheaMondaysElapsed = (int) ($rhea['mondays_elapsed_count'] ?? ($scorecard['mondays_elapsed_count'] ?? 0));
        $rheaEvalCompliancePct = (float) ($rhea['eval_compliance_pct'] ?? 0);
        $rheaEvalPass = !empty($rhea['eval_compliance_passing']);

        $rheaAuditsCount = (int) ($rhea['audits_count_mtd'] ?? 0);
        $rheaMysteryAvgPct = (float) ($rhea['mystery_shopper_avg_pct'] ?? 0);
        $rheaMysteryAttainmentPct = (float) ($rhea['mystery_shopper_attainment_pct'] ?? 0);
        $rheaMysteryPass = !empty($rhea['mystery_shopper_passing']);

        $rheaOverallPct = (float) ($rhea['overall_score_pct'] ?? 0);
        $rheaPassingCount = (int) ($rhea['passing_kpis_count'] ?? 0);
        $rheaTotalKpis = (int) ($rhea['total_kpis_count'] ?? 2);

        // Areeba metrics (Chat Agent — 13 Bookings / Day)
        $areebaTodayBk = (int) ($areeba['today_bookings'] ?? 0);
        $areebaTodayPct = (float) ($areeba['today_attainment_pct'] ?? 0);
        $areebaMtdBk = (int) ($areeba['mtd_bookings'] ?? 0);
        $areebaMtdTarget = (int) ($areeba['mtd_target'] ?? (13 * $daysElapsedVal));
        $areebaFullMoTarget = (int) ($areeba['full_month_target'] ?? (13 * $daysInMonthVal));
        $areebaMtdPct = (float) ($areeba['mtd_attainment_pct'] ?? 0);
        $areebaDailyAvg = (float) ($areeba['daily_avg_pace'] ?? round($areebaMtdBk / max(1, $daysInMonthVal), 1));
        $areebaDailyAvgPct = (float) ($areeba['daily_avg_pct'] ?? min(100.0, round(($areebaDailyAvg / 13) * 100, 1)));
        $areebaBkPass = !empty($areeba['booking_passing']);
        $areebaCard2Pass = $isCurrentMonthView ? ($areebaTodayBk >= 13) : ($areebaDailyAvg >= 13.0);
        $areebaCard2BarPct = $isCurrentMonthView ? $areebaTodayPct : $areebaDailyAvgPct;
        $areebaOverallPct = (float) ($areeba['overall_score_pct'] ?? $areebaMtdPct);
        $areebaPassingCount = (int) ($areeba['passing_kpis_count'] ?? ($areebaBkPass ? 1 : 0));
        $areebaTotalKpis = (int) ($areeba['total_kpis_count'] ?? 1);

        // Zoya metrics (Chat Agent — 13 Bookings / Day)
        $zoyaTodayBk = (int) ($zoya['today_bookings'] ?? 0);
        $zoyaTodayPct = (float) ($zoya['today_attainment_pct'] ?? 0);
        $zoyaMtdBk = (int) ($zoya['mtd_bookings'] ?? 0);
        $zoyaMtdTarget = (int) ($zoya['mtd_target'] ?? (13 * $daysElapsedVal));
        $zoyaFullMoTarget = (int) ($zoya['full_month_target'] ?? (13 * $daysInMonthVal));
        $zoyaMtdPct = (float) ($zoya['mtd_attainment_pct'] ?? 0);
        $zoyaDailyAvg = (float) ($zoya['daily_avg_pace'] ?? round($zoyaMtdBk / max(1, $daysInMonthVal), 1));
        $zoyaDailyAvgPct = (float) ($zoya['daily_avg_pct'] ?? min(100.0, round(($zoyaDailyAvg / 13) * 100, 1)));
        $zoyaBkPass = !empty($zoya['booking_passing']);
        $zoyaCard2Pass = $isCurrentMonthView ? ($zoyaTodayBk >= 13) : ($zoyaDailyAvg >= 13.0);
        $zoyaCard2BarPct = $isCurrentMonthView ? $zoyaTodayPct : $zoyaDailyAvgPct;
        $zoyaOverallPct = (float) ($zoya['overall_score_pct'] ?? $zoyaMtdPct);
        $zoyaPassingCount = (int) ($zoya['passing_kpis_count'] ?? ($zoyaBkPass ? 1 : 0));
        $zoyaTotalKpis = (int) ($zoya['total_kpis_count'] ?? 1);

        $healthOrbClass = function (float $pct): string {
            if ($pct >= 80.0) {
                return 'orb-emerald';
            }
            if ($pct >= 50.0) {
                return 'orb-amber';
            }
            return 'orb-berry';
        };

        $healthBarClass = function (float $pct): string {
            if ($pct >= 80.0) {
                return 'fill-emerald';
            }
            if ($pct >= 50.0) {
                return 'fill-champagne';
            }
            return 'fill-rose-gold';
        };
    @endphp

    <div class="luxe-exec-dashboard">
        <svg width="0" height="0" aria-hidden="true" style="position:absolute;pointer-events:none;overflow:hidden;">
            <defs>
                <linearGradient id="gradEmerald" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#34d399" stop-opacity="0.38"/>
                    <stop offset="100%" stop-color="#34d399" stop-opacity="0.0"/>
                </linearGradient>
                <linearGradient id="gradRose" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#e5b8a8" stop-opacity="0.38"/>
                    <stop offset="100%" stop-color="#e5b8a8" stop-opacity="0.0"/>
                </linearGradient>
                <linearGradient id="gradChampagne" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#d4a373" stop-opacity="0.36"/>
                    <stop offset="100%" stop-color="#d4a373" stop-opacity="0.0"/>
                </linearGradient>
            </defs>
        </svg>
        {{-- =====================================================================
             1. TOP HEADER BAR
             ===================================================================== --}}
        <div class="luxe-exec-topbar">
            <div class="luxe-exec-topbar-left">
                <h1 class="luxe-exec-title">Executive KPI Dashboard</h1>
                <p class="luxe-exec-subtitle">Key indicators. Clear insights. Smarter decisions.</p>

                <div class="luxe-owner-pills" role="navigation" aria-label="Filter KPIs by Owner">
                    <button type="button" class="luxe-owner-pill active" data-owner-filter="all" id="allExecKpisBtn">
                        <span class="pill-dot pill-dot-all"></span>
                        All Executive KPIs
                    </button>

                    <div class="luxe-staff-select-wrap">
                        <select id="staffScorecardDropdown" class="luxe-staff-select" aria-label="Select Staff Scorecard">
                            <option value="all">Select Staff Scorecard (All Overview)</option>
                            <option value="ayan">Ayan &mdash; Marketing &amp; Ads ({{ number_format($ayanOverallPct, 0) }}%)</option>
                            <option value="laden">Laden &mdash; Customer Service &amp; Sales ({{ number_format($ladenOverallPct, 0) }}%)</option>
                            <option value="sana">Sana &mdash; Organic Content &amp; Social ({{ number_format($sanaOverallPct, 0) }}%)</option>
                            <option value="ahana">Ahana &mdash; Old Airport Manager ({{ number_format($ahanaOverallPct, 0) }}%)</option>
                            <option value="rhea">Rhea &mdash; Wakrah Manager ({{ number_format($rheaOverallPct, 0) }}%)</option>
                            <option value="areeba">Areeba &mdash; Chat Agent ({{ number_format($areebaOverallPct, 0) }}%)</option>
                            <option value="zoya">Zoya &mdash; Chat Agent ({{ number_format($zoyaOverallPct, 0) }}%)</option>
                        </select>
                        <svg class="luxe-staff-select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="luxe-exec-topbar-right">
                {{-- Top 2-Button Mode Switcher --}}
                <div class="luxe-mode-switcher" role="navigation" aria-label="KPI and Operations Switcher">
                    <a href="{{ route('kpi.hub') }}" class="luxe-mode-btn active">
                        <span>📊</span> KPIs (Executive Scorecards)
                    </a>
                    <a href="{{ route('kpi.operations') }}" class="luxe-mode-btn">
                        <span>⚙️</span> Operations
                    </a>
                </div>

                <form method="GET" action="{{ route('kpi.hub') }}" class="luxe-date-pill-form" id="kpiMonthForm">
                    <label for="kpiMonthInput" class="luxe-date-pill" title="Click to change month">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span class="luxe-date-pill-text">{{ $dateRangeLabel }}</span>
                        <svg class="luxe-date-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                        <input
                            type="month"
                            id="kpiMonthInput"
                            name="month"
                            value="{{ $selectedMonth ?? now()->format('Y-m') }}"
                            class="luxe-date-native-input"
                            onchange="this.form.submit()"
                        />
                    </label>
                </form>
            </div>
        </div>

        {{-- =====================================================================
             2A. HOME VIEW ("ALL EXECUTIVE KPIs") — 1 OVERALL CARD PER STAFF MEMBER
             ===================================================================== --}}
        <div class="luxe-staff-overview-grid" id="staffOverviewGrid">
            {{-- STAFF CARD 1: AYAN --}}
            <article class="luxe-kpi-card luxe-staff-master-card" data-select-owner="ayan" role="button" tabindex="0" title="Click to inspect Ayan's 4 KPIs">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $healthOrbClass($ayanOverallPct) }} luxe-avatar-initials">
                        AY
                    </div>
                    <span class="luxe-owner-tag {{ $ayanPassingCount === $ayanTotalKpis ? 'tag-pass' : 'tag-ayan' }}">
                        {{ $ayanPassingCount }} / {{ $ayanTotalKpis }} KPIs PASSING &nearr;
                    </span>
                </div>

                <div>
                    <h3 class="luxe-staff-name">Ayan</h3>
                    <div class="luxe-staff-role">Marketing &amp; Ads Lead</div>
                </div>

                <div class="luxe-card-hero">
                    {{ number_format($ayanOverallPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="hl-rose">{{ number_format($totalAdRev, 0) }} / {{ number_format($mtdCombinedTarget, 0) }} QAR MTD</span> ({{ number_format($adConv, 1) }}% Conv) &bull;
                    <span>{{ $sanaPostedTotal }}/{{ $sanaCumulativeTarget }} Content</span>
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $healthBarClass($ayanOverallPct) }}" style="width: {{ min(100, max(4, $ayanOverallPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span class="luxe-click-hint">Click to inspect individual KPIs &rarr;</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- STAFF CARD 2: LADEN --}}
            <article class="luxe-kpi-card luxe-staff-master-card" data-select-owner="laden" role="button" tabindex="0" title="Click to inspect Laden's 4 KPIs">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $healthOrbClass($ladenOverallPct) }} luxe-avatar-initials">
                        LA
                    </div>
                    <span class="luxe-owner-tag {{ $ladenPassingCount === $ladenTotalKpis ? 'tag-pass' : 'tag-laden' }}">
                        {{ $ladenPassingCount }} / {{ $ladenTotalKpis }} KPIs PASSING &nearr;
                    </span>
                </div>

                <div>
                    <h3 class="luxe-staff-name">Laden</h3>
                    <div class="luxe-staff-role">Customer Service &amp; Sales Lead</div>
                </div>

                <div class="luxe-card-hero">
                    {{ number_format($ladenOverallPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $bkPass ? 'hl-emerald' : 'hl-champagne' }}">{{ $mtdBk }}/{{ $mtdBkTarget }} Bookings MTD ({{ $todayBk }}/{{ $ladenDailyBkTarget }} Today)</span> &bull;
                    <span class="{{ $chatPass ? 'hl-emerald' : 'hl-rose' }}">{{ number_format($chatAvg, 1) }}% Chat QA</span> &bull;
                    <span class="{{ $leadPass ? 'hl-emerald' : 'hl-champagne' }}">{{ number_format($leadConv, 1) }}% Lead Conv</span> &bull;
                    <span class="{{ $cmpPass ? 'hl-emerald' : 'hl-ruby' }}">{{ number_format($cmpPct, 0) }}% SLA</span>
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $healthBarClass($ladenOverallPct) }}" style="width: {{ min(100, max(4, $ladenOverallPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span class="luxe-click-hint">Click to inspect individual KPIs &rarr;</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- STAFF CARD 3: SANA --}}
            <article class="luxe-kpi-card luxe-staff-master-card" data-select-owner="sana" role="button" tabindex="0" title="Click to inspect Sana's 2 KPIs">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $healthOrbClass($sanaOverallPct) }} luxe-avatar-initials">
                        SA
                    </div>
                    <span class="luxe-owner-tag {{ $sanaPassingCount === $sanaTotalKpis ? 'tag-pass' : 'tag-sana' }}">
                        {{ $sanaPassingCount }} / {{ $sanaTotalKpis }} KPIs PASSING &nearr;
                    </span>
                </div>

                <div>
                    <h3 class="luxe-staff-name">Sana</h3>
                    <div class="luxe-staff-role">Organic Content &amp; Social</div>
                </div>

                <div class="luxe-card-hero">
                    {{ number_format($sanaOverallPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ ($contentPass || ($sanaReelsTarget > 0 && $sanaReels >= $sanaReelsTarget)) ? 'hl-emerald' : 'hl-rose' }}">{{ $sanaReels }}/{{ $sanaReelsTarget }} Reels</span> &bull;
                    <span class="{{ ($contentPass || ($sanaStoriesTarget > 0 && $sanaStories >= $sanaStoriesTarget)) ? 'hl-emerald' : 'hl-champagne' }}">{{ $sanaStories }}/{{ $sanaStoriesTarget }} Stories</span> &bull;
                    <span class="{{ $repliesPass ? 'hl-emerald' : 'hl-ruby' }}">{{ $lateCount }} {{ $lateCount === 1 ? 'Late Reply' : 'Late Replies' }}</span>
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $healthBarClass($sanaOverallPct) }}" style="width: {{ min(100, max(4, $sanaOverallPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span class="luxe-click-hint">Click to inspect individual KPIs &rarr;</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- STAFF CARD 4: AHANA (OLD AIRPORT BRANCH MANAGER) --}}
            <article class="luxe-kpi-card luxe-staff-master-card" data-select-owner="ahana" role="button" tabindex="0" title="Click to inspect Ahana's 2 KPIs">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $healthOrbClass($ahanaOverallPct) }} luxe-avatar-initials">
                        AH
                    </div>
                    <span class="luxe-owner-tag {{ $ahanaPassingCount === $ahanaTotalKpis ? 'tag-pass' : 'tag-ahana' }}">
                        {{ $ahanaPassingCount }} / {{ $ahanaTotalKpis }} KPIs PASSING &nearr;
                    </span>
                </div>

                <div>
                    <h3 class="luxe-staff-name">Ahana</h3>
                    <div class="luxe-staff-role">Old Airport Branch Manager</div>
                </div>

                <div class="luxe-card-hero">
                    {{ number_format($ahanaOverallPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $ahanaEvalPass ? 'hl-emerald' : 'hl-rose' }}">{{ $ahanaCompletedEvals }}/{{ $ahanaExpectedEvals }} Staff Evals</span> &bull;
                    <span class="{{ $ahanaMysteryPass ? 'hl-emerald' : 'hl-champagne' }}">{{ number_format($ahanaMysteryAvgPct, 1) }}% Branch Audit ({{ $ahanaAuditsCount }} {{ $ahanaAuditsCount === 1 ? 'visit' : 'visits' }})</span>
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $healthBarClass($ahanaOverallPct) }}" style="width: {{ min(100, max(4, $ahanaOverallPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span class="luxe-click-hint">Click to inspect individual KPIs &rarr;</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- STAFF CARD 5: RHEA (WAKRAH BRANCH MANAGER) --}}
            <article class="luxe-kpi-card luxe-staff-master-card" data-select-owner="rhea" role="button" tabindex="0" title="Click to inspect Rhea's 2 KPIs">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $healthOrbClass($rheaOverallPct) }} luxe-avatar-initials">
                        RH
                    </div>
                    <span class="luxe-owner-tag {{ $rheaPassingCount === $rheaTotalKpis ? 'tag-pass' : 'tag-rhea' }}">
                        {{ $rheaPassingCount }} / {{ $rheaTotalKpis }} KPIs PASSING &nearr;
                    </span>
                </div>

                <div>
                    <h3 class="luxe-staff-name">Rhea</h3>
                    <div class="luxe-staff-role">Wakrah Branch Manager</div>
                </div>

                <div class="luxe-card-hero">
                    {{ number_format($rheaOverallPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $rheaEvalPass ? 'hl-emerald' : 'hl-rose' }}">{{ $rheaCompletedEvals }}/{{ $rheaExpectedEvals }} Staff Evals</span> &bull;
                    <span class="{{ $rheaMysteryPass ? 'hl-emerald' : 'hl-champagne' }}">{{ number_format($rheaMysteryAvgPct, 1) }}% Branch Audit ({{ $rheaAuditsCount }} {{ $rheaAuditsCount === 1 ? 'visit' : 'visits' }})</span>
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $healthBarClass($rheaOverallPct) }}" style="width: {{ min(100, max(4, $rheaOverallPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span class="luxe-click-hint">Click to inspect individual KPIs &rarr;</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- STAFF CARD 6: AREEBA (CHAT AGENT) --}}
            <article class="luxe-kpi-card luxe-staff-master-card" data-select-owner="areeba" role="button" tabindex="0" title="Click to inspect Areeba's KPIs">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $healthOrbClass($areebaOverallPct) }} luxe-avatar-initials">
                        AR
                    </div>
                    <span class="luxe-owner-tag {{ $areebaPassingCount === $areebaTotalKpis ? 'tag-pass' : 'tag-areeba' }}">
                        {{ $areebaPassingCount }} / {{ $areebaTotalKpis }} KPI PASSING &nearr;
                    </span>
                </div>

                <div>
                    <h3 class="luxe-staff-name">Areeba</h3>
                    <div class="luxe-staff-role">Customer Service Chat Agent</div>
                </div>

                <div class="luxe-card-hero">
                    {{ number_format($areebaMtdPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $areebaBkPass ? 'hl-emerald' : 'hl-rose' }}">{{ $areebaMtdBk }} / {{ $areebaMtdTarget }} Bookings MTD</span> ({{ $isCurrentMonthView ? ($areebaTodayBk . '/13 Today') : ($areebaDailyAvg . '/13 Daily Avg') }} &bull; {{ $areebaFullMoTarget }} Mo Target)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $healthBarClass($areebaOverallPct) }}" style="width: {{ min(100, max(0, $areebaOverallPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span class="luxe-click-hint">Click to inspect individual KPIs &rarr;</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- STAFF CARD 7: ZOYA (CHAT AGENT) --}}
            <article class="luxe-kpi-card luxe-staff-master-card" data-select-owner="zoya" role="button" tabindex="0" title="Click to inspect Zoya's KPIs">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $healthOrbClass($zoyaOverallPct) }} luxe-avatar-initials">
                        ZO
                    </div>
                    <span class="luxe-owner-tag {{ $zoyaPassingCount === $zoyaTotalKpis ? 'tag-pass' : 'tag-zoya' }}">
                        {{ $zoyaPassingCount }} / {{ $zoyaTotalKpis }} KPI PASSING &nearr;
                    </span>
                </div>

                <div>
                    <h3 class="luxe-staff-name">Zoya</h3>
                    <div class="luxe-staff-role">Customer Service Chat Agent</div>
                </div>

                <div class="luxe-card-hero">
                    {{ number_format($zoyaMtdPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $zoyaBkPass ? 'hl-emerald' : 'hl-rose' }}">{{ $zoyaMtdBk }} / {{ $zoyaMtdTarget }} Bookings MTD</span> ({{ $isCurrentMonthView ? ($zoyaTodayBk . '/13 Today') : ($zoyaDailyAvg . '/13 Daily Avg') }} &bull; {{ $zoyaFullMoTarget }} Mo Target)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $healthBarClass($zoyaOverallPct) }}" style="width: {{ min(100, max(0, $zoyaOverallPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span class="luxe-click-hint">Click to inspect individual KPIs &rarr;</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>
        </div>

        {{-- =====================================================================
             2B. INDIVIDUAL STAFF KPI BREAKDOWN VIEW (SHOWN WHEN A STAFF IS SELECTED)
             ===================================================================== --}}
        <div id="staffDetailHeader" class="luxe-staff-detail-banner is-hidden">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div id="detailOwnerOrb" class="luxe-icon-orb orb-rose luxe-avatar-initials">AY</div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h3 id="detailOwnerName" class="mb-0" style="font-size: 20px; font-weight: 700; color: var(--lx-cream);">Ayan</h3>
                        <span id="detailOwnerScore" class="luxe-owner-tag tag-ayan" style="font-size: 11.5px;">0.0% Overall</span>
                    </div>
                    <div id="detailOwnerRole" class="luxe-staff-role mb-0">Marketing &amp; Ads Lead</div>
                </div>
            </div>

            <button type="button" id="backToAllStaffBtn" class="luxe-back-all-btn">
                &larr; Back to All Staff Scorecards
            </button>
        </div>

        <div class="luxe-kpi-grid is-hidden" id="luxeKpiGrid">

            {{-- AYAN CARD 1: Old Airport Ad Revenue --}}
            <article class="luxe-kpi-card is-hidden" data-owner="ayan">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-rose">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.ads.index', ['month' => $selectedMonth ?? now()->format('Y-m')]) }}" class="luxe-owner-tag tag-ayan" title="Open Ads Conversion">AYAN &nearr;</a>
                </div>

                <div class="luxe-card-label">Old Airport Ad Revenue</div>

                <div class="luxe-card-hero">
                    {{ number_format($oaRev, 0) }} <span class="luxe-unit">QAR</span>
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $oaPass ? 'hl-emerald' : 'hl-rose' }}">{{ number_format($oaMtdPct, 0) }}%</span> of {{ number_format($mtdBranchTarget, 0) }} QAR MTD target (15K Full Mo)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $oaPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $oaMtdPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- AYAN CARD 2: Wakrah Ad Revenue --}}
            <article class="luxe-kpi-card is-hidden" data-owner="ayan">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-champagne">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.ads.index', ['month' => $selectedMonth ?? now()->format('Y-m')]) }}" class="luxe-owner-tag tag-ayan" title="Open Ads Conversion">AYAN &nearr;</a>
                </div>

                <div class="luxe-card-label">Wakrah Ad Revenue</div>

                <div class="luxe-card-hero">
                    {{ number_format($wkRev, 0) }} <span class="luxe-unit">QAR</span>
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $wkPass ? 'hl-emerald' : 'hl-champagne' }}">{{ number_format($wkMtdPct, 0) }}%</span> of {{ number_format($mtdBranchTarget, 0) }} QAR MTD target (15K Full Mo)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $wkPass ? 'fill-emerald' : 'fill-champagne' }}" style="width: {{ min(100, max(4, $wkMtdPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- AYAN CARD 3: Paid Ad Conversion --}}
            <article class="luxe-kpi-card is-hidden" data-owner="ayan">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-emerald">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.ads.index', ['month' => $selectedMonth ?? now()->format('Y-m')]) }}" class="luxe-owner-tag tag-ayan" title="Open Ads Conversion">AYAN &nearr;</a>
                </div>

                <div class="luxe-card-label">Paid Ad Conversion</div>

                <div class="luxe-card-hero">
                    {{ number_format($adConv, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    Target: <span class="{{ $adConvPass ? 'hl-emerald' : 'hl-ruby' }}">&ge; 20.0%</span> paid ({{ $ayan['booked_ad_leads'] ?? 0 }}/{{ $ayan['total_ad_leads'] ?? 0 }})@if($pipelineScheduledLeads > 0) &bull; <span class="hl-champagne">{{ $pipelineScheduledLeads }} scheduled on calendar</span>@endif
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $adConvPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $adConvBarPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>20% Target</span>
                    </div>
                </div>
            </article>

            {{-- AYAN CARD 4: Content & Social SLA Oversight --}}
            <article class="luxe-kpi-card is-hidden" data-owner="ayan">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $ayanOversightPass ? 'orb-emerald' : 'orb-berry' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.content-calendar.index', ['week' => $sanaInMonthWeek]) }}" class="luxe-owner-tag tag-ayan" title="Open Content Plan">AYAN &nearr;</a>
                </div>

                <div class="luxe-card-label">Content &amp; Social SLA Oversight</div>

                <div class="luxe-card-hero">
                    {{ number_format($ayanOversightPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $contentPass ? 'hl-emerald' : 'hl-rose' }}">{{ $sanaPostedTotal }}/{{ $sanaCumulativeTarget }} Content</span> &bull;
                    <span class="{{ $repliesPass ? 'hl-emerald' : 'hl-ruby' }}">{{ $lateCount }} Late Replies</span>
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $ayanOversightPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $ayanOversightPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- LADEN CARD 1: Daily Booking Quota --}}
            <article class="luxe-kpi-card is-hidden" data-owner="laden">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-amber">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <path d="M9 14l2 2 4-4"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.agents.index', ['month' => $ladenSelectedMonth, 'logs_from' => $ladenPeriodStart, 'logs_to' => $ladenPeriodEnd]) }}" class="luxe-owner-tag tag-laden" title="Open Agent Targets">LADEN &nearr;</a>
                </div>

                <div class="luxe-card-label">Daily Booking Quota</div>

                <div class="luxe-card-hero">
                    {{ $mtdBk }} <span class="luxe-unit">/ {{ $mtdBkTarget }}</span>
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $bkPass ? 'hl-emerald' : 'hl-champagne' }}">{{ number_format($mtdBkPct, 0) }}% MTD</span> ({{ $todayBk }}/{{ $ladenDailyBkTarget }} Today &bull; {{ $fullMoBkTarget }} Full Mo)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $bkPass ? 'fill-emerald' : 'fill-champagne' }}" style="width: {{ min(100, max(4, $mtdBkPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- LADEN CARD 2: Agent Chat Quality --}}
            <article class="luxe-kpi-card is-hidden" data-owner="laden">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-rose">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <path d="M9 10h6M9 14h4"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.chat-eval.index', ['month' => $ladenSelectedMonth]) }}" class="luxe-owner-tag tag-laden" title="Grade Agent Chats">LADEN &nearr;</a>
                </div>

                <div class="luxe-card-label">Agent Chat Quality</div>

                <div class="luxe-card-hero">
                    {{ number_format($chatAvg, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    Target: <span class="{{ $chatPass ? 'hl-emerald' : 'hl-ruby' }}">&ge; 85.0%</span> avg quality ({{ $laden['total_evaluations_count'] ?? 0 }} graded)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $chatPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $chatBarPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>85% Target</span>
                    </div>
                </div>
            </article>

            {{-- LADEN CARD 3: Lead Conversion Rate --}}
            <article class="luxe-kpi-card is-hidden" data-owner="laden">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-berry">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <polyline points="16 11 18 13 22 9"></polyline>
                        </svg>
                    </div>
                    <a href="{{ route('leads.index', ['start_date' => $ladenPeriodStart, 'end_date' => $ladenPeriodEnd]) }}" class="luxe-owner-tag tag-laden" title="Open CRM Leads">LADEN &nearr;</a>
                </div>

                <div class="luxe-card-label">Lead Conversion Rate</div>

                <div class="luxe-card-hero">
                    {{ number_format($leadConv, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    Target: <span class="{{ $leadPass ? 'hl-emerald' : 'hl-champagne' }}">&ge; 30.0% ({{ $bookedLeadsCnt }}/{{ $totalLeadsCnt }})</span> &bull;
                    <span class="{{ $unassignedCnt > 0 ? 'hl-ruby' : 'hl-emerald' }}">{{ $unassignedCnt }} Unassigned</span> &bull;
                    <span class="{{ $overdueCnt > 0 ? 'hl-champagne' : 'hl-emerald' }}">{{ $overdueCnt }} Overdue</span>
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $leadPass ? 'fill-emerald' : 'fill-champagne' }}" style="width: {{ min(100, max(4, $leadConvBarPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>30% Target</span>
                    </div>
                </div>
            </article>

            {{-- LADEN CARD 4: Complaint Resolution SLA --}}
            <article class="luxe-kpi-card is-hidden" data-owner="laden">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $cmpPass ? 'orb-emerald' : 'orb-ruby' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <path d="M9 12l2 2 4-4"></path>
                        </svg>
                    </div>
                    <a href="{{ route('staffs.index', ['tab' => 'complaints', 'complaints_from' => $ladenPeriodStart, 'complaints_to' => $ladenPeriodEnd]) }}" class="luxe-owner-tag tag-laden" title="Resolve Complaints">LADEN &nearr;</a>
                </div>

                <div class="luxe-card-label">Complaint Resolution SLA</div>

                <div class="luxe-card-hero">
                    {{ number_format($cmpPct, 0) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $openCmp === 0 ? 'hl-emerald' : ($cmpPass ? 'hl-champagne' : 'hl-ruby') }}">{{ $openCmp }} Open</span> &bull; &le; 48h resolution
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $cmpPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $cmpPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100% SLA</span>
                    </div>
                </div>
            </article>

            {{-- SANA CARD 1: Organic Content Plan (MTD) --}}
            <article class="luxe-kpi-card is-hidden" data-owner="sana">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-champagne">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.content-calendar.index', ['week' => $sanaInMonthWeek]) }}" class="luxe-owner-tag tag-sana" title="Open Weekly Content Schedule">SANA &nearr;</a>
                </div>

                <div class="luxe-card-label">Organic Content Plan (MTD)</div>

                <div class="luxe-card-hero">
                    {{ $sanaPostedTotal }} <span class="luxe-unit">/ {{ $sanaCumulativeTarget }}</span>
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ ($contentPass || ($sanaReelsTarget > 0 && $sanaReels >= $sanaReelsTarget)) ? 'hl-emerald' : 'hl-rose' }}">{{ $sanaReels }}/{{ $sanaReelsTarget }} Reels</span> &bull;
                    <span class="{{ ($contentPass || ($sanaStoriesTarget > 0 && $sanaStories >= $sanaStoriesTarget)) ? 'hl-emerald' : 'hl-champagne' }}">{{ $sanaStories }}/{{ $sanaStoriesTarget }} Stories</span> &bull; {{ $shortPeriodBadge }}
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $contentPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $execPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- SANA CARD 2: Social Reply SLA --}}
            <article class="luxe-kpi-card is-hidden" data-owner="sana">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $repliesPass ? 'orb-emerald' : 'orb-berry' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                    </div>

                    <div class="d-flex align-items-center gap-1 flex-wrap justify-content-end">
                        @if($isAdminUser)
                            <button type="button" class="luxe-micro-btn" data-bs-toggle="modal" data-bs-target="#logLateReplyModal" title="Log Late Reply Breach">+</button>
                        @endif
                        @if($lateCount > 0)
                            <button type="button" class="luxe-proof-btn" data-bs-toggle="modal" data-bs-target="#viewLateRepliesModal" title="View Late Reply Proof">
                                View Proof ({{ $lateCount }})
                            </button>
                        @endif
                        <a href="{{ route('kpi.content-calendar.index', ['week' => $sanaInMonthWeek]) }}" class="luxe-owner-tag tag-sana" title="Open Weekly Content Schedule">SANA &nearr;</a>
                    </div>
                </div>

                <div class="luxe-card-label">Social Reply SLA</div>

                <div class="luxe-card-hero">
                    {{ $lateCount }} <span class="luxe-unit">Late</span>
                </div>

                <div class="luxe-card-delta">
                    Target: <span class="{{ $repliesPass ? 'hl-emerald' : 'hl-ruby' }}">0 late replies</span> &bull; Syncs to Ayan
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $repliesPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ $repliesPass ? 100 : 4 }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100% SLA</span>
                    </div>
                </div>
            </article>

            {{-- AHANA CARD 1: Monday Staff Evaluation Compliance --}}
            <article class="luxe-kpi-card is-hidden" data-owner="ahana">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $ahanaEvalPass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.evaluations.index', ['branch' => 'old_airport', 'month' => $selectedMonth]) }}" class="luxe-owner-tag tag-ahana" title="Open Salon Staff Evaluations">AHANA &nearr;</a>
                </div>

                <div class="luxe-card-label">Monday Staff Evaluation Compliance</div>

                <div class="luxe-card-hero">
                    {{ number_format($ahanaEvalCompliancePct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $ahanaEvalPass ? 'hl-emerald' : 'hl-rose' }}">{{ $ahanaCompletedEvals }}/{{ $ahanaExpectedEvals }} Evaluated</span> &bull; {{ $ahanaMondaysElapsed }} {{ \Illuminate\Support\Str::plural('Mon', $ahanaMondaysElapsed) }} MTD (100% Target)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $ahanaEvalPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $ahanaEvalCompliancePct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- AHANA CARD 2: Branch Mystery Shopper Score --}}
            <article class="luxe-kpi-card is-hidden" data-owner="ahana">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $ahanaMysteryPass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            <path d="M9 14l2 2 4-4"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.branch-evaluation.index', ['branch' => 'old_airport', 'month' => $selectedMonth]) }}" class="luxe-owner-tag tag-ahana" title="Open Old Airport Branch Evaluations">AHANA &nearr;</a>
                </div>

                <div class="luxe-card-label">Branch Mystery Shopper Score</div>

                <div class="luxe-card-hero">
                    {{ number_format($ahanaMysteryAvgPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    Target: <span class="{{ $ahanaMysteryPass ? 'hl-emerald' : 'hl-ruby' }}">&ge; 85.0% avg</span> ({{ $ahanaAuditsCount }} Evaluations MTD)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $ahanaMysteryPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $ahanaMysteryAttainmentPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>85% Target</span>
                    </div>
                </div>
            </article>

            {{-- RHEA CARD 1: Monday Staff Evaluation Compliance --}}
            <article class="luxe-kpi-card is-hidden" data-owner="rhea">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $rheaEvalPass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.evaluations.index', ['branch' => 'wakrah', 'month' => $selectedMonth]) }}" class="luxe-owner-tag tag-rhea" title="Open Salon Staff Evaluations">RHEA &nearr;</a>
                </div>

                <div class="luxe-card-label">Monday Staff Evaluation Compliance</div>

                <div class="luxe-card-hero">
                    {{ number_format($rheaEvalCompliancePct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $rheaEvalPass ? 'hl-emerald' : 'hl-rose' }}">{{ $rheaCompletedEvals }}/{{ $rheaExpectedEvals }} Evaluated</span> &bull; {{ $rheaMondaysElapsed }} {{ \Illuminate\Support\Str::plural('Mon', $rheaMondaysElapsed) }} MTD (100% Target)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $rheaEvalPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $rheaEvalCompliancePct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- RHEA CARD 2: Branch Mystery Shopper Score --}}
            <article class="luxe-kpi-card is-hidden" data-owner="rhea">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $rheaMysteryPass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            <path d="M9 14l2 2 4-4"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.branch-evaluation.index', ['branch' => 'wakrah', 'month' => $selectedMonth]) }}" class="luxe-owner-tag tag-rhea" title="Open Wakrah Branch Evaluations">RHEA &nearr;</a>
                </div>

                <div class="luxe-card-label">Branch Mystery Shopper Score</div>

                <div class="luxe-card-hero">
                    {{ number_format($rheaMysteryAvgPct, 1) }}%
                </div>

                <div class="luxe-card-delta">
                    Target: <span class="{{ $rheaMysteryPass ? 'hl-emerald' : 'hl-ruby' }}">&ge; 85.0% avg</span> ({{ $rheaAuditsCount }} Evaluations MTD)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $rheaMysteryPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(4, $rheaMysteryAttainmentPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>85% Target</span>
                    </div>
                </div>
            </article>

            {{-- AREEBA CARD 1: MTD Booking Quota (01 – Current Day) --}}
            <article class="luxe-kpi-card is-hidden" data-owner="areeba">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $areebaBkPass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <path d="M9 14l2 2 4-4"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.agents.index', ['month' => $effectiveMonthStr]) }}" class="luxe-owner-tag tag-areeba" title="Open Agent Booking Targets">AREEBA &nearr;</a>
                </div>

                <div class="luxe-card-label">MTD Booking Quota</div>

                <div class="luxe-card-hero">
                    {{ $areebaMtdBk }} <span class="luxe-unit">/ {{ $areebaMtdTarget }}</span>
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $areebaBkPass ? 'hl-emerald' : 'hl-rose' }}">{{ number_format($areebaMtdPct, 1) }}%</span> of MTD Target (13/day &times; {{ $daysElapsedVal }} days &bull; {{ $areebaFullMoTarget }} Full Mo)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $areebaBkPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(0, $areebaMtdPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- AREEBA CARD 2: Today's Daily Booking Target / Historical Daily Avg Pace --}}
            <article class="luxe-kpi-card is-hidden" data-owner="areeba">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $areebaCard2Pass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.agents.index', ['month' => $effectiveMonthStr]) }}" class="luxe-owner-tag tag-areeba" title="Open Agent Shift Logs">AREEBA &nearr;</a>
                </div>

                <div class="luxe-card-label">
                    @if($isCurrentMonthView)
                        Today's Daily Booking Target
                    @else
                        Daily Avg Pace
                    @endif
                </div>

                <div class="luxe-card-hero">
                    {{ $isCurrentMonthView ? $areebaTodayBk : $areebaDailyAvg }} <span class="luxe-unit">/ 13</span>
                </div>

                <div class="luxe-card-delta">
                    @if($isCurrentMonthView)
                        <span class="{{ $areebaCard2Pass ? 'hl-emerald' : 'hl-rose' }}">{{ number_format($areebaTodayPct, 1) }}%</span> of Daily Quota ({{ $areebaTodayBk >= 13 ? 'Daily Quota Met!' : (max(0, 13 - $areebaTodayBk) . ' left today') }})
                    @else
                        <span class="{{ $areebaCard2Pass ? 'hl-emerald' : 'hl-rose' }}">{{ $areebaDailyAvg }} / 13 per day</span> across {{ $monthLabel }}
                    @endif
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $areebaCard2Pass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(0, $areebaCard2BarPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- ZOYA CARD 1: MTD Booking Quota (01 – Current Day) --}}
            <article class="luxe-kpi-card is-hidden" data-owner="zoya">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $zoyaBkPass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <path d="M9 14l2 2 4-4"></path>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.agents.index', ['month' => $effectiveMonthStr]) }}" class="luxe-owner-tag tag-zoya" title="Open Agent Booking Targets">ZOYA &nearr;</a>
                </div>

                <div class="luxe-card-label">MTD Booking Quota</div>

                <div class="luxe-card-hero">
                    {{ $zoyaMtdBk }} <span class="luxe-unit">/ {{ $zoyaMtdTarget }}</span>
                </div>

                <div class="luxe-card-delta">
                    <span class="{{ $zoyaBkPass ? 'hl-emerald' : 'hl-rose' }}">{{ number_format($zoyaMtdPct, 1) }}%</span> of MTD Target (13/day &times; {{ $daysElapsedVal }} days &bull; {{ $zoyaFullMoTarget }} Full Mo)
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $zoyaBkPass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(0, $zoyaMtdPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>

            {{-- ZOYA CARD 2: Today's Daily Booking Target / Historical Daily Avg Pace --}}
            <article class="luxe-kpi-card is-hidden" data-owner="zoya">
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb {{ $zoyaCard2Pass ? 'orb-emerald' : 'orb-rose' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <a href="{{ route('kpi.agents.index', ['month' => $effectiveMonthStr]) }}" class="luxe-owner-tag tag-zoya" title="Open Agent Shift Logs">ZOYA &nearr;</a>
                </div>

                <div class="luxe-card-label">
                    @if($isCurrentMonthView)
                        Today's Daily Booking Target
                    @else
                        Daily Avg Pace
                    @endif
                </div>

                <div class="luxe-card-hero">
                    {{ $isCurrentMonthView ? $zoyaTodayBk : $zoyaDailyAvg }} <span class="luxe-unit">/ 13</span>
                </div>

                <div class="luxe-card-delta">
                    @if($isCurrentMonthView)
                        <span class="{{ $zoyaCard2Pass ? 'hl-emerald' : 'hl-rose' }}">{{ number_format($zoyaTodayPct, 1) }}%</span> of Daily Quota ({{ $zoyaTodayBk >= 13 ? 'Daily Quota Met!' : (max(0, 13 - $zoyaTodayBk) . ' left today') }})
                    @else
                        <span class="{{ $zoyaCard2Pass ? 'hl-emerald' : 'hl-rose' }}">{{ $zoyaDailyAvg }} / 13 per day</span> across {{ $monthLabel }}
                    @endif
                </div>

                <div class="luxe-card-viz viz-bar">
                    <div class="luxe-bar-track">
                        <div class="luxe-bar-fill {{ $zoyaCard2Pass ? 'fill-emerald' : 'fill-rose-gold' }}" style="width: {{ min(100, max(0, $zoyaCard2BarPct)) }}%"></div>
                    </div>
                    <div class="luxe-bar-scale">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </article>
        </div>
    </div>

    {{-- ADMIN-ONLY MODAL: Log Late Reply Breach --}}
    @if($isAdminUser)
        <div class="modal fade" id="logLateReplyModal" tabindex="-1" aria-labelledby="logLateReplyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content luxe-modal-content">
                    <form method="POST" action="{{ route('kpi.late-replies.update') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="month" value="{{ $selectedMonth ?? now('Asia/Qatar')->format('Y-m') }}">

                        <div class="modal-header luxe-modal-header">
                            <h5 class="modal-title luxe-modal-title" id="logLateReplyModalLabel">Log Late Reply Breach</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body luxe-modal-body">
                            <p class="luxe-modal-sub mb-3">
                                Attach timestamped screenshot proof of the delayed social media reply. Logging a breach sets Sana's Social Reply SLA and Ayan's SLA Oversight to 0% for that month until cleared.
                            </p>

                            <div class="mb-3">
                                <label for="lateReplyIncidentAt" class="form-label luxe-form-label">Incident Date &amp; Time (Doha) <span class="text-danger">*</span></label>
                                <input
                                    type="datetime-local"
                                    id="lateReplyIncidentAt"
                                    name="incident_at"
                                    class="form-control luxe-form-input"
                                    value="{{ old('incident_at', now('Asia/Qatar')->format('Y-m-d\TH:i')) }}"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="lateReplyScreenshot" class="form-label luxe-form-label">Screenshot Upload <span class="text-danger">*</span></label>
                                <input
                                    type="file"
                                    id="lateReplyScreenshot"
                                    name="screenshot"
                                    class="form-control luxe-form-input"
                                    accept="image/*"
                                    required
                                >
                                <div class="luxe-form-hint mt-1">Accepted: JPG, PNG, WEBP up to 5 MB.</div>
                            </div>

                            <div class="mb-0">
                                <label for="lateReplyNotes" class="form-label luxe-form-label">Short Note (Optional)</label>
                                <textarea
                                    id="lateReplyNotes"
                                    name="notes"
                                    rows="2"
                                    maxlength="500"
                                    class="form-control luxe-form-input"
                                    placeholder="e.g. Instagram DM @client — 2h 40m reply delay"
                                >{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <div class="modal-footer luxe-modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm luxe-submit-btn">Save Breach Proof</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- READ-ONLY PROOF MODAL (Visible to all KPI viewers; Admin-only Delete per row) --}}
    @if($lateCount > 0)
        <div class="modal fade" id="viewLateRepliesModal" tabindex="-1" aria-labelledby="viewLateRepliesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content luxe-modal-content">
                    <div class="modal-header luxe-modal-header">
                        <h5 class="modal-title luxe-modal-title" id="viewLateRepliesModalLabel">
                            Late Reply SLA Breaches ({{ $lateCount }}) &mdash; {{ $selectedMonthLabel ?? ($selectedMonth ?? '') }}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body luxe-modal-body">
                        @if($lateReplyLogs->isEmpty())
                            <div class="luxe-proof-card">
                                <div class="luxe-proof-meta">
                                    {{ $lateCount }} legacy late {{ $lateCount === 1 ? 'reply' : 'replies' }} recorded on weekly plan rows for this period.
                                </div>
                            </div>
                        @else
                            <div class="d-flex flex-column gap-3">
                                @foreach($lateReplyLogs as $breachLog)
                                    @php
                                        $proofUrl = $breachLog->screenshotUrl();
                                        $incidentFormatted = $breachLog->incident_at
                                            ? $breachLog->incident_at->copy()->setTimezone('Asia/Qatar')->format('d M Y, g:i A')
                                            : '—';
                                    @endphp
                                    <div class="luxe-proof-card">
                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                            <div>
                                                <div class="luxe-proof-time">{{ $incidentFormatted }} <span class="luxe-proof-tz">Asia/Qatar</span></div>
                                                <div class="luxe-proof-meta">
                                                    Logged by {{ $breachLog->logger?->name ?? 'Admin' }}
                                                </div>
                                                @if(filled($breachLog->notes))
                                                    <div class="luxe-proof-note mt-1">{{ $breachLog->notes }}</div>
                                                @endif
                                            </div>

                                            @if($isAdminUser)
                                                <form method="POST" action="{{ route('kpi.late-replies.destroy', $breachLog) }}" onsubmit="return confirm('Delete this late reply breach record and its screenshot?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="month" value="{{ $selectedMonth ?? now('Asia/Qatar')->format('Y-m') }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger luxe-trash-btn" title="Delete Breach">
                                                        <i class="bx bx-trash"></i> Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>

                                        @if($proofUrl)
                                            <div class="luxe-proof-img-wrap mt-2">
                                                <a href="{{ $proofUrl }}" target="_blank" rel="noopener">
                                                    <img src="{{ $proofUrl }}" alt="Late reply proof screenshot" class="luxe-proof-img">
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer luxe-modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
        /* =====================================================================
           LALEEN OPS DARK LUXURY KPI DASHBOARD PALETTE & ARCHITECTURE
           ===================================================================== */
        .luxe-exec-dashboard {
            --lx-bg-deep: #140c0f;
            --lx-bg-card: #1d1216;
            --lx-bg-card-hover: #25171c;
            --lx-border: rgba(229, 184, 168, 0.14);
            --lx-border-strong: rgba(229, 184, 168, 0.32);
            --lx-cream: #f8efe6;
            --lx-muted: #b59e99;
            --lx-rose-gold: #e5b8a8;
            --lx-champagne: #d4a373;
            --lx-emerald: #34d399;
            --lx-ruby: #fb7185;
            --lx-amber: #f59e0b;

            background: radial-gradient(circle at 18% 0%, rgba(229, 184, 168, 0.08) 0%, transparent 45%),
                        radial-gradient(circle at 85% 100%, rgba(52, 211, 153, 0.05) 0%, transparent 45%),
                        var(--lx-bg-deep);
            border: 1px solid var(--lx-border);
            border-radius: 24px;
            padding: 28px 28px 32px;
            color: var(--lx-cream);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
        }

        /* 1. TOP HEADER BAR */
        .luxe-exec-topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 26px;
        }

        .luxe-exec-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--lx-cream);
            letter-spacing: -0.02em;
            margin: 0 0 4px 0;
            line-height: 1.2;
        }

        .luxe-exec-subtitle {
            font-size: 13.5px;
            color: var(--lx-muted);
            margin: 0 0 14px 0;
        }

        .luxe-owner-pills {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .luxe-owner-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(29, 18, 22, 0.85);
            border: 1px solid var(--lx-border);
            color: var(--lx-muted);
            font-size: 12.5px;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .luxe-owner-pill:hover {
            color: var(--lx-cream);
            border-color: var(--lx-border-strong);
        }

        .luxe-owner-pill.active {
            background: linear-gradient(135deg, rgba(229, 184, 168, 0.22), rgba(212, 163, 115, 0.14));
            border-color: var(--lx-rose-gold);
            color: var(--lx-cream);
            box-shadow: 0 0 18px rgba(229, 184, 168, 0.18);
        }

        .luxe-staff-select-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .luxe-staff-select {
            appearance: none;
            -webkit-appearance: none;
            background: rgba(29, 18, 22, 0.92);
            border: 1px solid var(--lx-border-strong);
            color: var(--lx-cream);
            font-size: 12.5px;
            font-weight: 600;
            padding: 8px 36px 8px 15px;
            border-radius: 999px;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .luxe-staff-select:hover,
        .luxe-staff-select:focus {
            border-color: var(--lx-rose-gold);
            box-shadow: 0 0 16px rgba(229, 184, 168, 0.16);
        }

        .luxe-staff-select option {
            background: #1d1216;
            color: var(--lx-cream);
            padding: 8px;
        }

        .luxe-staff-select-chevron {
            position: absolute;
            right: 13px;
            pointer-events: none;
            color: var(--lx-rose-gold);
        }

        .pill-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .pill-dot-all { background: var(--lx-cream); }
        .pill-dot-ayan { background: var(--lx-rose-gold); }
        .pill-dot-laden { background: var(--lx-champagne); }
        .pill-dot-sana { background: var(--lx-emerald); }
        .pill-dot-ahana { background: var(--lx-rose-gold); }
        .pill-dot-rhea { background: var(--lx-champagne); }

        .luxe-exec-topbar-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .luxe-mode-switcher {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 6px;
            background: rgba(20, 12, 15, 0.9);
            border: 1px solid var(--lx-border-strong);
            padding: 4px;
            border-radius: 13px;
        }

        .luxe-mode-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 13px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 600;
            color: var(--lx-muted);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .luxe-mode-btn:hover {
            color: var(--lx-cream);
            background: rgba(229, 184, 168, 0.08);
        }

        .luxe-mode-btn.active {
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1f1412;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(217, 143, 131, 0.28);
        }

        .luxe-date-pill {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #1d1216;
            border: 1px solid var(--lx-border-strong);
            border-radius: 12px;
            padding: 9px 15px;
            color: var(--lx-cream);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            margin: 0;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transition: border-color 0.2s ease;
        }

        .luxe-date-pill:hover {
            border-color: var(--lx-rose-gold);
        }

        .luxe-date-pill svg {
            color: var(--lx-rose-gold);
        }

        .luxe-date-native-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .luxe-quick-nav-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .luxe-subpage-link {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--lx-rose-gold);
            background: rgba(229, 184, 168, 0.08);
            border: 1px solid var(--lx-border);
            padding: 4px 10px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .luxe-subpage-link:hover {
            background: rgba(229, 184, 168, 0.18);
            color: var(--lx-cream);
            border-color: var(--lx-border-strong);
        }

        /* 2A. HOME STAFF OVERVIEW GRID (MAX 3 PER ROW) */
        .luxe-staff-overview-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        @media (max-width: 1199px) {
            .luxe-staff-overview-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .luxe-staff-overview-grid {
                grid-template-columns: 1fr;
            }
        }

        .luxe-staff-master-card {
            cursor: pointer;
            min-height: 270px;
        }

        .luxe-staff-master-card:hover {
            border-color: var(--lx-rose-gold) !important;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.55), 0 0 24px rgba(229, 184, 168, 0.15) !important;
        }

        .luxe-avatar-initials {
            font-size: 13.5px;
            font-weight: 800;
            letter-spacing: 0.06em;
        }

        .luxe-staff-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--lx-cream);
            margin: 0 0 2px 0;
        }

        .luxe-staff-role {
            font-size: 12.5px;
            color: var(--lx-muted);
            margin-bottom: 12px;
        }

        .luxe-click-hint {
            color: var(--lx-rose-gold);
            font-size: 10.5px;
            font-weight: 600;
            opacity: 0.85;
        }

        /* 2B. STAFF DETAIL MINI HEADER & INDIVIDUAL KPI GRID */
        .luxe-staff-detail-banner {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            margin-bottom: 18px;
            background: rgba(29, 18, 22, 0.9);
            border: 1px solid var(--lx-border-strong);
            border-radius: 16px;
        }

        .luxe-back-all-btn {
            background: rgba(229, 184, 168, 0.1);
            border: 1px solid var(--lx-border-strong);
            color: var(--lx-cream);
            font-size: 12px;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .luxe-back-all-btn:hover {
            background: rgba(229, 184, 168, 0.22);
            border-color: var(--lx-rose-gold);
        }

        .luxe-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        @media (max-width: 1199px) {
            .luxe-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .luxe-kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        .is-hidden {
            display: none !important;
        }

        .luxe-kpi-card {
            background: linear-gradient(180deg, rgba(35, 22, 27, 0.92) 0%, rgba(23, 14, 18, 0.96) 100%);
            border: 1px solid var(--lx-border);
            border-radius: 18px;
            padding: 20px 20px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 255px;
            position: relative;
            overflow: hidden;
            transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease;
        }

        .luxe-kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(229, 184, 168, 0.35), transparent);
        }

        .luxe-kpi-card:hover {
            transform: translateY(-3px);
            border-color: var(--lx-border-strong);
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.45);
        }

        .luxe-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .luxe-icon-orb {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .orb-rose {
            background: radial-gradient(circle, rgba(229, 184, 168, 0.25) 0%, rgba(229, 184, 168, 0.06) 100%);
            border: 1px solid rgba(229, 184, 168, 0.4);
            color: var(--lx-rose-gold);
            box-shadow: 0 0 18px rgba(229, 184, 168, 0.2);
        }

        .orb-champagne {
            background: radial-gradient(circle, rgba(212, 163, 115, 0.25) 0%, rgba(212, 163, 115, 0.06) 100%);
            border: 1px solid rgba(212, 163, 115, 0.4);
            color: var(--lx-champagne);
            box-shadow: 0 0 18px rgba(212, 163, 115, 0.2);
        }

        .orb-emerald {
            background: radial-gradient(circle, rgba(52, 211, 153, 0.24) 0%, rgba(52, 211, 153, 0.05) 100%);
            border: 1px solid rgba(52, 211, 153, 0.4);
            color: var(--lx-emerald);
            box-shadow: 0 0 18px rgba(52, 211, 153, 0.2);
        }

        .orb-amber {
            background: radial-gradient(circle, rgba(245, 158, 11, 0.24) 0%, rgba(245, 158, 11, 0.05) 100%);
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: var(--lx-amber);
            box-shadow: 0 0 18px rgba(245, 158, 11, 0.18);
        }

        .orb-berry,
        .orb-ruby {
            background: radial-gradient(circle, rgba(251, 113, 133, 0.24) 0%, rgba(251, 113, 133, 0.05) 100%);
            border: 1px solid rgba(251, 113, 133, 0.4);
            color: var(--lx-ruby);
            box-shadow: 0 0 18px rgba(251, 113, 133, 0.2);
        }

        .luxe-owner-tag {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            padding: 3px 9px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid var(--lx-border);
            color: var(--lx-muted);
            background: rgba(255, 255, 255, 0.03);
            transition: all 0.2s ease;
        }

        .tag-pass {
            border-color: rgba(52, 211, 153, 0.4);
            color: var(--lx-emerald);
            background: rgba(52, 211, 153, 0.1);
        }

        .luxe-owner-tag:hover {
            color: var(--lx-cream);
            border-color: var(--lx-rose-gold);
        }

        .luxe-micro-btn {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1px solid rgba(251, 113, 133, 0.4);
            background: rgba(251, 113, 133, 0.14);
            color: var(--lx-cream);
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            transition: all 0.15s ease;
        }

        .luxe-micro-btn:hover {
            background: rgba(251, 113, 133, 0.32);
            border-color: var(--lx-ruby);
        }

        .luxe-card-label {
            font-size: 15px;
            font-weight: 600;
            color: var(--lx-cream);
            line-height: 1.35;
            margin-bottom: 10px;
        }

        .luxe-card-hero {
            font-size: 36px;
            font-weight: 800;
            color: var(--lx-cream);
            letter-spacing: -0.025em;
            line-height: 1.08;
            margin-bottom: 8px;
        }

        .luxe-unit {
            font-size: 18px;
            font-weight: 600;
            color: var(--lx-rose-gold);
        }

        .luxe-card-delta {
            font-size: 12px;
            color: var(--lx-muted);
            margin-bottom: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .hl-emerald { color: var(--lx-emerald); font-weight: 700; }
        .hl-rose { color: var(--lx-rose-gold); font-weight: 700; }
        .hl-champagne { color: var(--lx-champagne); font-weight: 700; }
        .hl-ruby { color: var(--lx-ruby); font-weight: 700; }
        .text-cream { color: var(--lx-cream); font-weight: 600; }

        /* Card Bottom Visualizations */
        .luxe-card-viz {
            margin-top: auto;
            padding-top: 6px;
        }

        .luxe-bar-track {
            width: 100%;
            height: 7px;
            background: rgba(255, 255, 255, 0.07);
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 6px;
        }

        .luxe-bar-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        .fill-rose-gold {
            background: linear-gradient(90deg, #c98a78, #e5b8a8);
            box-shadow: 0 0 12px rgba(229, 184, 168, 0.45);
        }

        .fill-champagne {
            background: linear-gradient(90deg, #b58352, #d4a373);
            box-shadow: 0 0 12px rgba(212, 163, 115, 0.45);
        }

        .fill-emerald {
            background: linear-gradient(90deg, #10b981, #34d399);
            box-shadow: 0 0 12px rgba(52, 211, 153, 0.45);
        }

        .luxe-bar-scale {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10.5px;
            color: var(--lx-muted);
            font-weight: 500;
        }

        .luxe-sparkline {
            width: 100%;
            height: 48px;
            display: block;
            overflow: visible;
        }

        .luxe-proof-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #fecdd3;
            background: rgba(251, 113, 133, 0.16);
            border: 1px solid rgba(251, 113, 133, 0.42);
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .luxe-proof-btn:hover {
            background: rgba(251, 113, 133, 0.28);
            color: #fff;
        }

        .luxe-modal-content {
            background: #1a1014;
            color: #f8efe6;
            border: 1px solid rgba(229, 184, 168, 0.28);
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.65);
        }

        .luxe-modal-header,
        .luxe-modal-footer {
            border-color: rgba(229, 184, 168, 0.14);
            padding: 16px 20px;
        }

        .luxe-modal-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: #f8efe6;
            font-size: 18px;
        }

        .luxe-modal-body {
            padding: 18px 20px;
        }

        .luxe-modal-sub {
            font-size: 12.5px;
            color: #bfa59d;
        }

        .luxe-form-label {
            font-size: 12px;
            font-weight: 600;
            color: #e5b8a8;
        }

        .luxe-form-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(229, 184, 168, 0.25);
            color: #f8efe6;
            border-radius: 10px;
            font-size: 13px;
        }

        .luxe-form-input:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #e5b8a8;
            color: #fff;
            box-shadow: 0 0 0 2px rgba(229, 184, 168, 0.18);
        }

        .luxe-form-hint {
            font-size: 11px;
            color: #9f857d;
        }

        .luxe-submit-btn {
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1f1412;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 6px 16px;
        }

        .luxe-submit-btn:hover {
            opacity: 0.92;
            color: #140c0f;
        }

        .luxe-proof-card {
            background: rgba(255, 255, 255, 0.035);
            border: 1px solid rgba(251, 113, 133, 0.25);
            border-radius: 12px;
            padding: 14px;
        }

        .luxe-proof-time {
            font-size: 13.5px;
            font-weight: 700;
            color: #fda4af;
        }

        .luxe-proof-tz {
            font-size: 10.5px;
            font-weight: 600;
            color: #bfa59d;
            margin-left: 4px;
        }

        .luxe-proof-meta {
            font-size: 11.5px;
            color: #bfa59d;
        }

        .luxe-proof-note {
            font-size: 12.5px;
            color: #f8efe6;
        }

        .luxe-proof-img-wrap {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(229, 184, 168, 0.18);
            max-height: 360px;
            background: #10090c;
        }

        .luxe-proof-img {
            display: block;
            width: 100%;
            max-height: 360px;
            object-fit: contain;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pills = document.querySelectorAll('[data-owner-filter]');
            const staffDropdown = document.getElementById('staffScorecardDropdown');
            const overviewGrid = document.getElementById('staffOverviewGrid');
            const detailHeader = document.getElementById('staffDetailHeader');
            const kpiGrid = document.getElementById('luxeKpiGrid');
            const kpiCards = document.querySelectorAll('#luxeKpiGrid .luxe-kpi-card[data-owner]');
            const masterCards = document.querySelectorAll('[data-select-owner]');
            const backToAllBtn = document.getElementById('backToAllStaffBtn');

            const staffMeta = {
                ayan: {
                    initials: 'AY',
                    name: 'Ayan',
                    role: 'Marketing & Ads Lead — 4 Executive KPIs',
                    score: '{{ number_format($ayanOverallPct, 1) }}% Overall ({{ $ayanPassingCount }}/{{ $ayanTotalKpis }} Passing)',
                    orbClass: '{{ $healthOrbClass($ayanOverallPct) }}'
                },
                laden: {
                    initials: 'LA',
                    name: 'Laden',
                    role: 'Customer Service & Sales Lead — 4 Executive KPIs',
                    score: '{{ number_format($ladenOverallPct, 1) }}% Overall ({{ $ladenPassingCount }}/{{ $ladenTotalKpis }} Passing)',
                    orbClass: '{{ $healthOrbClass($ladenOverallPct) }}'
                },
                sana: {
                    initials: 'SA',
                    name: 'Sana',
                    role: 'Organic Content & Social — 2 Executive KPIs',
                    score: '{{ number_format($sanaOverallPct, 1) }}% Overall ({{ $sanaPassingCount }}/{{ $sanaTotalKpis }} Passing)',
                    orbClass: '{{ $healthOrbClass($sanaOverallPct) }}'
                },
                ahana: {
                    initials: 'AH',
                    name: 'Ahana',
                    role: 'Old Airport Branch Manager — 2 Executive KPIs',
                    score: '{{ number_format($ahanaOverallPct, 1) }}% Overall ({{ $ahanaPassingCount }}/{{ $ahanaTotalKpis }} Passing)',
                    orbClass: '{{ $healthOrbClass($ahanaOverallPct) }}'
                },
                rhea: {
                    initials: 'RH',
                    name: 'Rhea',
                    role: 'Wakrah Branch Manager — 2 Executive KPIs',
                    score: '{{ number_format($rheaOverallPct, 1) }}% Overall ({{ $rheaPassingCount }}/{{ $rheaTotalKpis }} Passing)',
                    orbClass: '{{ $healthOrbClass($rheaOverallPct) }}'
                },
                areeba: {
                    initials: 'AR',
                    name: 'Areeba',
                    role: 'Customer Service Chat Agent — 13 Bookings/Day Quota',
                    score: '{{ number_format($areebaOverallPct, 1) }}% MTD Quota ({{ $areebaMtdBk }}/{{ $areebaMtdTarget }} Bookings)',
                    orbClass: '{{ $healthOrbClass($areebaOverallPct) }}'
                },
                zoya: {
                    initials: 'ZO',
                    name: 'Zoya',
                    role: 'Customer Service Chat Agent — 13 Bookings/Day Quota',
                    score: '{{ number_format($zoyaOverallPct, 1) }}% MTD Quota ({{ $zoyaMtdBk }}/{{ $zoyaMtdTarget }} Bookings)',
                    orbClass: '{{ $healthOrbClass($zoyaOverallPct) }}'
                }
            };

            function applyOwnerView(owner) {
                pills.forEach(pill => {
                    pill.classList.toggle('active', pill.getAttribute('data-owner-filter') === owner);
                });

                if (staffDropdown) {
                    staffDropdown.value = (owner && staffMeta[owner]) ? owner : 'all';
                }

                if (owner === 'all' || !staffMeta[owner]) {
                    // Show ONLY the Staff Overview Cards
                    overviewGrid.classList.remove('is-hidden');
                    detailHeader.classList.add('is-hidden');
                    kpiGrid.classList.add('is-hidden');
                    kpiCards.forEach(card => card.classList.add('is-hidden'));
                } else {
                    // Hide the Home Staff Overview Cards and show ONLY the selected staff member's KPIs
                    overviewGrid.classList.add('is-hidden');
                    detailHeader.classList.remove('is-hidden');
                    kpiGrid.classList.remove('is-hidden');

                    const meta = staffMeta[owner];
                    const orbEl = document.getElementById('detailOwnerOrb');
                    if (orbEl) {
                        orbEl.textContent = meta.initials;
                        orbEl.className = 'luxe-icon-orb luxe-avatar-initials ' + meta.orbClass;
                    }
                    document.getElementById('detailOwnerName').textContent = meta.name;
                    document.getElementById('detailOwnerRole').textContent = meta.role;
                    document.getElementById('detailOwnerScore').textContent = meta.score;

                    kpiCards.forEach(card => {
                        const cardOwner = card.getAttribute('data-owner');
                        if (cardOwner === owner) {
                            card.classList.remove('is-hidden');
                        } else {
                            card.classList.add('is-hidden');
                        }
                    });
                }
            }

            pills.forEach(pill => {
                pill.addEventListener('click', function () {
                    applyOwnerView(this.getAttribute('data-owner-filter'));
                });
            });

            if (staffDropdown) {
                staffDropdown.addEventListener('change', function () {
                    applyOwnerView(this.value);
                });
            }

            masterCards.forEach(card => {
                card.addEventListener('click', function () {
                    applyOwnerView(this.getAttribute('data-select-owner'));
                });
                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        applyOwnerView(this.getAttribute('data-select-owner'));
                    }
                });
            });

            if (backToAllBtn) {
                backToAllBtn.addEventListener('click', function () {
                    applyOwnerView('all');
                });
            }
        });
    </script>
@endsection
