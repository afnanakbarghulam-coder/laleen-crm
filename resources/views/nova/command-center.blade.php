<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/laleen logo1.PNG') }}">
    <title>Nova Command Center</title>

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        :root {
            --void: #0a0a0c;
            --text-primary: #ffffff;
            --text-muted: #8e8ea0;
            --border-subtle: rgba(255, 255, 255, 0.08);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            height: 100%;
            background: var(--void);
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text-primary);
        }

        #hud-canvas {
            position: fixed;
            inset: 0;
            display: block;
            z-index: 0;
        }

        .exit-link {
            position: fixed;
            top: 22px;
            left: 26px;
            z-index: 20;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 16px;
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            transition: color .15s ease, border-color .15s ease, background .15s ease;
        }

        .exit-link:hover { color: var(--text-primary); border-color: rgba(255, 255, 255, 0.16); background: rgba(255, 255, 255, 0.06); }
        .exit-link:focus-visible { outline: 2px solid rgba(255, 255, 255, 0.4); outline-offset: 2px; }

        /* The one remaining piece of UI: a quiet, centered readout of
           whatever Nova is doing right now - nothing else sits on screen. */
        .hud-status {
            position: fixed;
            left: 50%;
            bottom: 56px;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.14em;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .hud-status .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--text-muted);
            transition: background .2s ease;
        }

        .hud-status.listening .dot,
        .hud-status.processing .dot,
        .hud-status.speaking .dot {
            background: var(--text-primary);
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
            animation: hud-pulse 1.1s ease-in-out infinite;
        }

        .hud-status.processing .dot { animation-duration: .7s; }

        #hud-status-text {
            transition: opacity .2s ease;
        }

        @keyframes hud-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .35; transform: scale(1.4); }
        }

        @media (prefers-reduced-motion: reduce) {
            .hud-status .dot { animation: none; }
        }
    </style>
</head>

<body>
    <canvas id="hud-canvas" aria-hidden="true"></canvas>

    <a href="{{ route('dashboard') }}" class="exit-link">
        <i class="bx bx-chevron-left"></i> Dashboard
    </a>

    <div class="hud-status" id="hud-status" role="status" aria-live="polite">
        <span class="dot" aria-hidden="true"></span>
        <span id="hud-status-text">Initializing...</span>
    </div>

    <script>
    (function () {
        /* ================= canvas: rotating 3D spiral galaxy + starfield ================= */
        const canvas = document.getElementById('hud-canvas');
        const ctx = canvas.getContext('2d');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let W, H, CX, CY;

        function resize() {
            W = canvas.width = window.innerWidth;
            H = canvas.height = window.innerHeight;
            CX = W / 2;
            CY = H / 2;
        }
        window.addEventListener('resize', resize);
        resize();

        // Ambient background starfield - three depth layers drifting at
        // different constant speeds (the classic parallax cue: distant
        // things crawl, near things glide) plus their own gentle twinkle.
        // Coordinates are normalized (0..1) so layers survive a resize
        // untouched, and drift wraps with a plain modulo - no edge cases.
        const DRIFT_ANGLE = -0.32; // radians - a slow, consistent diagonal glide
        const DRIFT_DX = Math.cos(DRIFT_ANGLE), DRIFT_DY = Math.sin(DRIFT_ANGLE);
        const STAR_LAYERS = [
            { count: 150, sizeMin: 0.25, sizeMax: 0.6, alpha: 0.32, parallax: 0.18, drift: 0.0035 },
            { count: 85, sizeMin: 0.5, sizeMax: 1.0, alpha: 0.48, parallax: 0.38, drift: 0.009 },
            { count: 35, sizeMin: 0.9, sizeMax: 1.7, alpha: 0.66, parallax: 0.7, drift: 0.02 },
        ];
        const starLayers = STAR_LAYERS.map((layer) => ({
            ...layer,
            stars: Array.from({ length: layer.count }, () => ({
                x: Math.random(),
                y: Math.random(),
                r: layer.sizeMin + Math.random() * (layer.sizeMax - layer.sizeMin),
                phase: Math.random() * Math.PI * 2,
                speed: 0.4 + Math.random() * 0.9,
            })),
        }));

        // Galaxy: several spiral arms of points plus a bright core cluster,
        // stored in 3D (x, y, z) and re-projected every frame as it spins.
        // Each point also carries its distance from the core (0 = center,
        // 1 = outer edge) so the core can "shimmer" harder than the arms -
        // a cheap stand-in for gravitational lensing that never needs an
        // actual light-bending pass.
        const ARMS = 4, PER_ARM = 220, CORE_COUNT = 90, MAX_RADIUS = 480;
        const galaxy = [];
        for (let arm = 0; arm < ARMS; arm++) {
            const armAngle = (arm / ARMS) * Math.PI * 2;
            for (let i = 0; i < PER_ARM; i++) {
                const t = i / PER_ARM;
                const angle = armAngle + t * Math.PI * 2.5 + (Math.random() - 0.5) * 0.3;
                const radius = 34 + t * (MAX_RADIUS - 34) + (Math.random() - 0.5) * 30;
                const roll = Math.random();
                galaxy.push({
                    x: Math.cos(angle) * radius,
                    z: Math.sin(angle) * radius,
                    y: (Math.random() - 0.5) * 16 * (1 - t * 0.6),
                    size: 1 + Math.random() * 2.1,
                    b: Math.pow(1 - t, 1.4) * 0.85 + 0.15 + Math.random() * 0.15,
                    hue: roll < 0.12 ? 'gold' : (roll < 0.55 ? 'cyan' : 'white'),
                    coreProximity: 1 - Math.min(1, radius / MAX_RADIUS),
                    twinklePhase: Math.random() * Math.PI * 2,
                    twinkleSpeed: 0.5 + Math.random() * 1.1,
                });
            }
        }
        for (let i = 0; i < CORE_COUNT; i++) {
            const angle = Math.random() * Math.PI * 2;
            const radius = Math.random() * 42;
            galaxy.push({
                x: Math.cos(angle) * radius,
                z: Math.sin(angle) * radius,
                y: (Math.random() - 0.5) * 8,
                size: 1.4 + Math.random() * 2.2,
                b: 1,
                hue: 'white',
                coreProximity: 1 - Math.min(1, radius / MAX_RADIUS),
                twinklePhase: Math.random() * Math.PI * 2,
                twinkleSpeed: 0.5 + Math.random() * 1.1,
            });
        }

        let rotation = 0;
        const TILT = 1.02;
        const cosTilt = Math.cos(TILT), sinTilt = Math.sin(TILT);
        const DEPTH_RANGE = MAX_RADIUS * Math.sin(TILT);

        // Desaturated, near-monochrome star colors - a touch of warm/cool
        // variation for realism and depth, without neon saturation.
        function colorFor(hue, alpha) {
            if (hue === 'gold') return `rgba(255,244,230,${alpha})`;
            if (hue === 'cyan') return `rgba(212,222,235,${alpha})`;
            return `rgba(255,255,255,${alpha})`;
        }

        // Dynamic camera drift: a slow, organic viewport nudge that eases
        // toward the pointer (when it moves) blended with a gentle idle
        // sine wander (when it doesn't - this page is voice-first, the
        // pointer may never move at all). Exponential easing keeps the
        // motion frame-rate independent rather than tied to a fixed
        // per-frame step. Applied per-layer/subject below so nearer things
        // shift further than distant ones - camera drift doubling as the
        // parallax mechanism.
        let pointerX = 0, pointerY = 0;
        window.addEventListener('mousemove', (e) => {
            pointerX = (e.clientX / W) * 2 - 1;
            pointerY = (e.clientY / H) * 2 - 1;
        });
        const camera = { x: 0, y: 0 };
        function easeTo(current, target, dt, halfLife) {
            const factor = 1 - Math.pow(2, -dt / halfLife);
            return current + (target - current) * factor;
        }

        let lastTime = 0;
        function drawFrame(now) {
            const dt = lastTime ? Math.min(0.05, (now - lastTime) / 1000) : 0;
            lastTime = now;

            const idleX = Math.sin(now * 0.00007) * 0.5;
            const idleY = Math.cos(now * 0.00005) * 0.5;
            camera.x = easeTo(camera.x, pointerX * 0.6 + idleX, dt, 0.6);
            camera.y = easeTo(camera.y, pointerY * 0.6 + idleY, dt, 0.6);

            const fit = Math.min(W, H) / 900;
            const camPxX = camera.x * 36 * fit;
            const camPxY = camera.y * 24 * fit;

            ctx.fillStyle = '#0a0a0c';
            ctx.fillRect(0, 0, W, H);

            // Layer 1-3: parallax starfield, back to front.
            for (const layer of starLayers) {
                const px = camPxX * layer.parallax, py = camPxY * layer.parallax;
                for (const s of layer.stars) {
                    if (!reduceMotion) {
                        s.x = ((s.x + DRIFT_DX * layer.drift * dt) % 1 + 1) % 1;
                        s.y = ((s.y + DRIFT_DY * layer.drift * dt) % 1 + 1) % 1;
                    }
                    const twinkle = 0.7 + Math.sin(now * 0.001 * s.speed + s.phase) * 0.3;
                    const alpha = Math.max(0.05, layer.alpha * twinkle);
                    ctx.beginPath();
                    ctx.fillStyle = `rgba(255,255,255,${alpha})`;
                    ctx.arc(s.x * W + px, s.y * H + py, s.r, 0, Math.PI * 2);
                    ctx.fill();
                }
            }

            // Volumetric core: several additive glow passes read as soft
            // bloom rather than one flat radial gradient. Cheap - a
            // handful of gradient fills, no offscreen buffers.
            const glowCX = CX + camPxX * 0.4, glowCY = CY + camPxY * 0.4;
            ctx.save();
            ctx.globalCompositeOperation = 'lighter';
            const bloomLayers = [
                { r: 70 * fit, a: 0.5 },
                { r: 150 * fit, a: 0.24 },
                { r: 280 * fit, a: 0.12 },
            ];
            for (const b of bloomLayers) {
                const glow = ctx.createRadialGradient(glowCX, glowCY, 0, glowCX, glowCY, b.r);
                glow.addColorStop(0, `rgba(255,255,255,${b.a})`);
                glow.addColorStop(1, 'rgba(255,255,255,0)');
                ctx.fillStyle = glow;
                ctx.fillRect(glowCX - b.r, glowCY - b.r, b.r * 2, b.r * 2);
            }
            ctx.restore();

            // Linear projection (position scales only by `fit`, never by
            // depth) keeps the spiral's screen radius predictable and
            // bounded - depth (z2) is used purely for z-sorting and a mild
            // size/brightness falloff, which is enough for the tilt +
            // rotation to read clearly as 3D without any risk of the
            // near-side points blowing up in size.
            const cosR = Math.cos(rotation), sinR = Math.sin(rotation);
            const projected = galaxy.map((st) => {
                const x = st.x * cosR - st.z * sinR;
                const z = st.x * sinR + st.z * cosR;
                const y2 = st.y * cosTilt - z * sinTilt;
                const z2 = st.y * sinTilt + z * cosTilt;
                const depth = 1 - Math.min(1, Math.max(0, (z2 + DEPTH_RANGE) / (2 * DEPTH_RANGE))) * 0.45;
                // Gravitational-lensing shimmer: amplitude grows the closer
                // a star sits to the core, so the center visibly shimmers
                // while the outer arms stay nearly steady.
                const shimmerAmp = 0.06 + st.coreProximity * 0.35;
                const shimmer = 1 + Math.sin(now * 0.001 * st.twinkleSpeed + st.twinklePhase) * shimmerAmp;
                return {
                    sx: CX + x * fit + camPxX * 0.4,
                    sy: CY + y2 * fit + camPxY * 0.4,
                    size: st.size * fit * depth,
                    b: st.b * (0.75 + depth * 0.25) * shimmer,
                    hue: st.hue,
                    z2,
                };
            });
            projected.sort((a, b) => b.z2 - a.z2);
            for (const p of projected) {
                if (p.size <= 0) continue;
                ctx.beginPath();
                ctx.fillStyle = colorFor(p.hue, Math.min(1, p.b));
                ctx.arc(p.sx, p.sy, Math.max(0.3, p.size), 0, Math.PI * 2);
                ctx.fill();
            }

            if (!reduceMotion) rotation += 0.0016;
            requestAnimationFrame(drawFrame);
        }
        requestAnimationFrame(drawFrame);

        /* ================= status readout ================= */
        const statusEl = document.getElementById('hud-status');
        const statusText = document.getElementById('hud-status-text');

        function setStatus(mode, label) {
            statusEl.classList.remove('listening', 'processing', 'speaking');
            if (mode) statusEl.classList.add(mode);

            statusText.style.opacity = '0';
            setTimeout(() => {
                statusText.textContent = label;
                statusText.style.opacity = '1';
            }, 150);
        }

        /* ================= hands-free conversation loop =================
           No buttons, no transcript, no typing - Nova listens, thinks,
           answers out loud, then immediately listens again. `busy` is the
           single guard against the recognizer restarting mid-request or
           mid-speech; `listening` guards against starting it twice. */
        let busy = false;
        let listening = false;

        function ask(message) {
            message = (message || '').trim();
            if (!message) {
                startListening();
                return;
            }

            busy = true;
            setStatus('processing', 'Processing...');

            fetch("{{ route('nova.ask') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message })
                })
                .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    const reply = ok ? (data.reply || "I didn't get a usable answer that time.") :
                        (data.message || "I couldn't process that question.");
                    speakAsNova(reply);
                })
                .catch(() => {
                    speakAsNova('Connection to the command relay was lost. Please try again.');
                });
        }

        /* ---------------- voice input ---------------- */
        const SpeechRecognitionImpl = window.SpeechRecognition || window.webkitSpeechRecognition;
        let recognition = null;

        if (SpeechRecognitionImpl) {
            recognition = new SpeechRecognitionImpl();
            recognition.lang = 'en-US';
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            recognition.addEventListener('result', (e) => {
                ask(e.results[0][0].transcript);
            });

            recognition.addEventListener('end', () => {
                listening = false;
                // Recognition always stops itself after one utterance, a
                // silence timeout, or an error - restart automatically
                // unless a request is in flight or Nova is mid-sentence,
                // in which case speakAsNova() below restarts it once she's
                // done talking.
                if (!busy) startListening();
            });

            recognition.addEventListener('error', (e) => {
                if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                    setStatus(null, 'Microphone access is blocked - allow it and reload');
                }
                // Anything else (no-speech, audio-capture, aborted, network)
                // is handled by the 'end' listener above, which retries.
            });
        } else {
            setStatus(null, "Voice isn't supported in this browser");
        }

        function startListening() {
            if (!recognition || busy || listening) return;

            try {
                recognition.start();
                listening = true;
                setStatus('listening', 'Listening...');
            } catch (e) {
                listening = false;
                // Most likely: the browser won't start recognition without
                // a user gesture yet. The one-time click-anywhere listener
                // below supplies that gesture the moment it happens.
                setStatus(null, 'Tap anywhere to begin');
            }
        }

        /* ---------------- voice output: pinned profile + tuned delivery =================
           Verified voice: 'Google UK English Female', pitch 0.95, rate 1.08.
           Falls back to a broader heuristic when that exact voice isn't
           installed on this browser/OS, so Nova never goes silent - but the
           pitch/rate stay fixed either way. Always re-arms listening once
           she's done speaking, which is what keeps the conversation loop
           genuinely hands-free from here on. */
        let cachedVoices = [];
        function loadVoices() {
            cachedVoices = window.speechSynthesis ? window.speechSynthesis.getVoices() : [];
        }
        if (window.speechSynthesis) {
            loadVoices();
            window.speechSynthesis.addEventListener('voiceschanged', loadVoices);
        }

        function pickVoice() {
            const exact = cachedVoices.find((v) => v.name === 'Google UK English Female');
            if (exact) return exact;

            const fallbackNames = [
                'Google US English Female', 'Microsoft Zira', 'Microsoft Aria', 'Microsoft Jenny',
                'Microsoft Hazel', 'Microsoft Susan', 'Microsoft Libby', 'Microsoft Sonia', 'Microsoft Michelle',
                'Samantha', 'Victoria', 'Karen', 'Moira', 'Tessa', 'Fiona', 'Emma'
            ];
            for (const name of fallbackNames) {
                const match = cachedVoices.find((v) => v.name.includes(name));
                if (match) return match;
            }

            const byMeta = cachedVoices.find((v) => /female/i.test(v.name));
            if (byMeta) return byMeta;

            const english = cachedVoices.find((v) => /^en/i.test(v.lang));
            return english || cachedVoices[0] || null;
        }

        function speakAsNova(text) {
            setStatus('speaking', 'Speaking...');

            const resume = () => {
                busy = false;
                startListening();
            };

            if (!window.speechSynthesis || !text) {
                resume();
                return;
            }

            window.speechSynthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            const voice = pickVoice();
            if (voice) utterance.voice = voice;
            utterance.pitch = 0.95;
            utterance.rate = 1.08;
            utterance.onend = resume;
            utterance.onerror = resume;

            window.speechSynthesis.speak(utterance);
        }

        /* ---------------- kick off the loop ---------------- */
        startListening();
        document.addEventListener('click', function primeMic() {
            document.removeEventListener('click', primeMic);
            startListening();
        }, { once: true });
    })();
    </script>
</body>

</html>
