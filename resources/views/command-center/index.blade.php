@extends('layouts.app')

@section('title', 'Command Center — Laleen Ops')

@section('content')
<style>
    .cc-wrap {
        max-width: 960px;
        margin: 0 auto;
        padding: 4px 0 12px;
        font-family: 'Poppins', sans-serif;
        color: #f8efe6;
    }

    .cc-shell {
        min-height: calc(100vh - 156px);
        max-height: calc(100vh - 128px);
        display: flex;
        flex-direction: column;
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.42);
        overflow: hidden;
    }

    /* 1. Compact Header */
    .cc-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 22px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        flex-wrap: wrap;
    }

    .cc-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .cc-title {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        font-weight: 700;
        color: #f8efe6;
        margin: 0;
        letter-spacing: 0.02em;
    }

    .cc-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 11.5px;
        font-weight: 500;
        color: #b8a29a;
        padding: 3px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.03);
    }

    .cc-status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .cc-status-dot.online {
        background: #6ee7b7;
        box-shadow: 0 0 8px rgba(110, 231, 183, 0.55);
    }

    .cc-status-dot.snapshot {
        background: #d4a373;
        box-shadow: 0 0 8px rgba(212, 163, 115, 0.45);
    }

    .cc-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cc-mode-switch {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 3px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.04);
    }

    .cc-mode-btn {
        border: none;
        background: transparent;
        color: #b8a29a;
        font-size: 11.5px;
        font-weight: 500;
        padding: 4px 11px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.16s ease;
    }

    .cc-mode-btn.active {
        background: rgba(229, 184, 168, 0.18);
        color: #f8efe6;
        font-weight: 600;
    }

    .cc-clear-btn {
        border: 1px solid rgba(229, 184, 168, 0.18);
        background: transparent;
        color: #b8a29a;
        font-size: 12px;
        font-weight: 500;
        padding: 5px 13px;
        border-radius: 999px;
        cursor: pointer;
        transition: color 0.16s ease, border-color 0.16s ease, background 0.16s ease;
    }

    .cc-clear-btn:hover {
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.38);
        background: rgba(229, 184, 168, 0.08);
    }

    /* 2. Scrollable Message Thread & Empty State */
    .cc-thread {
        flex: 1;
        overflow-y: auto;
        padding: 24px 22px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        scroll-behavior: smooth;
    }

    .cc-empty-state {
        margin: auto 0;
        text-align: center;
        padding: 28px 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
    }

    .cc-empty-heading {
        font-family: 'Playfair Display', serif;
        font-size: 21px;
        font-weight: 600;
        color: #f8efe6;
        margin: 0;
    }

    .cc-empty-sub {
        font-size: 13px;
        color: #b8a29a;
        max-width: 500px;
        line-height: 1.55;
        margin: 0;
    }

    .cc-inline-credential-note {
        font-size: 12px;
        color: #d4a373;
        margin: 0;
    }

    .cc-chips-row {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 9px;
        max-width: 680px;
        margin-top: 4px;
    }

    .cc-prompt-chip {
        border: 1px solid rgba(229, 184, 168, 0.20);
        background: rgba(229, 184, 168, 0.06);
        color: #f8efe6;
        font-size: 12.5px;
        font-weight: 500;
        padding: 8px 15px;
        border-radius: 999px;
        cursor: pointer;
        transition: background 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
    }

    .cc-prompt-chip:hover {
        background: rgba(229, 184, 168, 0.15);
        border-color: rgba(229, 184, 168, 0.42);
        transform: translateY(-1px);
    }

    /* Message Rows & Bubbles */
    .cc-msg-row {
        display: flex;
        flex-direction: column;
        max-width: 82%;
        gap: 4px;
    }

    .cc-msg-row.user {
        align-self: flex-end;
        align-items: flex-end;
    }

    .cc-msg-row.assistant {
        align-self: flex-start;
        align-items: flex-start;
    }

    .cc-bubble {
        padding: 12px 16px;
        border-radius: 14px;
        font-size: 13.5px;
        line-height: 1.62;
        word-break: break-word;
    }

    .cc-msg-row.user .cc-bubble {
        background: linear-gradient(135deg, rgba(217, 143, 131, 0.24), rgba(212, 163, 115, 0.18));
        border: 1px solid rgba(229, 184, 168, 0.28);
        color: #f8efe6;
        border-bottom-right-radius: 4px;
    }

    .cc-msg-row.assistant .cc-bubble {
        background: rgba(255, 255, 255, 0.035);
        border: 1px solid rgba(255, 255, 255, 0.07);
        color: #f8efe6;
        border-bottom-left-radius: 4px;
    }

    .cc-bubble p {
        margin: 0 0 8px;
    }

    .cc-bubble p:last-child {
        margin-bottom: 0;
    }

    .cc-bubble ul,
    .cc-bubble ol {
        margin: 6px 0 8px;
        padding-left: 18px;
    }

    .cc-bubble ul:last-child,
    .cc-bubble ol:last-child {
        margin-bottom: 0;
    }

    .cc-bubble li {
        margin-bottom: 4px;
    }

    .cc-bubble strong {
        color: #e5b8a8;
        font-weight: 600;
    }

    .cc-bubble blockquote {
        margin: 8px 0 0;
        padding-left: 11px;
        border-left: 2px solid rgba(229, 184, 168, 0.45);
        color: #e6d7cf;
        font-size: 13px;
    }

    .cc-msg-meta {
        font-size: 11px;
        color: #8f7b74;
        padding: 0 4px;
    }

    .cc-wa-inline {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-top: 6px;
        font-size: 12px;
    }

    .cc-wa-link {
        color: #6ee7b7 !important;
        font-weight: 600;
        text-decoration: none !important;
    }

    .cc-wa-link:hover {
        text-decoration: underline !important;
    }

    .cc-wa-copy {
        border: none;
        background: transparent;
        color: #b8a29a;
        font-size: 11.5px;
        padding: 0;
        cursor: pointer;
    }

    .cc-wa-copy:hover {
        color: #f8efe6;
    }

    /* Typing Indicator */
    .cc-typing {
        display: none;
        align-self: flex-start;
        align-items: center;
        gap: 6px;
        padding: 10px 14px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.035);
        border: 1px solid rgba(255, 255, 255, 0.07);
    }

    .cc-typing.visible {
        display: inline-flex;
    }

    .cc-typing-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #e5b8a8;
        opacity: 0.4;
        animation: ccPulse 1.2s infinite ease-in-out;
    }

    .cc-typing-dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .cc-typing-dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes ccPulse {
        0%, 80%, 100% { opacity: 0.3; transform: translateY(0); }
        40% { opacity: 1; transform: translateY(-2px); }
    }

    /* 3. Bottom Input Bar */
    .cc-composer {
        padding: 14px 20px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        background: rgba(16, 11, 14, 0.65);
    }

    .cc-composer-form {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }

    .cc-textarea {
        flex: 1;
        resize: none;
        min-height: 42px;
        max-height: 140px;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid rgba(229, 184, 168, 0.20);
        background: rgba(255, 255, 255, 0.035);
        color: #f8efe6;
        font-family: 'Poppins', sans-serif;
        font-size: 13.5px;
        line-height: 1.45;
        outline: none;
        transition: border-color 0.16s ease;
    }

    .cc-textarea:focus {
        border-color: rgba(229, 184, 168, 0.48);
    }

    .cc-textarea::placeholder {
        color: #8f7b74;
    }

    .cc-send-btn {
        height: 42px;
        padding: 0 20px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #d98f83, #d4a373);
        color: #1c1215;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        flex-shrink: 0;
        transition: opacity 0.16s ease;
    }

    .cc-send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    @media (max-width: 640px) {
        .cc-shell {
            min-height: calc(100vh - 140px);
            max-height: none;
        }
        .cc-msg-row {
            max-width: 92%;
        }
    }

    @media print {
        .cc-shell {
            background: #ffffff !important;
            color: #111111 !important;
            border: 1px solid #dddddd !important;
            box-shadow: none !important;
            max-height: none !important;
        }
        .cc-title,
        .cc-bubble,
        .cc-bubble strong {
            color: #111111 !important;
        }
        .cc-header-actions,
        .cc-composer,
        .cc-chips-row {
            display: none !important;
        }
    }
</style>

<div class="cc-wrap">
    <div class="cc-shell">
        {{-- 1. Header --}}
        <div class="cc-header">
            <div class="cc-header-left">
                <h1 class="cc-title">Command Center</h1>
                <span class="cc-status-pill" id="ccStatusPill">
                    <span class="cc-status-dot {{ $aiConfigured ? 'online' : 'snapshot' }}"></span>
                    <span>{{ $aiConfigured ? 'Ready · Live CRM' : 'Live CRM Summary' }}</span>
                </span>
            </div>

            <div class="cc-header-actions">
                <div class="cc-mode-switch" role="group" aria-label="Response depth">
                    <button type="button" class="cc-mode-btn active" data-cc-mode="fast">Concise</button>
                    <button type="button" class="cc-mode-btn" data-cc-mode="deep">Deep Analysis</button>
                </div>
                <button type="button" class="cc-clear-btn" id="ccClearChatBtn">Clear Chat</button>
            </div>
        </div>

        {{-- 2. Message Thread & Empty State --}}
        <div class="cc-thread" id="ccMessageThread">
            <div class="cc-empty-state" id="ccEmptyState">
                <h2 class="cc-empty-heading">How can I help with salon operations today?</h2>
                <p class="cc-empty-sub">
                    Ask anything across Old Airport and Wakrah—leads, branch bookings, pending ops actions, ad performance, or floor upsells.
                </p>
                @if (!$aiConfigured)
                    <p class="cc-inline-credential-note" id="ccCredentialNote">
                        AI assistant key is not configured — instant live CRM summaries are active.
                    </p>
                @endif
                <div class="cc-chips-row">
                    <button type="button" class="cc-prompt-chip" data-prompt="Today's Leads Summary">Today's Leads Summary</button>
                    <button type="button" class="cc-prompt-chip" data-prompt="Pending Ops Actions">Pending Ops Actions</button>
                    <button type="button" class="cc-prompt-chip" data-prompt="Branch Bookings Overview">Branch Bookings Overview</button>
                    <button type="button" class="cc-prompt-chip" data-prompt="Ad & Follow-Up Check">Ad &amp; Follow-Up Check</button>
                </div>
            </div>

            <div class="cc-typing" id="ccTypingIndicator" aria-live="polite" aria-label="Command Center is replying">
                <span class="cc-typing-dot"></span>
                <span class="cc-typing-dot"></span>
                <span class="cc-typing-dot"></span>
            </div>
        </div>

        {{-- 3. Bottom Text Input Bar --}}
        <div class="cc-composer">
            <form id="ccChatForm" class="cc-composer-form">
                @csrf
                <textarea
                    id="ccPromptInput"
                    class="cc-textarea"
                    rows="1"
                    placeholder="Ask Command Center anything about leads, bookings, ops, or staff..."
                    autocomplete="off"
                ></textarea>
                <button type="submit" id="ccSendBtn" class="cc-send-btn">Send</button>
            </form>
        </div>
    </div>
</div>

@php
    $ccEndpoint = route('command-center.ask');
    $ccCsrf = csrf_token();
@endphp

<script>
(function () {
    const askUrl = {{ \Illuminate\Support\Js::from($ccEndpoint) }};
    const csrfToken = {{ \Illuminate\Support\Js::from($ccCsrf) }};

    const threadEl = document.getElementById('ccMessageThread');
    const emptyStateEl = document.getElementById('ccEmptyState');
    const typingEl = document.getElementById('ccTypingIndicator');
    const formEl = document.getElementById('ccChatForm');
    const inputEl = document.getElementById('ccPromptInput');
    const sendBtnEl = document.getElementById('ccSendBtn');
    const clearBtnEl = document.getElementById('ccClearChatBtn');
    const modeBtns = document.querySelectorAll('[data-cc-mode]');
    const chipBtns = document.querySelectorAll('.cc-prompt-chip');

    let currentMode = 'fast';
    let isSending = false;
    let chatHistory = [];

    function escapeHtml(raw) {
        return String(raw || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatInlineMarkdown(escapedLine) {
        return escapedLine.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    }

    function formatMessageHtml(text) {
        const lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
        const htmlParts = [];
        let listType = null;

        function closeList() {
            if (listType) {
                htmlParts.push('</' + listType + '>');
                listType = null;
            }
        }

        for (let i = 0; i < lines.length; i++) {
            const trimmed = lines[i].trim();

            if (!trimmed) {
                closeList();
                continue;
            }

            const bulletMatch = trimmed.match(/^[-*•]\s+(.+)$/);
            if (bulletMatch) {
                if (listType !== 'ul') {
                    closeList();
                    listType = 'ul';
                    htmlParts.push('<ul>');
                }
                htmlParts.push('<li>' + formatInlineMarkdown(escapeHtml(bulletMatch[1])) + '</li>');
                continue;
            }

            const numMatch = trimmed.match(/^\d+\.\s+(.+)$/);
            if (numMatch) {
                if (listType !== 'ol') {
                    closeList();
                    listType = 'ol';
                    htmlParts.push('<ol>');
                }
                htmlParts.push('<li>' + formatInlineMarkdown(escapeHtml(numMatch[1])) + '</li>');
                continue;
            }

            closeList();

            if (trimmed.startsWith('>')) {
                const quoteBody = trimmed.replace(/^>\s*/, '');
                htmlParts.push('<blockquote>' + formatInlineMarkdown(escapeHtml(quoteBody)) + '</blockquote>');
            } else {
                const headingClean = trimmed.replace(/^#{1,3}\s+/, '');
                if (headingClean !== trimmed) {
                    htmlParts.push('<p><strong>' + formatInlineMarkdown(escapeHtml(headingClean)) + '</strong></p>');
                } else {
                    htmlParts.push('<p>' + formatInlineMarkdown(escapeHtml(trimmed)) + '</p>');
                }
            }
        }

        closeList();

        return htmlParts.join('') || '<p>' + escapeHtml(text) + '</p>';
    }

    function formatTimestamp() {
        try {
            return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '';
        }
    }

    function autoResizeInput() {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px';
    }

    function scrollToBottom() {
        threadEl.scrollTop = threadEl.scrollHeight;
    }

    function appendMessage(role, content, whatsappAction) {
        if (emptyStateEl) {
            emptyStateEl.style.display = 'none';
        }

        const row = document.createElement('div');
        row.className = 'cc-msg-row ' + (role === 'user' ? 'user' : 'assistant');

        const bubble = document.createElement('div');
        bubble.className = 'cc-bubble';
        if (role === 'user') {
            bubble.innerHTML = '<p>' + escapeHtml(content).replace(/\n/g, '<br>') + '</p>';
        } else {
            bubble.innerHTML = formatMessageHtml(content);
        }
        row.appendChild(bubble);

        if (role === 'assistant' && whatsappAction && whatsappAction.whatsapp_url && whatsappAction.whatsapp_message) {
            const waBar = document.createElement('div');
            waBar.className = 'cc-wa-inline';

            const waLink = document.createElement('a');
            waLink.className = 'cc-wa-link';
            waLink.href = whatsappAction.whatsapp_url;
            waLink.target = '_blank';
            waLink.rel = 'noopener noreferrer';
            waLink.textContent = 'Open in WhatsApp (' + (whatsappAction.client_name || 'Client') + ') ↗';
            waBar.appendChild(waLink);

            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'cc-wa-copy';
            copyBtn.textContent = 'Copy message';
            copyBtn.addEventListener('click', function () {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(whatsappAction.whatsapp_message).then(function () {
                        copyBtn.textContent = 'Copied';
                        setTimeout(function () { copyBtn.textContent = 'Copy message'; }, 1500);
                    }).catch(function () {});
                }
            });
            waBar.appendChild(copyBtn);

            row.appendChild(waBar);
        }

        const meta = document.createElement('div');
        meta.className = 'cc-msg-meta';
        meta.textContent = (role === 'user' ? 'You' : 'Command Center') + ' · ' + formatTimestamp();
        row.appendChild(meta);

        threadEl.insertBefore(row, typingEl);
        scrollToBottom();
    }

    async function sendPrompt(rawText) {
        const prompt = String(rawText || '').trim();
        if (!prompt || isSending) {
            return;
        }

        isSending = true;
        sendBtnEl.disabled = true;
        inputEl.value = '';
        autoResizeInput();

        appendMessage('user', prompt, null);
        typingEl.classList.add('visible');
        scrollToBottom();

        try {
            const response = await fetch(askUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    prompt: prompt,
                    mode: currentMode,
                    history: chatHistory.slice(-8)
                })
            });

            let data = {};
            try {
                data = await response.json();
            } catch (parseErr) {
                data = {};
            }

            typingEl.classList.remove('visible');

            const replyText = (data && (data.reply || data.message))
                ? String(data.reply || data.message)
                : 'Command Center could not process that request right now. Please try again.';

            appendMessage('assistant', replyText, data ? data.whatsapp_action : null);

            if (response.ok && data && data.reply) {
                chatHistory.push({ role: 'user', content: prompt });
                chatHistory.push({ role: 'assistant', content: replyText });
            }
        } catch (networkErr) {
            typingEl.classList.remove('visible');
            appendMessage(
                'assistant',
                'Connection interrupted while reaching Command Center. Please check your connection and try again.',
                null
            );
        } finally {
            isSending = false;
            sendBtnEl.disabled = false;
            inputEl.focus();
        }
    }

    modeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentMode = btn.getAttribute('data-cc-mode') || 'fast';
            modeBtns.forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
        });
    });

    chipBtns.forEach(function (chip) {
        chip.addEventListener('click', function () {
            const text = chip.getAttribute('data-prompt') || chip.textContent;
            sendPrompt(text);
        });
    });

    clearBtnEl.addEventListener('click', function () {
        chatHistory = [];
        typingEl.classList.remove('visible');
        const rows = threadEl.querySelectorAll('.cc-msg-row');
        rows.forEach(function (r) { r.remove(); });
        if (emptyStateEl) {
            emptyStateEl.style.display = 'flex';
        }
        inputEl.focus();
    });

    inputEl.addEventListener('input', autoResizeInput);

    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendPrompt(inputEl.value);
        }
    });

    formEl.addEventListener('submit', function (e) {
        e.preventDefault();
        sendPrompt(inputEl.value);
    });
})();
</script>
@endsection
