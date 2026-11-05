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
    let conversationHistory = [];

    let recognition = null;
    let mediaRecorder = null;
    let mediaChunks = [];
    let silenceTimer = null;
    let pendingTranscript = '';

    let currentAudio = null;
    let audioCtx = null;
    let analyser = null;
    let frequencyData = null;
    let wordRevealTimer = null;

    const modeFastBtn = document.getElementById('modeFastBtn');
    const modeDeepBtn = document.getElementById('modeDeepBtn');
    const orbBtn = document.getElementById('fridayOrbBtn');
    const micBtn = document.getElementById('fridayMicBtn');
    const stateLabel = document.getElementById('fridayStateLabel');
    const userEchoEl = document.getElementById('fridayUserEcho');
    const spokenWordsEl = document.getElementById('fridaySpokenWords');
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
        orbBtn.classList.remove('state-listening', 'state-thinking', 'state-speaking');
        micBtn.classList.toggle('active', liveConversationActive || state === 'listening');

        if (state === 'listening') {
            orbBtn.classList.add('state-listening');
            stateLabel.textContent = 'LISTENING...';
        } else if (state === 'thinking') {
            orbBtn.classList.add('state-thinking');
            stateLabel.textContent = 'THINKING...';
        } else if (state === 'speaking') {
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
            energy = 0.22 + Math.sin(phase * 1.4) * 0.1;
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
        if (wordRevealTimer) {
            clearInterval(wordRevealTimer);
            wordRevealTimer = null;
        }
        if (currentAudio) {
            try {
                currentAudio.pause();
                currentAudio.currentTime = 0;
            } catch (e) {}
            currentAudio = null;
        }
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    }

    // 4. Progressive Subtitle Reveal + Voice Playback (Gemini WAV + Irish/British WebSpeech fallback)
    function revealSubtitleWords(fullText) {
        if (wordRevealTimer) {
            clearInterval(wordRevealTimer);
            wordRevealTimer = null;
        }
        const words = (fullText || '').trim().split(/\s+/).filter(Boolean);
        if (words.length <= 4) {
            spokenWordsEl.textContent = fullText;
            return;
        }
        let idx = 3;
        spokenWordsEl.textContent = words.slice(0, idx).join(' ');
        wordRevealTimer = setInterval(() => {
            idx += 2;
            if (idx >= words.length) {
                spokenWordsEl.textContent = fullText;
                clearInterval(wordRevealTimer);
                wordRevealTimer = null;
            } else {
                spokenWordsEl.textContent = words.slice(0, idx).join(' ');
            }
        }, 140);
    }

    function onSpeechFinished() {
        if (wordRevealTimer) {
            clearInterval(wordRevealTimer);
            wordRevealTimer = null;
        }
        if (liveConversationActive) {
            startContinuousListening();
        } else {
            setHudState('idle');
        }
    }

    function playFridayVoice(data) {
        interruptFridaySpeech();
        const displayReply = data.spoken_text || data.reply || '';
        setHudState('speaking');
        revealSubtitleWords(displayReply);

        if (data.audio_base64 && data.audio_mime) {
            try {
                const audio = new Audio('data:' + data.audio_mime + ';base64,' + data.audio_base64);
                currentAudio = audio;

                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) {
                    if (!audioCtx) audioCtx = new AudioContextClass();
                    if (audioCtx.state === 'suspended') audioCtx.resume();

                    const source = audioCtx.createMediaElementSource(audio);
                    const eqFilter = audioCtx.createBiquadFilter();
                    eqFilter.type = 'peaking';
                    eqFilter.frequency.value = 2500;
                    eqFilter.Q.value = 1.2;
                    eqFilter.gain.value = 2.5;

                    analyser = audioCtx.createAnalyser();
                    analyser.fftSize = 128;
                    frequencyData = new Uint8Array(analyser.frequencyBinCount);

                    source.connect(eqFilter);
                    eqFilter.connect(analyser);
                    analyser.connect(audioCtx.destination);
                }

                audio.onended = () => {
                    currentAudio = null;
                    onSpeechFinished();
                };
                audio.onerror = () => {
                    currentAudio = null;
                    speakWithIrishBritishFallback(displayReply);
                };

                audio.play().catch(() => {
                    currentAudio = null;
                    speakWithIrishBritishFallback(displayReply);
                });
                return;
            } catch (e) {
                currentAudio = null;
            }
        }

        speakWithIrishBritishFallback(displayReply);
    }

    function pickIrishOrBritishFemaleVoice() {
        if (!('speechSynthesis' in window)) return null;
        const voices = window.speechSynthesis.getVoices() || [];
        if (!voices.length) return null;

        // 1. Prefer Irish English (en-IE: Moira, Emily, Orla)
        const irish = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('en-ie'));
        if (irish) return irish;

        // 2. Prefer UK English Female voices (Libby, Sonia, Hazel, Martha, Google UK English Female, Serena)
        const ukFemaleNames = ['libby', 'sonia', 'hazel', 'martha', 'female', 'serena', 'kate', 'stephanie'];
        const ukFemale = voices.find(v =>
            v.lang && v.lang.toLowerCase().startsWith('en-gb') &&
            ukFemaleNames.some(n => v.name.toLowerCase().includes(n))
        );
        if (ukFemale) return ukFemale;

        // 3. Any en-GB voice
        const ukAny = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('en-gb'));
        if (ukAny) return ukAny;

        return voices[0];
    }

    function speakWithIrishBritishFallback(text) {
        if (!('speechSynthesis' in window) || !text) {
            onSpeechFinished();
            return;
        }
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        const preferredVoice = pickIrishOrBritishFemaleVoice();
        if (preferredVoice) {
            utter.voice = preferredVoice;
            utter.lang = preferredVoice.lang;
        } else {
            utter.lang = 'en-IE';
        }
        utter.rate = 1.08;
        utter.pitch = 1.05;

        utter.onend = () => onSpeechFinished();
        utter.onerror = () => onSpeechFinished();
        window.speechSynthesis.speak(utter);
    }

    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = () => pickIrishOrBritishFemaleVoice();
    }

    // 5. Send Spoken or Typed Turn to F.R.I.D.A.Y. (Decoupled Text + Progressive TTS)
    async function requestAndPlayFridayTts(spokenText, initialData = {}) {
        if (initialData.audio_base64 && initialData.audio_mime) {
            playFridayVoice(initialData);
            return;
        }

        try {
            const ttsResp = await fetch(ttsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    spoken_text: spokenText,
                    voice_profile: 'friday_tactical'
                })
            });

            const ttsData = await ttsResp.json();
            if (ttsResp.ok && ttsData.audio_base64 && ttsData.audio_mime) {
                playFridayVoice({
                    spoken_text: spokenText,
                    reply: initialData.reply || spokenText,
                    audio_base64: ttsData.audio_base64,
                    audio_mime: ttsData.audio_mime,
                    voice_meta: ttsData.voice_meta || initialData.voice_meta
                });
                return;
            }
        } catch (e) {
            // Fall through to WebSpeech Irish/British fallback
        }

        speakWithIrishBritishFallback(spokenText);
    }

    async function dispatchToFriday(promptText, audioBase64 = null, audioMime = null) {
        const cleanPrompt = (promptText || '').trim();
        if (!cleanPrompt && !audioBase64) return;

        interruptFridaySpeech();
        stopListeningInstance();

        if (cleanPrompt) {
            userEchoEl.textContent = 'You: "' + cleanPrompt + '"';
        } else {
            userEchoEl.textContent = 'You: [Voice Command]';
        }

        setHudState('thinking');
        spokenWordsEl.textContent = '⚡ Thinking...';

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
                    skip_tts: true,
                    history: conversationHistory.slice(-6),
                    audio_base64: audioBase64,
                    audio_mime: audioMime
                })
            });

            const data = await response.json();
            if (response.ok && data.reply) {
                if (cleanPrompt) {
                    conversationHistory.push({ role: 'user', content: cleanPrompt });
                }
                conversationHistory.push({ role: 'assistant', content: data.reply });

                const displayReply = data.spoken_text || data.reply;
                setHudState('speaking');
                revealSubtitleWords(displayReply);
                requestAndPlayFridayTts(displayReply, data);
            } else {
                spokenWordsEl.textContent = data.message || "Right then, Boss—I hit a brief comms glitch. Say that again for me?";
                onSpeechFinished();
            }
        } catch (err) {
            spokenWordsEl.textContent = "Boss, the network link dropped for a second. Give me that command again.";
            onSpeechFinished();
        }
    }

    // 6. Continuous Hands-Free SpeechRecognition + Barge-In
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;

    function initSpeechRecognition() {
        if (!SpeechRec || recognition) return;
        recognition = new SpeechRec();
        recognition.lang = 'en-US';
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            if (currentState !== 'speaking' && currentState !== 'thinking') {
                setHudState('listening');
            }
        };

        recognition.onresult = (event) => {
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
            if ((interim.trim().length > 2 || finalChunk.trim().length > 0) && currentState === 'speaking') {
                interruptFridaySpeech();
                setHudState('listening');
            }

            const combined = (pendingTranscript + ' ' + finalChunk + ' ' + interim).trim();
            if (combined) {
                userEchoEl.textContent = 'You: "' + combined + '..."';
            }

            if (finalChunk.trim()) {
                pendingTranscript = (pendingTranscript + ' ' + finalChunk).trim();
            }

            if (silenceTimer) clearTimeout(silenceTimer);
            silenceTimer = setTimeout(() => {
                const toSend = (pendingTranscript || combined).trim();
                pendingTranscript = '';
                if (toSend.length >= 2 && currentState !== 'thinking') {
                    orbBtn.classList.add('state-endpoint-flash');
                    stateLabel.textContent = '⚡ THINKING...';
                    setTimeout(() => orbBtn.classList.remove('state-endpoint-flash'), 260);
                    dispatchToFriday(toSend);
                }
            }, 700);
        };

        recognition.onerror = () => {};

        recognition.onend = () => {
            if (liveConversationActive && currentState === 'listening') {
                try { recognition.start(); } catch (e) {}
            }
        };
    }

    function startContinuousListening() {
        if (SpeechRec) {
            initSpeechRecognition();
            pendingTranscript = '';
            setHudState('listening');
            try {
                recognition.start();
            } catch (e) {}
            return;
        }

        // MediaRecorder fallback if browser lacks Web SpeechRecognition
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                mediaChunks = [];
                mediaRecorder = new MediaRecorder(stream);
                setHudState('listening');
                mediaRecorder.ondataavailable = e => {
                    if (e.data.size > 0) mediaChunks.push(e.data);
                };
                mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    const blob = new Blob(mediaChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.onloadend = () => {
                        const base64 = (reader.result || '').toString().split(',')[1];
                        if (base64) dispatchToFriday('', base64, 'audio/webm');
                    };
                    reader.readAsDataURL(blob);
                };
                mediaRecorder.start();
            }).catch(() => {
                liveConversationActive = false;
                setHudState('idle');
                promptInput.focus();
            });
        }
    }

    function stopListeningInstance() {
        if (silenceTimer) {
            clearTimeout(silenceTimer);
            silenceTimer = null;
        }
        if (recognition) {
            try { recognition.stop(); } catch (e) {}
        }
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            try { mediaRecorder.stop(); } catch (e) {}
        }
    }

    function toggleLiveConversation() {
        // If F.R.I.D.A.Y. is currently speaking, tapping the orb barges in and switches straight to listening
        if (currentState === 'speaking') {
            interruptFridaySpeech();
            liveConversationActive = true;
            startContinuousListening();
            return;
        }

        liveConversationActive = !liveConversationActive;
        if (liveConversationActive) {
            startContinuousListening();
        } else {
            interruptFridaySpeech();
            stopListeningInstance();
            setHudState('idle');
        }
    }

    orbBtn.addEventListener('click', toggleLiveConversation);
    micBtn.addEventListener('click', toggleLiveConversation);

    askForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = promptInput.value.trim();
        if (!text) return;
        promptInput.value = '';
        dispatchToFriday(text);
    });
})();
</script>
@endsection
