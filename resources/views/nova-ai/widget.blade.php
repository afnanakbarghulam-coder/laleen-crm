{{--
    Nova - executive AI assistant widget.
    Self-contained: styling, markup and behaviour all live in this one
    partial (own "frontend component directory": resources/views/nova-ai/),
    included once from layouts/app.blade.php behind an admin-only @if so it
    never even reaches the DOM for anyone else.
--}}
<style>
    #nova-trigger {
        position: fixed;
        right: 26px;
        bottom: 26px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        border: 1px solid var(--luxe-border-strong);
        background: linear-gradient(145deg, var(--luxe-accent), #8a5a6e);
        color: var(--luxe-accent-ink);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35), 0 0 0 rgba(217, 143, 131, 0.5);
        z-index: 2147483000;
        transition: transform .15s ease, box-shadow .2s ease;
    }

    #nova-trigger:hover { transform: scale(1.06); }
    #nova-trigger:focus-visible { outline: 2px solid var(--luxe-accent-hover); outline-offset: 3px; }

    #nova-trigger.nova-open { transform: scale(0.94); }

    #nova-trigger .nova-pulse {
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid var(--luxe-accent);
        opacity: 0;
    }

    #nova-trigger.nova-listening .nova-pulse {
        opacity: 1;
        animation: nova-pulse 1.4s ease-out infinite;
    }

    @keyframes nova-pulse {
        0% { transform: scale(1); opacity: .7; }
        100% { transform: scale(1.5); opacity: 0; }
    }

    #nova-panel {
        position: fixed;
        right: 26px;
        bottom: 96px;
        width: 380px;
        max-width: calc(100vw - 40px);
        height: 560px;
        max-height: calc(100vh - 140px);
        background: var(--luxe-surface-solid);
        border: 1px solid var(--luxe-border);
        border-radius: var(--luxe-radius);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
        display: none;
        flex-direction: column;
        overflow: hidden;
        z-index: 2147483000;
        font-family: 'Poppins', ui-sans-serif, sans-serif;
        color: var(--luxe-body);
    }

    #nova-panel.nova-open { display: flex; }

    .nova-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--luxe-border);
        background: var(--luxe-bg-elevated);
    }

    .nova-head-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        font-size: 17px;
        color: var(--luxe-ink);
    }

    .nova-head-title .bx { font-size: 20px; color: var(--luxe-accent); }

    .nova-head-actions { display: flex; align-items: center; gap: 6px; }

    .nova-icon-btn {
        border: none;
        background: transparent;
        color: var(--luxe-muted);
        font-size: 18px;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .nova-icon-btn:hover { background: var(--luxe-surface-hover); color: var(--luxe-ink); }
    .nova-icon-btn.nova-muted { color: var(--luxe-danger); }

    .nova-body {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .nova-msg {
        max-width: 88%;
        padding: 10px 13px;
        border-radius: 14px;
        font-size: 13.5px;
        line-height: 1.5;
        white-space: pre-wrap;
    }

    .nova-msg.user {
        align-self: flex-end;
        background: var(--luxe-accent);
        color: var(--luxe-accent-ink);
        border-bottom-right-radius: 4px;
    }

    .nova-msg.nova {
        align-self: flex-start;
        background: var(--luxe-surface-2);
        border: 1px solid var(--luxe-border);
        border-bottom-left-radius: 4px;
    }

    .nova-msg.nova.nova-thinking { color: var(--luxe-muted); font-style: italic; }

    .nova-empty {
        margin: auto;
        text-align: center;
        color: var(--luxe-muted);
        font-size: 13px;
        padding: 0 12px;
    }

    .nova-empty .bx { font-size: 30px; color: var(--luxe-accent); display: block; margin: 0 auto 8px; }

    .nova-input-row {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 12px;
        border-top: 1px solid var(--luxe-border);
        background: var(--luxe-bg-elevated);
    }

    #nova-input {
        flex: 1 1 auto;
        resize: none;
        max-height: 90px;
        min-height: 38px;
        background: var(--luxe-surface-2);
        border: 1px solid var(--luxe-border);
        border-radius: 10px;
        color: var(--luxe-body);
        padding: 9px 12px;
        font-size: 13.5px;
        font-family: inherit;
    }

    #nova-input:focus { outline: none; border-color: var(--luxe-accent); }

    .nova-round-btn {
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1px solid var(--luxe-border);
        background: var(--luxe-surface-2);
        color: var(--luxe-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        cursor: pointer;
    }

    .nova-round-btn:hover { color: var(--luxe-ink); border-color: var(--luxe-border-strong); }
    .nova-round-btn:disabled { opacity: .35; cursor: not-allowed; }

    #nova-mic.nova-listening {
        background: var(--luxe-accent);
        color: var(--luxe-accent-ink);
        border-color: var(--luxe-accent);
    }

    #nova-send {
        background: var(--luxe-accent);
        color: var(--luxe-accent-ink);
        border-color: var(--luxe-accent);
    }

    @media (max-width: 480px) {
        #nova-panel { right: 14px; left: 14px; width: auto; bottom: 90px; }
        #nova-trigger { right: 18px; bottom: 18px; }
    }
</style>

<button id="nova-trigger" type="button" aria-label="Open Nova, your executive assistant" aria-expanded="false">
    <span class="nova-pulse" aria-hidden="true"></span>
    <i class="bx bxs-bot" aria-hidden="true"></i>
</button>

<div id="nova-panel" role="dialog" aria-label="Nova executive assistant" aria-hidden="true">
    <div class="nova-head">
        <div class="nova-head-title"><i class="bx bxs-bot"></i> Nova</div>
        <div class="nova-head-actions">
            <button type="button" id="nova-mute" class="nova-icon-btn" aria-label="Mute Nova's spoken replies" title="Mute voice replies">
                <i class="bx bx-volume-full"></i>
            </button>
            <button type="button" id="nova-close" class="nova-icon-btn" aria-label="Close Nova">
                <i class="bx bx-x"></i>
            </button>
        </div>
    </div>

    <div class="nova-body" id="nova-body">
        <div class="nova-empty" id="nova-empty">
            <i class="bx bxs-bot"></i>
            Ask Nova about today's revenue, staff performance, service demand, or
            clients sitting on an unused package balance - by voice or by typing.
        </div>
    </div>

    <div class="nova-input-row">
        <button type="button" id="nova-mic" class="nova-round-btn" aria-label="Speak your question" title="Speak your question">
            <i class="bx bx-microphone"></i>
        </button>
        <textarea id="nova-input" rows="1" placeholder="Ask Nova anything about the business…"></textarea>
        <button type="button" id="nova-send" class="nova-round-btn" aria-label="Send question" title="Send">
            <i class="bx bx-send"></i>
        </button>
    </div>
</div>

<script>
(function () {
    const trigger = document.getElementById('nova-trigger');
    const panel = document.getElementById('nova-panel');
    const body = document.getElementById('nova-body');
    const empty = document.getElementById('nova-empty');
    const input = document.getElementById('nova-input');
    const sendBtn = document.getElementById('nova-send');
    const micBtn = document.getElementById('nova-mic');
    const closeBtn = document.getElementById('nova-close');
    const muteBtn = document.getElementById('nova-mute');

    let muted = false;
    let busy = false;

    /* ---------------- conversation memory ---------------- */
    // Shared with the command center so both Nova frontends continue the
    // same conversation. Purely a client-side continuity token - wrapped
    // in try/catch since localStorage can throw (private browsing, storage
    // disabled); losing it just means Nova starts a fresh conversation.
    const NOVA_CONVERSATION_KEY = 'nova_conversation_id';

    function getConversationId() {
        try {
            return localStorage.getItem(NOVA_CONVERSATION_KEY);
        } catch (e) {
            return null;
        }
    }

    function setConversationId(id) {
        try {
            if (id) localStorage.setItem(NOVA_CONVERSATION_KEY, id);
        } catch (e) {
            // ignore
        }
    }

    /* ---------------- panel open/close ---------------- */
    function openPanel() {
        panel.classList.add('nova-open');
        panel.setAttribute('aria-hidden', 'false');
        trigger.classList.add('nova-open');
        trigger.setAttribute('aria-expanded', 'true');
        input.focus();
    }

    function closePanel() {
        panel.classList.remove('nova-open');
        panel.setAttribute('aria-hidden', 'true');
        trigger.classList.remove('nova-open');
        trigger.setAttribute('aria-expanded', 'false');
        stopListening();
    }

    trigger.addEventListener('click', () => {
        panel.classList.contains('nova-open') ? closePanel() : openPanel();
    });
    closeBtn.addEventListener('click', closePanel);

    /* ---------------- transcript ---------------- */
    function addMessage(text, who) {
        if (empty) empty.remove();

        const el = document.createElement('div');
        el.className = 'nova-msg ' + who;
        el.textContent = text;
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;

        return el;
    }

    /* ---------------- ask Nova ---------------- */
    function ask(message) {
        message = message.trim();
        if (!message || busy) return;

        busy = true;
        input.value = '';
        autosize();
        addMessage(message, 'user');

        const thinking = addMessage('Nova is thinking…', 'nova nova-thinking');

        fetch("{{ route('nova.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message, conversation_id: getConversationId() })
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                thinking.remove();
                const reply = ok ? (data.reply || "Nova didn't return an answer.") :
                    (data.message || "Nova couldn't process that question.");
                if (ok && data.conversation_id) setConversationId(data.conversation_id);
                addMessage(reply, 'nova');
                speak(reply);
            })
            .catch(() => {
                thinking.remove();
                addMessage("Nova couldn't reach the server. Check your connection and try again.", 'nova');
            })
            .finally(() => { busy = false; });
    }

    sendBtn.addEventListener('click', () => ask(input.value));
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            ask(input.value);
        }
    });
    input.addEventListener('input', autosize);

    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 90) + 'px';
    }

    /* ---------------- voice input (Web Speech API) ---------------- */
    const SpeechRecognitionImpl = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let listening = false;

    if (SpeechRecognitionImpl) {
        recognition = new SpeechRecognitionImpl();
        recognition.lang = 'en-US';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.addEventListener('result', (e) => {
            const transcript = e.results[0][0].transcript;
            input.value = transcript;
            ask(transcript);
        });

        recognition.addEventListener('end', stopListening);
        recognition.addEventListener('error', stopListening);
    } else {
        micBtn.disabled = true;
        micBtn.title = 'Voice input is not supported in this browser';
    }

    function startListening() {
        if (!recognition || listening) return;
        listening = true;
        micBtn.classList.add('nova-listening');
        trigger.classList.add('nova-listening');
        try { recognition.start(); } catch (e) { stopListening(); }
    }

    function stopListening() {
        if (!listening) return;
        listening = false;
        micBtn.classList.remove('nova-listening');
        trigger.classList.remove('nova-listening');
        try { recognition && recognition.stop(); } catch (e) {}
    }

    micBtn.addEventListener('click', () => listening ? stopListening() : startListening());

    /* ---------------- voice output (SpeechSynthesis) ---------------- */
    let cachedVoices = [];

    function loadVoices() {
        cachedVoices = window.speechSynthesis ? window.speechSynthesis.getVoices() : [];
    }

    if (window.speechSynthesis) {
        loadVoices();
        window.speechSynthesis.addEventListener('voiceschanged', loadVoices);
    }

    // Preference order: named female voices most browsers ship, then any
    // voice whose own metadata says "female", then any English voice, then
    // whatever's first - so Nova always has something to speak with.
    function pickFemaleVoice() {
        const preferredNames = [
            'Google UK English Female', 'Google US English Female',
            'Microsoft Zira', 'Microsoft Aria', 'Microsoft Jenny', 'Microsoft Hazel',
            'Microsoft Susan', 'Microsoft Libby', 'Microsoft Sonia', 'Microsoft Michelle',
            'Samantha', 'Victoria', 'Karen', 'Moira', 'Tessa', 'Fiona', 'Emma'
        ];

        for (const name of preferredNames) {
            const match = cachedVoices.find(v => v.name.includes(name));
            if (match) return match;
        }

        const byMeta = cachedVoices.find(v => /female/i.test(v.name));
        if (byMeta) return byMeta;

        const english = cachedVoices.find(v => /^en/i.test(v.lang));
        if (english) return english;

        return cachedVoices[0] || null;
    }

    function speak(text) {
        if (muted || !window.speechSynthesis || !text) return;

        window.speechSynthesis.cancel();

        const utterance = new SpeechSynthesisUtterance(text);
        const voice = pickFemaleVoice();
        if (voice) utterance.voice = voice;
        utterance.pitch = 1.05;
        utterance.rate = 1;

        window.speechSynthesis.speak(utterance);
    }

    muteBtn.addEventListener('click', () => {
        muted = !muted;
        muteBtn.classList.toggle('nova-muted', muted);
        muteBtn.querySelector('.bx').className = 'bx ' + (muted ? 'bx-volume-mute' : 'bx-volume-full');
        if (muted && window.speechSynthesis) window.speechSynthesis.cancel();
    });
})();
</script>
