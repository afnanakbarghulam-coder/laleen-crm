@extends('layouts.app')

@section('title', 'F.R.I.D.A.Y. Command Center — Laleen Ops')

@section('content')
<style>
    .friday-shell {
        color: #f4eae6;
        font-family: 'Poppins', sans-serif;
    }

    .friday-header-card {
        background: linear-gradient(135deg, rgba(26, 20, 18, 0.96), rgba(38, 28, 25, 0.94));
        border: 1px solid rgba(217, 143, 131, 0.32);
        border-radius: 16px;
        padding: 18px 22px;
        box-shadow: 0 14px 36px rgba(0, 0, 0, 0.42);
    }

    .friday-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 5px 13px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        background: rgba(46, 204, 113, 0.12);
        border: 1px solid rgba(46, 204, 113, 0.42);
        color: #7ef2a6;
    }

    .friday-gear-toggle {
        display: inline-flex;
        background: rgba(15, 11, 10, 0.85);
        border: 1px solid rgba(217, 143, 131, 0.28);
        border-radius: 999px;
        padding: 3px;
        gap: 4px;
    }

    .friday-gear-btn {
        border: none;
        background: transparent;
        color: #cbb8b0;
        font-size: 12px;
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.16s ease;
    }

    .friday-gear-btn.active {
        background: linear-gradient(135deg, #d98f83, #c9a66b);
        color: #191413;
        font-weight: 700;
        box-shadow: 0 4px 14px rgba(217, 143, 131, 0.35);
    }

    .friday-select {
        background: rgba(18, 14, 13, 0.92);
        color: #f4eae6;
        border: 1px solid rgba(217, 143, 131, 0.35);
        border-radius: 999px;
        padding: 6px 14px;
        font-size: 12px;
        outline: none;
    }

    .friday-telemetry-card {
        background: linear-gradient(155deg, rgba(28, 21, 19, 0.94), rgba(20, 15, 14, 0.94));
        border: 1px solid rgba(217, 143, 131, 0.22);
        border-radius: 14px;
        padding: 16px 18px;
        height: 100%;
        transition: border-color 0.2s ease, transform 0.2s ease;
    }

    .friday-telemetry-card:hover {
        border-color: rgba(217, 143, 131, 0.52);
        transform: translateY(-2px);
    }

    .friday-card-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #c9a66b;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .friday-metric-big {
        font-family: 'Playfair Display', serif;
        font-size: 23px;
        font-weight: 700;
        color: #f9efec;
        line-height: 1.2;
    }

    .friday-subrow {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: #cbb8b0;
        padding: 4px 0;
        border-bottom: 1px dashed rgba(217, 143, 131, 0.12);
    }

    .friday-subrow:last-child {
        border-bottom: none;
    }

    .friday-console-card {
        background: radial-gradient(circle at top center, rgba(217, 143, 131, 0.10), rgba(19, 14, 13, 0.97) 65%);
        border: 1px solid rgba(217, 143, 131, 0.34);
        border-radius: 18px;
        padding: 22px;
        box-shadow: 0 18px 46px rgba(0, 0, 0, 0.52);
    }

    .friday-reactor-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 10px 0 16px;
        position: relative;
    }

    .friday-visualizer-canvas {
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(217, 143, 131, 0.16) 0%, rgba(18, 13, 12, 0.92) 72%);
        border: 1px solid rgba(201, 166, 107, 0.42);
        box-shadow: 0 0 36px rgba(217, 143, 131, 0.24), inset 0 0 24px rgba(201, 166, 107, 0.18);
    }

    .friday-chip-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
        margin-bottom: 16px;
    }

    .friday-chip {
        background: rgba(217, 143, 131, 0.10);
        border: 1px solid rgba(217, 143, 131, 0.32);
        color: #f4eae6;
        border-radius: 999px;
        padding: 6px 13px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .friday-chip:hover {
        background: rgba(217, 143, 131, 0.25);
        border-color: #e79a91;
        transform: translateY(-1px);
    }

    .friday-chat-feed {
        max-height: 390px;
        overflow-y: auto;
        padding: 12px;
        border-radius: 12px;
        background: rgba(12, 9, 8, 0.72);
        border: 1px solid rgba(217, 143, 131, 0.16);
        margin-bottom: 14px;
    }

    .friday-msg {
        margin-bottom: 14px;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        line-height: 1.6;
    }

    .friday-msg-user {
        background: rgba(217, 143, 131, 0.16);
        border: 1px solid rgba(217, 143, 131, 0.35);
        color: #fff;
        margin-left: auto;
        max-width: 82%;
    }

    .friday-msg-ai {
        background: rgba(29, 22, 20, 0.95);
        border-left: 3px solid #e79a91;
        border-top: 1px solid rgba(201, 166, 107, 0.24);
        border-right: 1px solid rgba(201, 166, 107, 0.24);
        border-bottom: 1px solid rgba(201, 166, 107, 0.24);
        color: #f4eae6;
        max-width: 92%;
    }

    .friday-tool-trace {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 8px;
        margin-right: 6px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 10.5px;
        background: rgba(201, 166, 107, 0.13);
        border: 1px solid rgba(201, 166, 107, 0.38);
        color: #e6c891;
    }

    .friday-mic-btn {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 1px solid rgba(217, 143, 131, 0.55);
        background: linear-gradient(135deg, rgba(217, 143, 131, 0.24), rgba(201, 166, 107, 0.22));
        color: #f4eae6;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        transition: all 0.18s ease;
        flex-shrink: 0;
    }

    .friday-mic-btn.recording {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border-color: #ff7979;
        color: #fff;
        box-shadow: 0 0 22px rgba(231, 76, 60, 0.75);
        animation: fridayPulse 1.1s infinite;
    }

    @keyframes fridayPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.08); }
        100% { transform: scale(1); }
    }
</style>

<div class="friday-shell">
    {{-- Top Control Bar --}}
    <div class="friday-header-card mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h4 class="mb-0" style="font-family: 'Playfair Display', serif; color: #f9efec; font-weight: 700;">
                        F.R.I.D.A.Y. Command Center
                    </h4>
                    <span class="friday-status-pill" id="fridayStatusPill">
                        🟢 F.R.I.D.A.Y. ONLINE · READ-ONLY EXECUTIVE ACCESS
                    </span>
                </div>
                <div style="font-size: 12px; color: #cbb8b0;">
                    50% Tactical Secretary · 35% Hormozi Revenue Math · 15% Sandberg COO Compliance · Strictly <code>SELECT</code> Read-Only
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                {{-- Thinking Gear Toggle --}}
                <div class="friday-gear-toggle" role="group" aria-label="Thinking Mode">
                    <button type="button" class="friday-gear-btn active" id="modeFastBtn" data-mode="fast" title="Model: {{ $commandModel }}">
                        ⚡ Fast Secretary (Flash)
                    </button>
                    <button type="button" class="friday-gear-btn" id="modeDeepBtn" data-mode="deep" title="Model: {{ $thinkingModel }}">
                        🧠 Deep Consultant (Argon Thinking)
                    </button>
                </div>

                {{-- Voice Profile Selector --}}
                <select id="voiceProfileSelect" class="friday-select" aria-label="Voice Profile">
                    <option value="friday_tactical">🎧 F.R.I.D.A.Y. Tactical (Irish/British HUD)</option>
                    <option value="kore_coo">⚡ Kore (Firm COO)</option>
                    <option value="aoede_advisor">✨ Aoede (Warm Advisor)</option>
                </select>

                {{-- Auto-Speak / Mute Toggle --}}
                <button type="button" id="toggleMuteBtn" class="friday-select" style="cursor: pointer;">
                    🔊 Voice Output: ON
                </button>
            </div>
        </div>
    </div>

    {{-- 4 Instant Read-Only Telemetry Cards --}}
    @php
        $oaRev = $telemetry['revenue']['branches']['old_airport'] ?? [];
        $wkRev = $telemetry['revenue']['branches']['wakrah'] ?? [];
        $leadsTel = $telemetry['leads'] ?? [];
        $retTel = $telemetry['retention'] ?? [];
        $staffTel = $telemetry['staff'] ?? [];
        $mktTel = $telemetry['marketing'] ?? [];
    @endphp
    <div class="row g-3 mb-4" id="telemetryGrid">
        {{-- Card 1: Today & MTD Cash (Old Airport vs Wakrah) --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="friday-telemetry-card">
                <div class="friday-card-label">
                    <span>💰 Today & MTD Cash</span>
                    <span class="badge bg-label-warning" style="font-size: 10px;">Old Airport vs Wakrah</span>
                </div>
                <div class="friday-metric-big mb-2" id="telTodayNet">
                    {{ number_format((float) ($telemetry['revenue']['today_total_net_qar'] ?? 0), 0) }} QAR <small style="font-size: 11px; color: #cbb8b0;">Today</small>
                </div>
                <div class="friday-subrow">
                    <span>Old Airport (Today / MTD)</span>
                    <strong>{{ number_format((float) ($oaRev['today_net_qar'] ?? 0), 0) }} / {{ number_format((float) ($oaRev['mtd_net_qar'] ?? 0), 0) }} QAR</strong>
                </div>
                <div class="friday-subrow">
                    <span>Wakrah (Today / MTD)</span>
                    <strong>{{ number_format((float) ($wkRev['today_net_qar'] ?? 0), 0) }} / {{ number_format((float) ($wkRev['mtd_net_qar'] ?? 0), 0) }} QAR</strong>
                </div>
                <div class="friday-subrow">
                    <span>Bookings Today (Done / Open)</span>
                    <strong>{{ (int) ($telemetry['revenue']['today_completed_visits'] ?? 0) }} done · {{ (int) ($telemetry['revenue']['today_upcoming_bookings'] ?? 0) }} open</strong>
                </div>
            </div>
        </div>

        {{-- Card 2: Sales Floor & Leads --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="friday-telemetry-card">
                <div class="friday-card-label">
                    <span>🔥 Sales Floor & Leads</span>
                    <span class="badge {{ ($leadsTel['mtd_conversion_pct'] ?? 0) >= 30 ? 'bg-label-success' : 'bg-label-danger' }}" style="font-size: 10px;">
                        {{ number_format((float) ($leadsTel['mtd_conversion_pct'] ?? 0), 1) }}% Conv (30% Floor)
                    </span>
                </div>
                <div class="friday-metric-big mb-2">
                    ~{{ number_format((float) ($leadsTel['top_three_potential_qar'] ?? 0), 0) }} QAR <small style="font-size: 11px; color: #cbb8b0;">Top 3 Calls</small>
                </div>
                <div class="friday-subrow">
                    <span>Overdue Follow-ups</span>
                    <strong style="color: #ff8a80;">{{ (int) ($leadsTel['overdue_count'] ?? 0) }}</strong>
                </div>
                <div class="friday-subrow">
                    <span>Due Today / Unscheduled</span>
                    <strong>{{ (int) ($leadsTel['due_today_count'] ?? 0) }} due · {{ (int) ($leadsTel['unscheduled_count'] ?? 0) }} unscheduled</strong>
                </div>
                <div class="friday-subrow">
                    <span>MTD Booked / Total Leads</span>
                    <strong>{{ (int) ($leadsTel['mtd_booked_leads'] ?? 0) }} / {{ (int) ($leadsTel['mtd_total_leads'] ?? 0) }}</strong>
                </div>
            </div>
        </div>

        {{-- Card 3: Retention Radar --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="friday-telemetry-card">
                <div class="friday-card-label">
                    <span>🔄 Retention Radar</span>
                    <span class="badge bg-label-info" style="font-size: 10px;">Zero-Overlap Engine</span>
                </div>
                <div class="friday-metric-big mb-2">
                    {{ (int) ($retTel['total_actionable_clients'] ?? 0) }} <small style="font-size: 11px; color: #cbb8b0;">Actionable Clients</small>
                </div>
                <div class="friday-subrow">
                    <span>Due for Refill (incl. 1st Visit)</span>
                    <strong>{{ (int) ($retTel['due_for_refill_count'] ?? 0) }} clients</strong>
                </div>
                <div class="friday-subrow">
                    <span>Packages Ending Soon</span>
                    <strong>{{ (int) ($retTel['package_renewal_count'] ?? 0) }} clients</strong>
                </div>
                <div class="friday-subrow">
                    <span>Missing VIPs (45d+ Away)</span>
                    <strong style="color: #ffd180;">{{ (int) ($retTel['bleeding_vips_count'] ?? 0) }} VIPs</strong>
                </div>
            </div>
        </div>

        {{-- Card 4: Staff & Marketing KPIs --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="friday-telemetry-card">
                <div class="friday-card-label">
                    <span>📈 Staff & Marketing KPIs</span>
                    <span class="badge bg-label-primary" style="font-size: 10px;">1,700 QAR Target</span>
                </div>
                <div class="friday-metric-big mb-2">
                    {{ number_format((float) ($mktTel['attributed_ad_revenue_qar'] ?? 0), 0) }} QAR <small style="font-size: 11px; color: #cbb8b0;">Ad Revenue</small>
                </div>
                <div class="friday-subrow">
                    <span>Prorated Upsell Benchmark</span>
                    <strong>{{ number_format((float) ($staffTel['prorated_target_qar'] ?? 0), 0) }} QAR (85% Floor)</strong>
                </div>
                <div class="friday-subrow">
                    <span>Monthly Ad Conv. (20% Floor)</span>
                    <strong>{{ number_format((float) ($mktTel['ad_conversion_pct'] ?? 0), 1) }}% ({{ (int) ($mktTel['ad_leads_booked'] ?? 0) }}/{{ (int) ($mktTel['ad_leads_total'] ?? 0) }})</strong>
                </div>
                <div class="friday-subrow">
                    <span>Weekly Content (4 Reels + 7 Stories)</span>
                    <strong>{{ (int) ($mktTel['reels_posted'] ?? 0) }}/4 R · {{ (int) ($mktTel['stories_posted'] ?? 0) }}/7 S</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Center Interactive F.R.I.D.A.Y. Core & Conversation Console --}}
    <div class="friday-console-card">
        <div class="friday-reactor-wrap">
            <canvas id="fridayVisualizer" class="friday-visualizer-canvas" width="240" height="240"></canvas>
            <div id="fridayStateLabel" class="mt-2" style="font-size: 12px; color: #e79a91; font-weight: 600; letter-spacing: 0.05em;">
                F.R.I.D.A.Y. CORE STANDBY · TAP MICROPHONE OR SELECT A BRIEFING CHIP
            </div>
        </div>

        {{-- 6 One-Click Tactical Briefing Chips --}}
        <div class="friday-chip-bar">
            <button type="button" class="friday-chip" data-prompt="Give me the Morning Executive Brief across Old Airport and Wakrah.">
                ☀️ Morning Executive Brief
            </button>
            <button type="button" class="friday-chip" data-prompt="Audit Old Airport vs Wakrah cash, card payments, and today's bookings.">
                💰 Audit Old Airport vs Wakrah Cash
            </button>
            <button type="button" class="friday-chip" data-prompt="Who are today's Top Money Leads and how is our agent conversion rate performing against the 30% floor?">
                🔥 Who Are Today's Top Money Leads?
            </button>
            <button type="button" class="friday-chip" data-prompt="Check the Retention Radar leaks across Due for Refill, Packages Ending, and Missing VIPs.">
                🔄 Check Retention Radar Leaks
            </button>
            <button type="button" class="friday-chip" data-prompt="Show me the Staff Upsell Leaderboard against the 1,700 QAR monthly target and weekly evaluations.">
                📈 Staff Upsell Leaderboard
            </button>
            <button type="button" class="friday-chip" data-prompt="Audit our Marketing & Ad ROAS, same-month Ad Lead conversion %, and weekly Reels/Stories compliance.">
                🎯 Audit Marketing & Ad ROAS
            </button>
        </div>

        {{-- Conversation Feed --}}
        <div class="friday-chat-feed" id="fridayChatFeed">
            <div class="friday-msg friday-msg-ai">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong style="color: #e79a91; font-size: 12px;">F.R.I.D.A.Y. · EXECUTIVE HUD</strong>
                    <span style="font-size: 11px; color: #cbb8b0;">Read-Only Telemetry Online</span>
                </div>
                <div>
                    Good day, Boss. Live telemetry is locked on <strong>Old Airport</strong> and <strong>Wakrah</strong>. Today's collected net cash is <strong>{{ number_format((float) ($telemetry['revenue']['today_total_net_qar'] ?? 0), 0) }} QAR</strong> ({{ number_format((float) ($telemetry['revenue']['mtd_total_net_qar'] ?? 0), 0) }} QAR MTD), with <strong>{{ (int) ($leadsTel['overdue_count'] ?? 0) }} overdue follow-ups</strong> and <strong>{{ (int) ($retTel['total_actionable_clients'] ?? 0) }} retention clients</strong> ready for outreach. Tap the microphone or pick a tactical chip below.
                </div>
                <div>
                    <span class="friday-tool-trace">🔍 Inspected: Revenue & Bookings · Leads & Money Brief · Retention Radar</span>
                </div>
            </div>
        </div>

        {{-- Push-to-Talk Microphone + Text Input Bar --}}
        <form id="fridayAskForm" class="d-flex align-items-center gap-2">
            @csrf
            <button type="button" id="fridayMicBtn" class="friday-mic-btn" title="Tap to Speak to F.R.I.D.A.Y.">
                <i class="bx bx-microphone"></i>
            </button>
            <input
                type="text"
                id="fridayPromptInput"
                class="form-control"
                placeholder="Ask F.R.I.D.A.Y. anything about Old Airport, Wakrah, Leads, Retention, Staff Upsells, or Ads..."
                style="background: rgba(15, 11, 10, 0.9); border: 1px solid rgba(217, 143, 131, 0.35); color: #f4eae6; border-radius: 999px; padding: 11px 18px;"
            />
            <button type="submit" id="fridaySendBtn" class="btn" style="background: linear-gradient(135deg, #d98f83, #c9a66b); color: #191413; font-weight: 700; border-radius: 999px; padding: 10px 22px;">
                Ask F.R.I.D.A.Y.
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const askUrl = @json(route('command-center.ask'));
    const csrfToken = @json(csrf_token());

    let currentMode = 'fast';
    let voiceEnabled = true;
    let conversationHistory = [];
    let isSpeaking = false;
    let isRecording = false;
    let recognition = null;

    const modeFastBtn = document.getElementById('modeFastBtn');
    const modeDeepBtn = document.getElementById('modeDeepBtn');
    const voiceSelect = document.getElementById('voiceProfileSelect');
    const toggleMuteBtn = document.getElementById('toggleMuteBtn');
    const chatFeed = document.getElementById('fridayChatFeed');
    const askForm = document.getElementById('fridayAskForm');
    const promptInput = document.getElementById('fridayPromptInput');
    const micBtn = document.getElementById('fridayMicBtn');
    const stateLabel = document.getElementById('fridayStateLabel');
    const canvas = document.getElementById('fridayVisualizer');
    const ctx = canvas.getContext('2d');

    // Mode Gear Toggle
    [modeFastBtn, modeDeepBtn].forEach(btn => {
        btn.addEventListener('click', () => {
            currentMode = btn.dataset.mode;
            modeFastBtn.classList.toggle('active', currentMode === 'fast');
            modeDeepBtn.classList.toggle('active', currentMode === 'deep');
        });
    });

    // Mute Toggle
    toggleMuteBtn.addEventListener('click', () => {
        voiceEnabled = !voiceEnabled;
        toggleMuteBtn.textContent = voiceEnabled ? '🔊 Voice Output: ON' : '🔇 Voice Output: MUTED';
        if (!voiceEnabled && window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
    });

    // Arc-Reactor & Waveform Visualizer
    let phase = 0;
    function renderReactor() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const cx = canvas.width / 2;
        const cy = canvas.height / 2;
        const baseRadius = 64;
        const activeAmp = isSpeaking ? 16 : (isRecording ? 12 : 3);

        // Outer ring
        ctx.beginPath();
        ctx.strokeStyle = isRecording ? 'rgba(231, 76, 60, 0.75)' : 'rgba(201, 166, 107, 0.45)';
        ctx.lineWidth = 2;
        ctx.arc(cx, cy, 92, 0, Math.PI * 2);
        ctx.stroke();

        // Dynamic waveform ring
        ctx.beginPath();
        for (let angle = 0; angle < Math.PI * 2; angle += 0.08) {
            const wave = Math.sin(angle * 8 + phase) * activeAmp * Math.cos(angle * 3 - phase * 0.7);
            const r = baseRadius + wave;
            const x = cx + Math.cos(angle) * r;
            const y = cy + Math.sin(angle) * r;
            if (angle === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }
        ctx.closePath();
        ctx.strokeStyle = isSpeaking ? '#e79a91' : (isRecording ? '#ff7979' : 'rgba(217, 143, 131, 0.75)');
        ctx.lineWidth = isSpeaking ? 3 : 2;
        ctx.stroke();

        // Inner core glow
        const grad = ctx.createRadialGradient(cx, cy, 6, cx, cy, 44);
        grad.addColorStop(0, isSpeaking ? 'rgba(231, 154, 145, 0.85)' : 'rgba(201, 166, 107, 0.45)');
        grad.addColorStop(1, 'rgba(25, 20, 19, 0.05)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(cx, cy, 44, 0, Math.PI * 2);
        ctx.fill();

        phase += isSpeaking ? 0.16 : 0.04;
        requestAnimationFrame(renderReactor);
    }
    renderReactor();

    // Play Audio (Gemini WAV w/ WebAudio subtle metallic EQ filter OR Web Speech API fallback)
    function playFridayVoice(data) {
        if (!voiceEnabled) return;

        if (data.audio_base64 && data.audio_mime) {
            try {
                const audio = new Audio('data:' + data.audio_mime + ';base64,' + data.audio_base64);
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    const audioCtx = new AudioCtx();
                    const source = audioCtx.createMediaElementSource(audio);
                    const filter = audioCtx.createBiquadFilter();
                    filter.type = 'peaking';
                    filter.frequency.value = 2400;
                    filter.Q.value = 1.4;
                    filter.gain.value = (data.voice_meta && data.voice_meta.metallic_eq) ? 3.5 : 1.0;
                    source.connect(filter);
                    filter.connect(audioCtx.destination);
                }
                isSpeaking = true;
                stateLabel.textContent = 'F.R.I.D.A.Y. SPEAKING · LIVE VOICE STREAM';
                audio.onended = () => {
                    isSpeaking = false;
                    stateLabel.textContent = 'F.R.I.D.A.Y. CORE STANDBY';
                };
                audio.play().catch(() => speakWithWebSpeech(data.spoken_text, data.voice_meta));
                return;
            } catch (e) {
                // Fall through to Web Speech API
            }
        }

        speakWithWebSpeech(data.spoken_text || data.reply, data.voice_meta);
    }

    function speakWithWebSpeech(text, voiceMeta) {
        if (!voiceEnabled || !('speechSynthesis' in window) || !text) return;
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        const voices = window.speechSynthesis.getVoices();
        const prefs = (voiceMeta && voiceMeta.lang_pref) ? voiceMeta.lang_pref : ['en-IE', 'en-GB', 'en-US'];

        for (const pref of prefs) {
            const match = voices.find(v => v.lang && v.lang.toLowerCase().startsWith(pref.toLowerCase()));
            if (match) {
                utter.voice = match;
                break;
            }
        }
        utter.rate = (voiceMeta && voiceMeta.rate) ? voiceMeta.rate : 1.04;
        utter.pitch = (voiceMeta && voiceMeta.pitch) ? voiceMeta.pitch : 1.05;
        utter.onstart = () => {
            isSpeaking = true;
            stateLabel.textContent = 'F.R.I.D.A.Y. SPEAKING · TACTICAL HUD AUDIO';
        };
        utter.onend = () => {
            isSpeaking = false;
            stateLabel.textContent = 'F.R.I.D.A.Y. CORE STANDBY';
        };
        window.speechSynthesis.speak(utter);
    }

    function appendMessage(role, text, toolBadges = [], modeUsed = 'fast') {
        const div = document.createElement('div');
        div.className = 'friday-msg ' + (role === 'user' ? 'friday-msg-user' : 'friday-msg-ai');

        if (role === 'user') {
            div.textContent = text;
        } else {
            const header = document.createElement('div');
            header.className = 'd-flex align-items-center justify-content-between mb-1';
            header.innerHTML = `<strong style="color:#e79a91;font-size:12px;">F.R.I.D.A.Y. · ${modeUsed === 'deep' ? 'DEEP CONSULTANT' : 'FAST SECRETARY'}</strong>`;
            div.appendChild(header);

            const body = document.createElement('div');
            body.style.whiteSpace = 'pre-wrap';
            body.textContent = text;
            div.appendChild(body);

            if (Array.isArray(toolBadges) && toolBadges.length > 0) {
                const traceWrap = document.createElement('div');
                const labels = toolBadges.map(b => b.label || b.name).join(' · ');
                traceWrap.innerHTML = `<span class="friday-tool-trace">🔍 Inspected: ${labels}</span>`;
                div.appendChild(traceWrap);
            }
        }

        chatFeed.appendChild(div);
        chatFeed.scrollTop = chatFeed.scrollHeight;
    }

    async function sendCommand(promptText) {
        const cleanPrompt = (promptText || '').trim();
        if (!cleanPrompt) return;

        appendMessage('user', cleanPrompt);
        promptInput.value = '';
        stateLabel.textContent = currentMode === 'deep'
            ? 'F.R.I.D.A.Y. DEEP THINKING · AUDITING SALON TELEMETRY...'
            : 'F.R.I.D.A.Y. SCANNING LIVE CRM TELEMETRY...';

        try {
            const response = await fetch(askUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    prompt: cleanPrompt,
                    mode: currentMode,
                    voice_profile: voiceSelect.value,
                    history: conversationHistory.slice(-6)
                })
            });

            const data = await response.json();
            if (response.ok && data.reply) {
                conversationHistory.push({ role: 'user', content: cleanPrompt });
                conversationHistory.push({ role: 'assistant', content: data.reply });
                appendMessage('assistant', data.reply, data.tool_badges || [], data.mode || currentMode);
                playFridayVoice(data);
            } else {
                appendMessage('assistant', data.message || 'Boss, I hit a temporary link interruption. Please try again.');
            }
        } catch (err) {
            appendMessage('assistant', 'Boss, network telemetry timed out. Please retry your command.');
        } finally {
            if (!isSpeaking) {
                stateLabel.textContent = 'F.R.I.D.A.Y. CORE STANDBY';
            }
        }
    }

    askForm.addEventListener('submit', function (e) {
        e.preventDefault();
        sendCommand(promptInput.value);
    });

    document.querySelectorAll('.friday-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            sendCommand(chip.dataset.prompt);
        });
    });

    // Microphone SpeechRecognition / Push-to-Talk
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRec) {
        recognition = new SpeechRec();
        recognition.lang = 'en-US';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            isRecording = true;
            micBtn.classList.add('recording');
            stateLabel.textContent = 'LISTENING TO BOSS · SPEAK YOUR COMMAND...';
        };

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            if (transcript) {
                sendCommand(transcript);
            }
        };

        recognition.onend = () => {
            isRecording = false;
            micBtn.classList.remove('recording');
            if (!isSpeaking) {
                stateLabel.textContent = 'F.R.I.D.A.Y. CORE STANDBY';
            }
        };
    }

    micBtn.addEventListener('click', () => {
        if (!recognition) {
            promptInput.focus();
            stateLabel.textContent = 'VOICE MIC UNSUPPORTED IN THIS BROWSER · TYPE BELOW';
            return;
        }
        if (isRecording) {
            recognition.stop();
        } else {
            recognition.start();
        }
    });
})();
</script>
@endsection
