@extends('layouts.app')

@section('title', 'F.R.I.D.A.Y. — Laleen Ops')

@section('content')
<style>
    .friday-stage {
        min-height: calc(100vh - 140px);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 16px 24px 22px;
        color: #f4eae6;
        font-family: 'Poppins', sans-serif;
        background: radial-gradient(circle at 50% 42%, rgba(217, 143, 131, 0.11) 0%, rgba(16, 12, 11, 0.96) 66%);
        border: 1px solid rgba(217, 143, 131, 0.22);
        border-radius: 22px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
    }

    /* 1. Minimal Top Bar */
    .friday-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .friday-brand-title {
        font-family: 'Playfair Display', serif;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: 0.14em;
        color: #f9efec;
        margin: 0;
    }

    .friday-gear-toggle {
        display: inline-flex;
        background: rgba(12, 9, 8, 0.88);
        border: 1px solid rgba(217, 143, 131, 0.30);
        border-radius: 999px;
        padding: 4px;
        gap: 4px;
    }

    .friday-gear-btn {
        border: none;
        background: transparent;
        color: #cbb8b0;
        font-size: 12.5px;
        font-weight: 500;
        padding: 7px 16px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.18s ease;
    }

    .friday-gear-btn.active {
        background: linear-gradient(135deg, #d98f83, #c9a66b);
        color: #191413;
        font-weight: 700;
        box-shadow: 0 4px 16px rgba(217, 143, 131, 0.36);
    }

    /* 2. Center Interactive F.R.I.D.A.Y. Core Orb */
    .friday-orb-zone {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 28px 0;
        user-select: none;
    }

    .friday-orb-button {
        position: relative;
        width: 280px;
        height: 280px;
        border-radius: 50%;
        padding: 0;
        border: 1px solid rgba(201, 166, 107, 0.42);
        background: radial-gradient(circle, rgba(217, 143, 131, 0.18) 0%, rgba(16, 12, 11, 0.94) 72%);
        box-shadow: 0 0 48px rgba(217, 143, 131, 0.22), inset 0 0 28px rgba(201, 166, 107, 0.18);
        cursor: pointer;
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        outline: none;
    }

    .friday-orb-button:hover {
        transform: scale(1.025);
        border-color: rgba(231, 154, 145, 0.72);
    }

    .friday-orb-button.state-listening {
        border-color: rgba(126, 242, 166, 0.75);
        box-shadow: 0 0 58px rgba(46, 204, 113, 0.30), inset 0 0 32px rgba(46, 204, 113, 0.18);
    }

    .friday-orb-button.state-thinking {
        border-color: rgba(201, 166, 107, 0.85);
        box-shadow: 0 0 58px rgba(201, 166, 107, 0.36), inset 0 0 32px rgba(201, 166, 107, 0.22);
    }

    .friday-orb-button.state-speaking {
        border-color: rgba(231, 154, 145, 0.92);
        box-shadow: 0 0 68px rgba(231, 154, 145, 0.48), inset 0 0 36px rgba(201, 166, 107, 0.28);
    }

    .friday-orb-button.state-endpoint-flash {
        border-color: #ffeaa7;
        box-shadow: 0 0 82px rgba(255, 234, 167, 0.72), inset 0 0 42px rgba(217, 143, 131, 0.45);
        transform: scale(1.045);
    }

    .friday-visualizer-canvas {
        width: 280px;
        height: 280px;
        border-radius: 50%;
        pointer-events: none;
    }

    .friday-status-label {
        margin-top: 20px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #e79a91;
        text-align: center;
        min-height: 20px;
    }

    /* 3. Bottom Subtitle + Sleek Chat Bar */
    .friday-bottom-dock {
        max-width: 820px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .friday-subtitle-box {
        min-height: 82px;
        padding: 16px 22px;
        border-radius: 16px;
        background: rgba(18, 13, 12, 0.82);
        border: 1px solid rgba(217, 143, 131, 0.24);
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 6px;
        text-align: center;
        transition: border-color 0.2s ease;
    }

    .friday-user-echo {
        font-size: 12px;
        color: #a9958d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 18px;
    }

    .friday-spoken-words {
        font-size: 15.5px;
        font-weight: 500;
        line-height: 1.55;
        color: #f9efec;
    }

    .friday-whatsapp-pill {
        margin-top: 10px;
        padding: 10px 14px;
        border-radius: 14px;
        background: rgba(24, 38, 30, 0.84);
        border: 1px solid rgba(126, 242, 166, 0.38);
        display: none;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        text-align: left;
    }

    .friday-whatsapp-pill.visible {
        display: flex;
    }

    .friday-whatsapp-preview {
        flex: 1 1 360px;
        font-size: 13px;
        line-height: 1.45;
        color: #e4f7eb;
        white-space: normal;
        word-break: break-word;
    }

    .friday-whatsapp-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .friday-wa-open-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 999px;
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        color: #0e1711 !important;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none !important;
        transition: opacity 0.15s ease;
    }

    .friday-wa-open-btn:hover {
        opacity: 0.92;
    }

    .friday-wa-copy-btn {
        border: 1px solid rgba(217, 143, 131, 0.45);
        background: rgba(217, 143, 131, 0.15);
        color: #f4eae6;
        font-size: 11.5px;
        font-weight: 600;
        padding: 5px 11px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .friday-wa-copy-btn:hover {
        background: rgba(217, 143, 131, 0.28);
    }

    .friday-input-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(14, 10, 9, 0.92);
        border: 1px solid rgba(217, 143, 131, 0.34);
        border-radius: 999px;
        padding: 6px 8px 6px 10px;
    }

    .friday-mic-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 1px solid rgba(217, 143, 131, 0.48);
        background: rgba(217, 143, 131, 0.16);
        color: #f4eae6;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        cursor: pointer;
        transition: all 0.18s ease;
        flex-shrink: 0;
    }

    .friday-mic-btn.active {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        border-color: #7ef2a6;
        color: #101612;
        box-shadow: 0 0 18px rgba(46, 204, 113, 0.55);
    }

    .friday-text-input {
        flex: 1;
        background: transparent;
        border: none;
        color: #f4eae6;
        font-size: 14px;
        padding: 8px 10px;
        outline: none;
    }

    .friday-text-input::placeholder {
        color: #8c7770;
    }

    .friday-send-btn {
        border: none;
        background: linear-gradient(135deg, #d98f83, #c9a66b);
        color: #191413;
        font-weight: 700;
        font-size: 13px;
        border-radius: 999px;
        padding: 10px 22px;
        cursor: pointer;
        transition: opacity 0.15s ease;
    }

    .friday-send-btn:hover {
        opacity: 0.92;
    }
</style>

<div class="friday-stage">
    {{-- 1. Minimal Top Bar --}}
    <div class="friday-topbar">
        <h1 class="friday-brand-title">F.R.I.D.A.Y.</h1>

        <div class="friday-gear-toggle" role="group" aria-label="Thinking Mode">
            <button type="button" class="friday-gear-btn active" id="modeFastBtn" data-mode="fast" title="Model: {{ $commandModel }}">
                ⚡ Quick
            </button>
            <button type="button" class="friday-gear-btn" id="modeDeepBtn" data-mode="deep" title="Model: {{ $thinkingModel }}">
                🧠 Deep Thinking
            </button>
        </div>
    </div>

    {{-- 2. Center Interactive F.R.I.D.A.Y. Core Orb --}}
    <div class="friday-orb-zone">
        <button type="button" id="fridayOrbBtn" class="friday-orb-button" aria-label="Toggle Live Conversation with F.R.I.D.A.Y.">
            <canvas id="fridayVisualizer" class="friday-visualizer-canvas" width="280" height="280"></canvas>
        </button>

        <div id="fridayStateLabel" class="friday-status-label">
            TAP ORB TO START LIVE CONVERSATION
        </div>
    </div>

    {{-- 3. Bottom Subtitle + Sleek Chat Bar --}}
    <div class="friday-bottom-dock">
        <div class="friday-subtitle-box" id="fridaySubtitleBox">
            <div class="friday-user-echo" id="fridayUserEcho"></div>
            <div class="friday-spoken-words" id="fridaySpokenWords">
                Right then, Boss—tap the orb or start speaking whenever you're ready.
            </div>
            <div class="friday-whatsapp-pill" id="fridayWhatsappPill">
                <span class="friday-whatsapp-preview" id="fridayWhatsappPreview"></span>
                <div class="friday-whatsapp-actions">
                    <a href="#" target="_blank" rel="noopener noreferrer" class="friday-wa-open-btn" id="fridayWhatsappOpenLink">
                        💬 Open in WhatsApp
                    </a>
                    <button type="button" class="friday-wa-copy-btn" id="fridayWhatsappCopyBtn">
                        📋 Copy Pitch
                    </button>
                </div>
            </div>
        </div>

        <form id="fridayAskForm" class="friday-input-bar">
            @csrf
            <button type="button" id="fridayMicBtn" class="friday-mic-btn" title="Toggle Live Voice">
                <i class="bx bx-microphone"></i>
            </button>
            <input
                type="text"
                id="fridayPromptInput"
                class="friday-text-input"
                placeholder="Ask F.R.I.D.A.Y. anything..."
                autocomplete="off"
            />
            <button type="submit" id="fridaySendBtn" class="friday-send-btn">
                Send
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const askUrl = @json(route('command-center.ask'));
    const ttsUrl = @json(route('command-center.tts'));
    const csrfToken = @json(csrf_token());

    let currentMode = 'fast';
    let liveConversationActive = false;
    let currentState = 'idle'; // 'idle' | 'listening' | 'thinking' | 'speaking'
    let isThinking = false;
    let isSpeaking = false;
    let isSubmitting = false;
    let conversationHistory = [];

    let recognition = null;
    let listeningRestartTimer = null;
    let silenceTimer = null;
    let pendingTranscript = '';
    let currentTranscript = '';

    // Persistent Hardware Mic + VAD + MediaRecorder state for Turn 1, Turn 2, Turn 10+
    let micStream = null;
    let micAudioCtx = null;
    let micAnalyser = null;
    let micTimeDomainData = null;
    let vadIntervalTimer = null;
    let currentMicRms = 0;
    let speechDetectedInTurn = false;
    let speechActiveMs = 0;
    let silenceAfterSpeechMs = 0;
    let lastSpeechActivityAt = 0;
    let mediaRecorder = null;
    let mediaChunks = [];
    let mediaMimeType = 'audio/webm';
    let recorderStartedAt = 0;

    const STANDARD_SILENCE_MS = 1500;
    const HANGING_PHRASE_SILENCE_MS = 2200;
    const HANGING_PHRASE_SUFFIXES = [
        'can you tell me',
        'can you tell',
        'can you',
        'tell me',
        'what about',
        'how about'
    ];
    const HANGING_WORD_SUFFIXES = new Set([
        'and', 'or', 'but', 'so', 'because', 'if', 'for', 'in', 'at', 'to',
        'with', 'the', 'a', 'is', 'are', 'how', 'what', 'who', 'why', 'where', 'when'
    ]);

    function getRequiredSilenceMs(transcriptText) {
        const cleaned = (transcriptText || '')
            .toLowerCase()
            .replace(/[.,!?;:'"()-]+$/g, '')
            .replace(/\s+/g, ' ')
            .trim();

        if (!cleaned) {
            return STANDARD_SILENCE_MS;
        }

        for (const phrase of HANGING_PHRASE_SUFFIXES) {
            if (cleaned === phrase || cleaned.endsWith(' ' + phrase)) {
                return HANGING_PHRASE_SILENCE_MS;
            }
        }

        const words = cleaned.split(' ');
        const lastWord = words[words.length - 1] || '';
        if (HANGING_WORD_SUFFIXES.has(lastWord)) {
            return HANGING_PHRASE_SILENCE_MS;
        }

        return STANDARD_SILENCE_MS;
    }

    function scheduleUnifiedSilenceCommit() {
        if (silenceTimer) {
            clearTimeout(silenceTimer);
            silenceTimer = null;
        }
        if (!liveConversationActive || isThinking || isSpeaking || isSubmitting || !speechDetectedInTurn) {
            return;
        }

        const activeText = (currentTranscript || pendingTranscript || '').trim();
        const requiredSilenceMs = getRequiredSilenceMs(activeText);
        const elapsedSinceActivity = lastSpeechActivityAt > 0
            ? Math.max(0, performance.now() - lastSpeechActivityAt)
            : 0;
        const remainingMs = Math.max(80, requiredSilenceMs - elapsedSinceActivity);

        silenceTimer = setTimeout(() => {
            if (!liveConversationActive || isThinking || isSpeaking || isSubmitting || !speechDetectedInTurn) {
                return;
            }
            const latestText = (currentTranscript || pendingTranscript || '').trim();
            const targetSilenceMs = getRequiredSilenceMs(latestText);
            const silentForMs = lastSpeechActivityAt > 0 ? (performance.now() - lastSpeechActivityAt) : targetSilenceMs;

            if (silentForMs >= targetSilenceMs - 30) {
                commitTurnVoiceInput();
            } else {
                scheduleUnifiedSilenceCommit();
            }
        }, remainingMs);
    }

    let currentAudio = null;
    let audioCtx = null;
    let analyser = null;
    let frequencyData = null;
    let wordRevealTimer = null;
    let lastFullReplyText = '';
    let activeFullSubtitleText = '';

    const modeFastBtn = document.getElementById('modeFastBtn');
    const modeDeepBtn = document.getElementById('modeDeepBtn');
    const orbBtn = document.getElementById('fridayOrbBtn');
    const micBtn = document.getElementById('fridayMicBtn');
    const stateLabel = document.getElementById('fridayStateLabel');
    const userEchoEl = document.getElementById('fridayUserEcho');
    const spokenWordsEl = document.getElementById('fridaySpokenWords');
    const whatsappPillEl = document.getElementById('fridayWhatsappPill');
    const whatsappPreviewEl = document.getElementById('fridayWhatsappPreview');
    const whatsappOpenLinkEl = document.getElementById('fridayWhatsappOpenLink');
    const whatsappCopyBtnEl = document.getElementById('fridayWhatsappCopyBtn');
    let latestWhatsappMessage = '';

    if (whatsappCopyBtnEl) {
        whatsappCopyBtnEl.addEventListener('click', () => {
            if (!latestWhatsappMessage) return;
            navigator.clipboard.writeText(latestWhatsappMessage).then(() => {
                whatsappCopyBtnEl.textContent = 'Copied!';
                setTimeout(() => {
                    whatsappCopyBtnEl.textContent = '📋 Copy Pitch';
                }, 1600);
            }).catch(() => {});
        });
    }

    function updateWhatsappActionPill(waAction) {
        if (!whatsappPillEl) return;
        if (waAction && waAction.whatsapp_url && waAction.whatsapp_message) {
            latestWhatsappMessage = waAction.whatsapp_message;
            const clientLabel = waAction.client_name || 'Client';
            whatsappPreviewEl.textContent = waAction.whatsapp_message;
            whatsappOpenLinkEl.href = waAction.whatsapp_url;
            whatsappOpenLinkEl.textContent = '💬 Open in WhatsApp (' + clientLabel + ')';
            whatsappPillEl.classList.add('visible');
        } else {
            latestWhatsappMessage = '';
            whatsappPillEl.classList.remove('visible');
        }
    }
    const askForm = document.getElementById('fridayAskForm');
    const promptInput = document.getElementById('fridayPromptInput');
    const canvas = document.getElementById('fridayVisualizer');
    const ctx = canvas.getContext('2d');

    // 1. Quick vs Deep Thinking Toggle
    [modeFastBtn, modeDeepBtn].forEach(btn => {
        btn.addEventListener('click', () => {
            currentMode = btn.dataset.mode;
            modeFastBtn.classList.toggle('active', currentMode === 'fast');
            modeDeepBtn.classList.toggle('active', currentMode === 'deep');
        });
    });

    function setHudState(state) {
        currentState = state;
        isThinking = (state === 'thinking');
        isSpeaking = (state === 'speaking');

        orbBtn.classList.remove('state-listening', 'state-thinking', 'state-speaking');
        micBtn.classList.toggle('active', liveConversationActive || state === 'listening');

        if (state === 'listening') {
            orbBtn.classList.add('state-listening');
            stateLabel.textContent = 'LISTENING...';
        } else if (state === 'thinking') {
            destroyRecognitionInstance();
            orbBtn.classList.add('state-thinking');
            stateLabel.textContent = 'THINKING...';
        } else if (state === 'speaking') {
            destroyRecognitionInstance();
            orbBtn.classList.add('state-speaking');
            stateLabel.textContent = 'F.R.I.D.A.Y. SPEAKING (SPEAK OR TAP TO INTERRUPT)';
        } else {
            stateLabel.textContent = liveConversationActive
                ? 'LISTENING...'
                : 'TAP ORB TO START LIVE CONVERSATION';
        }
    }

    // 2. Arc-Reactor Orb Visualizer driven by WebAudio AnalyserNode
    let phase = 0;
    function renderOrb() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const cx = canvas.width / 2;
        const cy = canvas.height / 2;

        let energy = 0;
        if (analyser && frequencyData && currentState === 'speaking') {
            analyser.getByteFrequencyData(frequencyData);
            let sum = 0;
            for (let i = 0; i < 40; i++) sum += frequencyData[i];
            energy = (sum / 40) / 255;
        } else if (currentState === 'speaking') {
            energy = 0.45 + Math.sin(phase * 2.3) * 0.2;
        } else if (currentState === 'listening') {
            const liveBoost = Math.min(0.65, currentMicRms * 8.5);
            energy = Math.max(0.22 + Math.sin(phase * 1.4) * 0.1, 0.18 + liveBoost);
        } else if (currentState === 'thinking') {
            energy = 0.30 + Math.cos(phase * 3.0) * 0.15;
        } else {
            energy = 0.06;
        }

        const baseRadius = 74;
        const amp = 4 + energy * 28;

        // Outer tactical ring
        ctx.beginPath();
        ctx.strokeStyle = currentState === 'listening'
            ? 'rgba(126, 242, 166, 0.55)'
            : 'rgba(201, 166, 107, 0.42)';
        ctx.lineWidth = 1.6;
        ctx.arc(cx, cy, 114, 0, Math.PI * 2);
        ctx.stroke();

        // Reactive voice waveform ring
        ctx.beginPath();
        for (let a = 0; a < Math.PI * 2; a += 0.06) {
            const wave = Math.sin(a * 7 + phase) * amp * Math.cos(a * 3 - phase * 0.8);
            const r = baseRadius + wave;
            const x = cx + Math.cos(a) * r;
            const y = cy + Math.sin(a) * r;
            if (a === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }
        ctx.closePath();
        ctx.strokeStyle = currentState === 'listening'
            ? '#7ef2a6'
            : (currentState === 'thinking' ? '#e6c891' : '#e79a91');
        ctx.lineWidth = currentState === 'speaking' ? 3.2 : 2.2;
        ctx.stroke();

        // Inner core glow
        const coreRadius = 38 + energy * 18;
        const grad = ctx.createRadialGradient(cx, cy, 4, cx, cy, coreRadius);
        grad.addColorStop(0, currentState === 'listening'
            ? 'rgba(126, 242, 166, 0.85)'
            : 'rgba(231, 154, 145, 0.90)');
        grad.addColorStop(1, 'rgba(20, 14, 13, 0.04)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(cx, cy, coreRadius, 0, Math.PI * 2);
        ctx.fill();

        phase += currentState === 'speaking' ? 0.18 : (currentState === 'thinking' ? 0.14 : 0.05);
        requestAnimationFrame(renderOrb);
    }
    renderOrb();

    // 3. Stop / Barge-In helper
    function interruptFridaySpeech() {
        isSpeaking = false;
        if (wordRevealTimer) {
            clearInterval(wordRevealTimer);
            wordRevealTimer = null;
        }
        if (activeFullSubtitleText) {
            spokenWordsEl.textContent = activeFullSubtitleText;
        } else if (lastFullReplyText) {
            spokenWordsEl.textContent = lastFullReplyText;
        }
        if (currentAudio) {
            try {
                currentAudio.onended = null;
                currentAudio.onerror = null;
                currentAudio.onplay = null;
                currentAudio.pause();
                currentAudio.currentTime = 0;
            } catch (e) {}
            currentAudio = null;
        }
    }

    // 4. Progressive Subtitle Reveal + Voice Playback (Gemini Aoede WAV)
    function revealSubtitleWords(fullText, audioDurationSec = 0) {
        if (wordRevealTimer) {
            clearInterval(wordRevealTimer);
            wordRevealTimer = null;
        }
        const cleanFull = (fullText || '').trim();
        activeFullSubtitleText = cleanFull;
        lastFullReplyText = cleanFull;
        const words = cleanFull.split(/\s+/).filter(Boolean);
        if (words.length <= 4) {
            spokenWordsEl.textContent = cleanFull;
            return;
        }

        const stepIntervalMs = (audioDurationSec && isFinite(audioDurationSec) && audioDurationSec > 0.5)
            ? Math.max(60, Math.min(115, Math.floor((audioDurationSec * 900) / words.length)))
            : 95;

        let idx = 3;
        spokenWordsEl.textContent = words.slice(0, idx).join(' ');
        wordRevealTimer = setInterval(() => {
            idx += 2;
            if (idx >= words.length) {
                spokenWordsEl.textContent = cleanFull;
                clearInterval(wordRevealTimer);
                wordRevealTimer = null;
            } else {
                spokenWordsEl.textContent = words.slice(0, idx).join(' ');
            }
        }, stepIntervalMs);
    }

    function onSpeechFinished() {
        isThinking = false;
        isSpeaking = false;
        if (wordRevealTimer) {
            clearInterval(wordRevealTimer);
            wordRevealTimer = null;
        }
        if (activeFullSubtitleText) {
            spokenWordsEl.textContent = activeFullSubtitleText;
        } else if (lastFullReplyText) {
            spokenWordsEl.textContent = lastFullReplyText;
        }
        if (liveConversationActive) {
            setHudState('listening');
            startListeningCycle(80);
        } else {
            setHudState('idle');
        }
    }

    function playFridayVoice(data) {
        interruptFridaySpeech();
        const displayReply = (data.spoken_text || data.reply || '').trim();
        activeFullSubtitleText = displayReply;
        lastFullReplyText = displayReply;

        if (data.audio_base64 && data.audio_mime) {
            try {
                const audio = new Audio('data:' + data.audio_mime + ';base64,' + data.audio_base64);
                currentAudio = audio;
                let hasStartedPlayback = false;

                const syncSubtitlesOnStart = () => {
                    if (hasStartedPlayback) return;
                    hasStartedPlayback = true;
                    setHudState('speaking');
                    const dur = (audio.duration && isFinite(audio.duration)) ? audio.duration : 0;
                    revealSubtitleWords(displayReply, dur);
                };

                audio.onplay = syncSubtitlesOnStart;
                audio.onloadedmetadata = () => {
                    if (hasStartedPlayback && audio.duration && isFinite(audio.duration)) {
                        revealSubtitleWords(displayReply, audio.duration);
                    }
                };

                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) {
                    if (!audioCtx) audioCtx = new AudioContextClass();
                    if (audioCtx.state === 'suspended') audioCtx.resume();

                    const source = audioCtx.createMediaElementSource(audio);
                    const warmLowShelf = audioCtx.createBiquadFilter();
                    warmLowShelf.type = 'lowshelf';
                    warmLowShelf.frequency.value = 220;
                    warmLowShelf.gain.value = 2.5;

                    const smoothHighShelf = audioCtx.createBiquadFilter();
                    smoothHighShelf.type = 'highshelf';
                    smoothHighShelf.frequency.value = 6500;
                    smoothHighShelf.gain.value = -1.5;

                    analyser = audioCtx.createAnalyser();
                    analyser.fftSize = 128;
                    frequencyData = new Uint8Array(analyser.frequencyBinCount);

                    source.connect(warmLowShelf);
                    warmLowShelf.connect(smoothHighShelf);
                    smoothHighShelf.connect(analyser);
                    analyser.connect(audioCtx.destination);
                }

                audio.onended = () => {
                    if (wordRevealTimer) {
                        clearInterval(wordRevealTimer);
                        wordRevealTimer = null;
                    }
                    if (activeFullSubtitleText) {
                        spokenWordsEl.textContent = activeFullSubtitleText;
                    }
                    currentAudio = null;
                    onSpeechFinished();
                };
                audio.onerror = () => {
                    if (wordRevealTimer) {
                        clearInterval(wordRevealTimer);
                        wordRevealTimer = null;
                    }
                    if (activeFullSubtitleText) {
                        spokenWordsEl.textContent = activeFullSubtitleText;
                    }
                    currentAudio = null;
                    setHudState('speaking');
                    onSpeechFinished();
                };

                audio.play().then(() => {
                    syncSubtitlesOnStart();
                }).catch(() => {
                    if (wordRevealTimer) {
                        clearInterval(wordRevealTimer);
                        wordRevealTimer = null;
                    }
                    if (activeFullSubtitleText) {
                        spokenWordsEl.textContent = activeFullSubtitleText;
                    }
                    currentAudio = null;
                    setHudState('speaking');
                    onSpeechFinished();
                });
                return;
            } catch (e) {
                currentAudio = null;
            }
        }

        setHudState('speaking');
        revealSubtitleWords(displayReply);
        onSpeechFinished();
    }

    // 5. Send Spoken or Typed Turn to F.R.I.D.A.Y. (Synchronized Neural Voice + Subtitle Playback)
    async function dispatchToFriday(promptText, audioBase64 = null, audioMime = null) {
        const cleanPrompt = (promptText || '').trim();
        if (!cleanPrompt && !audioBase64) {
            isSubmitting = false;
            return;
        }

        isSubmitting = true;
        interruptFridaySpeech();
        stopListeningInstance();

        if (cleanPrompt) {
            userEchoEl.textContent = 'You: "' + cleanPrompt + '"';
        } else {
            userEchoEl.textContent = 'You: [Processing Voice...]';
        }

        setHudState('thinking');
        activeFullSubtitleText = '';
        lastFullReplyText = '';
        spokenWordsEl.textContent = '⚡ Thinking...';
        updateWhatsappActionPill(null);

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
                    voice_profile: 'friday_tactical',
                    history: conversationHistory.slice(-6),
                    audio_base64: audioBase64,
                    audio_input_base64: audioBase64,
                    audio_mime: audioMime,
                    audio_input_mime: audioMime
                })
            });

            const data = await response.json();
            if (response.ok && data.reply) {
                const resolvedUserText = cleanPrompt || (data.user_transcript || '').trim();
                if (resolvedUserText) {
                    userEchoEl.textContent = 'You: "' + resolvedUserText + '"';
                    conversationHistory.push({ role: 'user', content: resolvedUserText });
                }
                conversationHistory.push({ role: 'assistant', content: data.reply });

                isSubmitting = false;
                updateWhatsappActionPill(data.whatsapp_action || null);
                playFridayVoice(data);
            } else {
                isSubmitting = false;
                isThinking = false;
                isSpeaking = false;
                spokenWordsEl.textContent = data.message || "Right then, Boss—I hit a brief comms glitch. Say that again for me?";
                if (liveConversationActive) {
                    setHudState('listening');
                    startListeningCycle(600);
                } else {
                    setHudState('idle');
                }
            }
        } catch (err) {
            isSubmitting = false;
            isThinking = false;
            isSpeaking = false;
            spokenWordsEl.textContent = "Boss, the network link dropped for a second. Give me that command again.";
            if (liveConversationActive) {
                setHudState('listening');
                startListeningCycle(600);
            } else {
                setHudState('idle');
            }
        }
    }

    // 6. Persistent Hardware Microphone VAD + MediaRecorder + SpeechRecognition Hybrid Loop
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;

    function stopVadLoop() {
        if (vadIntervalTimer) {
            clearInterval(vadIntervalTimer);
            vadIntervalTimer = null;
        }
        currentMicRms = 0;
    }

    function stopMediaRecorderQuietly() {
        if (mediaRecorder) {
            const oldRecorder = mediaRecorder;
            mediaRecorder = null;
            try {
                oldRecorder.ondataavailable = null;
                oldRecorder.onstop = null;
                if (oldRecorder.state !== 'inactive') {
                    oldRecorder.stop();
                }
            } catch (e) {}
        }
        mediaChunks = [];
    }

    function releasePersistentMicStream() {
        stopVadLoop();
        stopMediaRecorderQuietly();
        if (micStream) {
            try {
                micStream.getTracks().forEach(t => t.stop());
            } catch (e) {}
            micStream = null;
        }
    }

    async function ensurePersistentMicStream() {
        if (micStream && micStream.active) {
            if (micAudioCtx && micAudioCtx.state === 'suspended') {
                try { await micAudioCtx.resume(); } catch (e) {}
            }
            return true;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return false;
        }

        try {
            micStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });

            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                if (!micAudioCtx) micAudioCtx = new AudioContextClass();
                if (micAudioCtx.state === 'suspended') await micAudioCtx.resume();

                const micSource = micAudioCtx.createMediaStreamSource(micStream);
                micAnalyser = micAudioCtx.createAnalyser();
                micAnalyser.fftSize = 512;
                micTimeDomainData = new Uint8Array(micAnalyser.fftSize);
                micSource.connect(micAnalyser);
            }
            return true;
        } catch (err) {
            return false;
        }
    }

    function startFreshTurnRecorder() {
        stopMediaRecorderQuietly();
        if (!micStream || !micStream.active || typeof MediaRecorder === 'undefined') return;

        try {
            const preferredTypes = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/mp4'
            ];
            let chosenType = '';
            if (typeof MediaRecorder.isTypeSupported === 'function') {
                chosenType = preferredTypes.find(t => MediaRecorder.isTypeSupported(t)) || '';
            }
            mediaMimeType = chosenType ? chosenType.split(';')[0] : 'audio/webm';
            mediaChunks = [];
            const recorderOptions = chosenType
                ? { mimeType: chosenType, audioBitsPerSecond: 16000 }
                : { audioBitsPerSecond: 16000 };
            mediaRecorder = new MediaRecorder(micStream, recorderOptions);
            recorderStartedAt = performance.now();

            mediaRecorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    mediaChunks.push(e.data);
                }
            };
            mediaRecorder.start(200);
        } catch (e) {
            mediaRecorder = null;
        }
    }

    function commitTurnVoiceInput() {
        if (isThinking || isSpeaking || isSubmitting) return;
        isSubmitting = true;

        if (silenceTimer) {
            clearTimeout(silenceTimer);
            silenceTimer = null;
        }
        stopVadLoop();
        destroyRecognitionInstance();

        const spokenText = (currentTranscript || pendingTranscript || '').trim();
        currentTranscript = '';
        pendingTranscript = '';

        orbBtn.classList.add('state-endpoint-flash');
        stateLabel.textContent = '⚡ THINKING...';
        setTimeout(() => orbBtn.classList.remove('state-endpoint-flash'), 220);

        // Path A: SpeechRecognition captured text cleanly -> DO NOT upload audio_input_base64! Send plain text only.
        if (spokenText.length >= 2) {
            stopMediaRecorderQuietly();
            dispatchToFriday(spokenText, null, null);
            return;
        }

        // Path B: SpeechRecognition was silent/deaf on Turn 2+ while hardware VAD confirmed speech -> send compact 16kbps audio blob
        if (speechDetectedInTurn && mediaRecorder && mediaRecorder.state !== 'inactive') {
            const activeRecorder = mediaRecorder;
            mediaRecorder = null;
            activeRecorder.onstop = () => {
                const blob = new Blob(mediaChunks, { type: mediaMimeType || 'audio/webm' });
                mediaChunks = [];
                if (blob.size < 400) {
                    isSubmitting = false;
                    if (liveConversationActive) startListeningCycle(60);
                    return;
                }
                const reader = new FileReader();
                reader.onloadend = () => {
                    const base64 = ((reader.result || '').toString().split(',')[1] || '').trim();
                    if (base64) {
                        dispatchToFriday('', base64, mediaMimeType || 'audio/webm');
                    } else {
                        isSubmitting = false;
                        if (liveConversationActive) startListeningCycle(60);
                    }
                };
                reader.readAsDataURL(blob);
            };
            try {
                activeRecorder.stop();
            } catch (e) {
                isSubmitting = false;
                if (liveConversationActive) startListeningCycle(60);
            }
            return;
        }

        stopMediaRecorderQuietly();
        isSubmitting = false;
        if (liveConversationActive) startListeningCycle(60);
    }

    function startHardwareVadLoop() {
        stopVadLoop();
        if (!micAnalyser || !micTimeDomainData) return;

        vadIntervalTimer = setInterval(() => {
            if (!liveConversationActive || isThinking || isSpeaking || isSubmitting) return;

            micAnalyser.getByteTimeDomainData(micTimeDomainData);
            let sumSquares = 0;
            for (let i = 0; i < micTimeDomainData.length; i++) {
                const norm = (micTimeDomainData[i] - 128) / 128;
                sumSquares += norm * norm;
            }
            const rms = Math.sqrt(sumSquares / micTimeDomainData.length);
            currentMicRms = rms;

            const VOICE_RMS_THRESHOLD = 0.018;

            if (rms > VOICE_RMS_THRESHOLD) {
                speechActiveMs += 50;
                silenceAfterSpeechMs = 0;
                lastSpeechActivityAt = performance.now();
                if (speechActiveMs >= 150) {
                    speechDetectedInTurn = true;
                    if (!currentTranscript && !pendingTranscript) {
                        userEchoEl.textContent = 'You: [Listening...]';
                    }
                    scheduleUnifiedSilenceCommit();
                }
            } else if (speechDetectedInTurn) {
                const activeText = (currentTranscript || pendingTranscript || '').trim();
                const requiredSilenceMs = getRequiredSilenceMs(activeText);
                const silentForMs = lastSpeechActivityAt > 0
                    ? (performance.now() - lastSpeechActivityAt)
                    : (silenceAfterSpeechMs + 50);
                silenceAfterSpeechMs = silentForMs;

                if (silentForMs >= requiredSilenceMs) {
                    commitTurnVoiceInput();
                }
            } else if (!speechDetectedInTurn && recorderStartedAt > 0 && (performance.now() - recorderStartedAt) > 3500) {
                // Keep pre-speech fallback audio buffer trimmed to < 3.5s with a fresh WebM header so dead air is never uploaded
                startFreshTurnRecorder();
            }
        }, 50);
    }

    function destroyRecognitionInstance() {
        if (listeningRestartTimer) {
            clearTimeout(listeningRestartTimer);
            listeningRestartTimer = null;
        }
        if (silenceTimer) {
            clearTimeout(silenceTimer);
            silenceTimer = null;
        }
        if (recognition) {
            const oldRec = recognition;
            recognition = null;
            try {
                oldRec.onstart = null;
                oldRec.onresult = null;
                oldRec.onerror = null;
                oldRec.onend = null;
                oldRec.abort();
            } catch (e) {}
        }
    }

    function createFreshRecognitionInstance() {
        if (!SpeechRec) return null;
        const rec = new SpeechRec();
        rec.lang = 'en-US';
        rec.continuous = true;
        rec.interimResults = true;
        rec.maxAlternatives = 1;

        rec.onstart = () => {
            if (!isSpeaking && !isThinking && !isSubmitting) {
                setHudState('listening');
            }
        };

        rec.onresult = (event) => {
            let interim = '';
            let finalChunk = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalChunk += transcript + ' ';
                } else {
                    interim += transcript;
                }
            }

            // Barge-in: if Boss speaks while F.R.I.D.A.Y. is talking, stop her immediately
            if ((interim.trim().length > 2 || finalChunk.trim().length > 0) && isSpeaking) {
                interruptFridaySpeech();
                setHudState('listening');
            }

            if (finalChunk.trim()) {
                pendingTranscript = (pendingTranscript + ' ' + finalChunk).trim();
            }

            const combined = (pendingTranscript + ' ' + interim).trim();
            if (combined) {
                currentTranscript = combined;
                speechDetectedInTurn = true;
                silenceAfterSpeechMs = 0;
                lastSpeechActivityAt = performance.now();
                userEchoEl.textContent = 'You: "' + combined + '..."';
                scheduleUnifiedSilenceCommit();
            }
        };

        rec.onerror = () => {};

        rec.onend = () => {
            if (liveConversationActive && !isThinking && !isSpeaking && !isSubmitting) {
                try {
                    const nextRec = createFreshRecognitionInstance();
                    if (nextRec) {
                        recognition = nextRec;
                        recognition.start();
                    }
                } catch (e) {}
            }
        };

        return rec;
    }

    function startListeningCycle(delayMs = 0) {
        isThinking = false;
        isSpeaking = false;
        isSubmitting = false;
        speechDetectedInTurn = false;
        speechActiveMs = 0;
        silenceAfterSpeechMs = 0;
        lastSpeechActivityAt = 0;
        pendingTranscript = '';
        currentTranscript = '';
        destroyRecognitionInstance();
        stopVadLoop();

        if (!liveConversationActive) {
            setHudState('idle');
            return;
        }

        setHudState('listening');

        const launch = async () => {
            if (!liveConversationActive || isThinking || isSpeaking || isSubmitting) return;

            const hasMic = await ensurePersistentMicStream();
            if (!liveConversationActive || isThinking || isSpeaking || isSubmitting) return;

            if (hasMic) {
                startFreshTurnRecorder();
                startHardwareVadLoop();
            }

            if (SpeechRec) {
                destroyRecognitionInstance();
                recognition = createFreshRecognitionInstance();
                if (recognition) {
                    try {
                        recognition.start();
                    } catch (e) {}
                }
            }
        };

        if (delayMs > 0) {
            listeningRestartTimer = setTimeout(launch, delayMs);
        } else {
            launch();
        }
    }

    function startContinuousListening() {
        startListeningCycle(0);
    }

    function stopListeningInstance() {
        destroyRecognitionInstance();
        stopVadLoop();
        stopMediaRecorderQuietly();
    }

    function toggleLiveConversation() {
        // If F.R.I.D.A.Y. is currently speaking, tapping the orb barges in and switches straight to listening
        if (isSpeaking || currentState === 'speaking') {
            interruptFridaySpeech();
            liveConversationActive = true;
            startListeningCycle(80);
            return;
        }

        // If user is currently speaking/listening and has active speech, tapping the orb commits the turn immediately
        if (liveConversationActive && currentState === 'listening' && (speechDetectedInTurn || (currentTranscript || pendingTranscript).trim().length >= 2)) {
            commitTurnVoiceInput();
            return;
        }

        liveConversationActive = !liveConversationActive;
        if (liveConversationActive) {
            startListeningCycle(0);
        } else {
            interruptFridaySpeech();
            stopListeningInstance();
            releasePersistentMicStream();
            setHudState('idle');
        }
    }

    orbBtn.addEventListener('click', toggleLiveConversation);
    micBtn.addEventListener('click', toggleLiveConversation);

    askForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = promptInput.value.trim();
        if (!text) {
            if (liveConversationActive && currentState === 'listening' && (speechDetectedInTurn || (currentTranscript || pendingTranscript).trim().length >= 2)) {
                commitTurnVoiceInput();
            }
            return;
        }
        promptInput.value = '';
        dispatchToFriday(text);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && document.activeElement !== promptInput) {
            if (liveConversationActive && currentState === 'listening' && (speechDetectedInTurn || (currentTranscript || pendingTranscript).trim().length >= 2)) {
                e.preventDefault();
                commitTurnVoiceInput();
            }
        }
    });
})();
</script>
@endsection
