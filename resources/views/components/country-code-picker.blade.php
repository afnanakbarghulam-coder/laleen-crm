@props([
    'name' => 'country_code',
    'id' => null,
    'value' => '974',
    'inputClass' => 'lead-country-code',
])

@php
    $countriesConfig = config('countries.list', []);
    $pinnedIsos = config('countries.pinned_isos', ['QA']);
    $rawVal = preg_replace('/\D+/', '', preg_replace('/^\s*00/', '', (string) ($value ?? '974')));
    $cleanInitial = $rawVal !== '' ? $rawVal : '974';
@endphp

@once
    <style>
        .ccp-root {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
        }

        .ccp-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            min-width: 82px;
            height: 100%;
            padding: 0.375rem 0.65rem;
            font-size: 13px;
            font-weight: 600;
            color: #f8efe6;
            background: #1c1518;
            border: 1px solid rgba(229, 184, 168, 0.24);
            border-right: 0;
            border-radius: 0.375rem 0 0 0.375rem;
            cursor: pointer;
            white-space: nowrap;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .ccp-trigger:hover,
        .ccp-trigger:focus-visible,
        .ccp-root.is-open .ccp-trigger {
            background: #251b1f;
            border-color: rgba(229, 184, 168, 0.48);
            color: #f8efe6;
            outline: none;
        }

        .ccp-trigger-code {
            color: #f8efe6;
            letter-spacing: 0.01em;
        }

        .ccp-trigger-caret {
            font-size: 11px;
            color: #d4a373;
            line-height: 1;
        }

        .ccp-dropdown {
            position: fixed;
            width: 340px;
            min-width: 310px;
            max-width: calc(100vw - 24px);
            background: linear-gradient(180deg, rgba(34, 22, 27, 0.99) 0%, rgba(21, 14, 18, 0.99) 100%);
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 12px;
            box-shadow: 0 18px 42px rgba(0, 0, 0, 0.55);
            z-index: 2070;
            pointer-events: auto;
            overflow: hidden;
            display: none;
        }

        .ccp-dropdown.is-visible {
            display: block;
        }

        .ccp-search-wrap {
            padding: 9px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(21, 14, 18, 0.98);
            pointer-events: auto;
        }

        .ccp-search-input {
            width: 100%;
            padding: 6px 10px;
            font-size: 12.5px;
            color: #f8efe6;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 8px;
            outline: none;
            pointer-events: auto;
            cursor: text;
            user-select: text;
        }

        .ccp-search-input::placeholder {
            color: #9e8982;
        }

        .ccp-search-input:focus {
            border-color: #d98f83;
            box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.16);
        }

        .ccp-options-list {
            max-height: 250px;
            overflow-y: auto;
            padding: 4px 0;
        }

        .ccp-divider {
            height: 1px;
            margin: 4px 10px;
            background: rgba(255, 255, 255, 0.07);
        }

        .ccp-option {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 6px 12px;
            border: 0;
            background: transparent;
            color: #f8efe6;
            font-size: 12.5px;
            text-align: left;
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .ccp-option:hover,
        .ccp-option.is-highlighted {
            background: rgba(229, 184, 168, 0.12);
        }

        .ccp-option.is-selected {
            background: rgba(212, 163, 115, 0.16);
        }

        .ccp-opt-code {
            display: inline-block;
            min-width: 48px;
            font-weight: 700;
            color: #e5b8a8;
            font-variant-numeric: tabular-nums;
            flex-shrink: 0;
        }

        .ccp-opt-name {
            color: #f8efe6;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1 1 auto;
        }

        .ccp-empty {
            padding: 14px 12px;
            text-align: center;
            font-size: 12px;
            color: #b8a29a;
        }

        @media print {
            .ccp-dropdown {
                display: none !important;
            }
        }
    </style>

    <script>
        window.__LALEEN_COUNTRIES__ = @json($countriesConfig);
        window.__LALEEN_PINNED_ISOS__ = @json($pinnedIsos);

        (function () {
            if (window.__LALEEN_CCP_BOOTED__) return;
            window.__LALEEN_CCP_BOOTED__ = true;

            const RAW_LIST = (window.__LALEEN_COUNTRIES__ || []).map(item => {
                const digits = String(item.dial_code || '').replace(/\D+/g, '');
                return {
                    iso: String(item.iso || '').toUpperCase(),
                    name: String(item.name || ''),
                    nameLower: String(item.name || '').toLowerCase(),
                    dial: '+' + digits,
                    code: digits,
                };
            }).filter(item => item.code !== '');

            const PINNED_ISOS = (window.__LALEEN_PINNED_ISOS__ || ['QA']).map(s => String(s).toUpperCase());
            const PINNED_ITEMS = PINNED_ISOS
                .map(iso => RAW_LIST.find(c => c.iso === iso))
                .filter(Boolean);

            // Unique dial codes sorted longest-first for Smart Paste prefix matching
            const UNIQUE_CODES_DESC = Array.from(new Set(RAW_LIST.map(c => c.code)))
                .sort((a, b) => b.length - a.length);

            const nativeValueDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
            let activeInstance = null;

            function sanitizeCode(val) {
                const clean = String(val ?? '').trim().replace(/^00+/, '').replace(/\D+/g, '');
                return clean || '974';
            }

            function filterCountries(rawQuery) {
                const q = String(rawQuery || '').trim();
                if (!q) {
                    return { pinned: PINNED_ITEMS, rest: RAW_LIST, isSearch: false };
                }

                const qLower = q.toLowerCase();
                const qDigits = q.replace(/^\+/, '').replace(/^00+/, '').replace(/\D+/g, '');
                const isNumericLike = /^[+0-9\s\-()]+$/.test(q) && qDigits.length > 0;

                const scored = [];
                for (const item of RAW_LIST) {
                    let rank = 999;

                    if (isNumericLike) {
                        if (item.code === qDigits) {
                            rank = item.iso === 'QA' ? 0 : (PINNED_ISOS.includes(item.iso) ? 1 : 2);
                        } else if (item.code.startsWith(qDigits)) {
                            rank = 10 + item.code.length;
                        } else if (item.nameLower.includes(qLower)) {
                            rank = 40;
                        }
                    } else {
                        if (item.iso.toLowerCase() === qLower) {
                            rank = 1;
                        } else if (qDigits && item.code === qDigits) {
                            rank = 2;
                        } else if (item.nameLower.startsWith(qLower)) {
                            rank = 5;
                        } else if (item.nameLower.includes(qLower)) {
                            rank = 15;
                        } else if (qDigits && item.code.startsWith(qDigits)) {
                            rank = 25;
                        }
                    }

                    if (rank < 999) {
                        scored.push({ item, rank });
                    }
                }

                scored.sort((a, b) => a.rank - b.rank || a.item.name.localeCompare(b.item.name));
                return { pinned: [], rest: scored.map(s => s.item), isSearch: true };
            }

            function matchInternationalPaste(rawPasted) {
                const trimmed = String(rawPasted || '').trim();
                if (!trimmed.startsWith('+') && !trimmed.startsWith('00')) {
                    return null;
                }

                let digits = trimmed.replace(/\D+/g, '');
                if (trimmed.startsWith('00')) {
                    digits = digits.replace(/^00+/, '');
                }
                if (digits.length < 7) {
                    return null;
                }

                const qatarMatch = digits.match(/^9740*(\d{8})$/);
                if (qatarMatch) {
                    return { code: '974', local: qatarMatch[1] };
                }

                for (const code of UNIQUE_CODES_DESC) {
                    if (!digits.startsWith(code)) continue;
                    const rest = digits.slice(code.length);
                    const restStripped = rest.replace(/^0+/, '');
                    if (code === '1') {
                        if (rest.length === 10) {
                            return { code: '1', local: rest };
                        }
                        continue;
                    }
                    if (restStripped.length >= 6 && restStripped.length <= 12) {
                        return { code, local: restStripped };
                    }
                }

                return null;
            }

            function initPicker(root) {
                if (!root || root.__ccpInitialized) return;
                root.__ccpInitialized = true;

                const hiddenInput = root.querySelector('.ccp-hidden-input');
                const triggerBtn = root.querySelector('.ccp-trigger');
                const triggerCodeEl = root.querySelector('.ccp-trigger-code');
                const dropdown = root.querySelector('.ccp-dropdown');
                const searchInput = root.querySelector('.ccp-search-input');
                const optionsList = root.querySelector('.ccp-options-list');

                if (!hiddenInput || !triggerBtn || !dropdown || !searchInput || !optionsList) return;

                let highlightedIdx = 0;
                let flatOptions = [];

                function syncTriggerDisplay(codeVal) {
                    const clean = sanitizeCode(codeVal);
                    triggerCodeEl.textContent = '+' + clean;
                }

                // Intercept programmatic .value assignments (e.g. resetAdLeadEntryForm / editAdLeadEntry / form.reset)
                if (nativeValueDescriptor && nativeValueDescriptor.set && nativeValueDescriptor.get) {
                    Object.defineProperty(hiddenInput, 'value', {
                        configurable: true,
                        enumerable: true,
                        get() {
                            return nativeValueDescriptor.get.call(this);
                        },
                        set(v) {
                            const clean = sanitizeCode(v);
                            nativeValueDescriptor.set.call(this, clean);
                            syncTriggerDisplay(clean);
                        }
                    });
                }

                const parentForm = root.closest('form');
                if (parentForm) {
                    parentForm.addEventListener('reset', () => {
                        setTimeout(() => {
                            syncTriggerDisplay(hiddenInput.value || '974');
                        }, 0);
                    });
                }

                function positionDropdown() {
                    const rect = triggerBtn.getBoundingClientRect();
                    const dropWidth = Math.min(340, window.innerWidth - 24);
                    let left = rect.left;
                    if (left + dropWidth > window.innerWidth - 12) {
                        left = Math.max(12, window.innerWidth - dropWidth - 12);
                    }

                    dropdown.style.width = dropWidth + 'px';
                    dropdown.style.left = left + 'px';

                    const spaceBelow = window.innerHeight - rect.bottom - 12;
                    const estimatedHeight = Math.min(310, dropdown.offsetHeight || 300);

                    if (spaceBelow < 210 && rect.top > spaceBelow) {
                        dropdown.style.top = Math.max(12, rect.top - estimatedHeight - 6) + 'px';
                    } else {
                        dropdown.style.top = (rect.bottom + 6) + 'px';
                    }
                }

                function renderOptions() {
                    const currentCode = sanitizeCode(hiddenInput.value);
                    const { pinned, rest, isSearch } = filterCountries(searchInput.value);
                    flatOptions = [...pinned, ...rest];

                    if (!flatOptions.length) {
                        optionsList.innerHTML = '<div class="ccp-empty">No matching country or dial code</div>';
                        highlightedIdx = -1;
                        return;
                    }

                    if (highlightedIdx < 0 || highlightedIdx >= flatOptions.length) {
                        const selectedPos = flatOptions.findIndex(o => o.code === currentCode);
                        highlightedIdx = selectedPos >= 0 ? selectedPos : 0;
                    }

                    let html = '';
                    let idx = 0;

                    const buildRow = (opt, rowIdx) => {
                        const isSel = opt.code === currentCode;
                        const isHi = rowIdx === highlightedIdx;
                        return `<button type="button" class="ccp-option${isSel ? ' is-selected' : ''}${isHi ? ' is-highlighted' : ''}" data-idx="${rowIdx}" data-code="${opt.code}" data-iso="${opt.iso}">
                            <span class="ccp-opt-code">${opt.dial}</span>
                            <span class="ccp-opt-name">${opt.name}</span>
                        </button>`;
                    };

                    if (!isSearch && pinned.length) {
                        for (const p of pinned) {
                            html += buildRow(p, idx++);
                        }
                        html += '<div class="ccp-divider" role="separator"></div>';
                    }

                    for (const r of rest) {
                        html += buildRow(r, idx++);
                    }

                    optionsList.innerHTML = html;
                }

                function updateHighlight(nextIdx) {
                    if (!flatOptions.length) return;
                    highlightedIdx = (nextIdx + flatOptions.length) % flatOptions.length;
                    const rows = optionsList.querySelectorAll('.ccp-option');
                    rows.forEach((r, i) => r.classList.toggle('is-highlighted', i === highlightedIdx));
                    const activeRow = rows[highlightedIdx];
                    if (activeRow) {
                        activeRow.scrollIntoView({ block: 'nearest' });
                    }
                }

                function selectCode(code, focusPhone = true) {
                    const clean = sanitizeCode(code);
                    hiddenInput.value = clean;
                    syncTriggerDisplay(clean);
                    hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    closeDropdown();

                    if (focusPhone) {
                        const group = root.closest('.input-group');
                        const phoneInput = group ? group.querySelector('input[type="text"], input[type="tel"]') : null;
                        if (phoneInput) phoneInput.focus();
                    }
                }

                function openDropdown() {
                    if (activeInstance && activeInstance !== instanceApi) {
                        activeInstance.close();
                    }
                    activeInstance = instanceApi;

                    // Mount inside enclosing Bootstrap .modal when present so Bootstrap 5 FocusTrap
                    // treats the search input as inside the active modal (while keeping fixed viewport coords).
                    const hostContainer = root.closest('.modal') || document.body;
                    if (dropdown.parentElement !== hostContainer) {
                        hostContainer.appendChild(dropdown);
                    }

                    root.classList.add('is-open');
                    triggerBtn.setAttribute('aria-expanded', 'true');
                    searchInput.value = '';
                    highlightedIdx = 0;
                    renderOptions();
                    dropdown.classList.add('is-visible');
                    positionDropdown();

                    requestAnimationFrame(() => {
                        positionDropdown();
                        searchInput.focus({ preventScroll: true });
                    });
                }

                function closeDropdown() {
                    root.classList.remove('is-open');
                    triggerBtn.setAttribute('aria-expanded', 'false');
                    dropdown.classList.remove('is-visible');
                    if (activeInstance === instanceApi) {
                        activeInstance = null;
                    }
                }

                const instanceApi = { open: openDropdown, close: closeDropdown, root, dropdown };

                dropdown.addEventListener('focusin', (e) => {
                    e.stopPropagation();
                });

                dropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                });

                triggerBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (dropdown.classList.contains('is-visible')) {
                        closeDropdown();
                    } else {
                        openDropdown();
                    }
                });

                triggerBtn.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        e.stopPropagation();
                        openDropdown();
                    }
                });

                searchInput.addEventListener('mousedown', (e) => {
                    // Never preventDefault here so native text selection & cursor placement work
                    e.stopPropagation();
                });

                searchInput.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (document.activeElement !== searchInput) {
                        searchInput.focus({ preventScroll: true });
                    }
                });

                searchInput.addEventListener('input', (e) => {
                    e.stopPropagation();
                    highlightedIdx = 0;
                    renderOptions();
                });

                searchInput.addEventListener('keydown', (e) => {
                    e.stopPropagation();
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        updateHighlight(highlightedIdx + 1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        updateHighlight(highlightedIdx - 1);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (flatOptions[highlightedIdx]) {
                            selectCode(flatOptions[highlightedIdx].code, true);
                        }
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeDropdown();
                        triggerBtn.focus();
                    }
                });

                optionsList.addEventListener('mousedown', (e) => {
                    // Prevent blur jitter before click completes
                    e.preventDefault();
                });

                optionsList.addEventListener('click', (e) => {
                    const row = e.target.closest('.ccp-option');
                    if (!row) return;
                    selectCode(row.dataset.code, true);
                });

                // Smart Paste Support on sibling phone input
                const inputGroup = root.closest('.input-group');
                const siblingPhone = inputGroup
                    ? inputGroup.querySelector('input[name="phone_number"], input.lead-phone-number, input[type="tel"], input[type="text"]:not(.ccp-search-input)')
                    : null;

                if (siblingPhone && !siblingPhone.__ccpPasteBound) {
                    siblingPhone.__ccpPasteBound = true;

                    const applySmartIntlParse = (rawText) => {
                        const parsed = matchInternationalPaste(rawText);
                        if (!parsed) return false;
                        hiddenInput.value = parsed.code;
                        syncTriggerDisplay(parsed.code);
                        siblingPhone.value = parsed.local;
                        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                        siblingPhone.dispatchEvent(new Event('input', { bubbles: true }));
                        return true;
                    };

                    siblingPhone.addEventListener('paste', (e) => {
                        const pasted = (e.clipboardData || window.clipboardData)?.getData('text') || '';
                        if (pasted && (pasted.trim().startsWith('+') || pasted.trim().startsWith('00'))) {
                            if (applySmartIntlParse(pasted)) {
                                e.preventDefault();
                            }
                        }
                    });

                    siblingPhone.addEventListener('input', () => {
                        const val = siblingPhone.value || '';
                        if ((val.trim().startsWith('+') || val.trim().startsWith('00')) && val.replace(/\D+/g, '').length >= 8) {
                            applySmartIntlParse(val);
                        }
                    });
                }

                syncTriggerDisplay(hiddenInput.value);
            }

            function bootAllPickers() {
                document.querySelectorAll('.ccp-root').forEach(initPicker);
            }

            document.addEventListener('mousedown', (e) => {
                if (!activeInstance) return;
                if (activeInstance.root.contains(e.target) || activeInstance.dropdown.contains(e.target)) {
                    return;
                }
                activeInstance.close();
            });

            window.addEventListener('resize', () => {
                if (activeInstance) activeInstance.close();
            });

            document.addEventListener('scroll', (e) => {
                if (!activeInstance) return;
                if (activeInstance.dropdown.contains(e.target)) return;
                activeInstance.close();
            }, true);

            document.addEventListener('hidden.bs.modal', () => {
                if (activeInstance) activeInstance.close();
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootAllPickers);
            } else {
                bootAllPickers();
            }

            window.initLaleenCountryPickers = bootAllPickers;
        })();
    </script>
@endonce

<div class="ccp-root" data-ccp-picker>
    <input type="hidden"
        name="{{ $name }}"
        @if($id) id="{{ $id }}" @endif
        class="{{ trim($inputClass . ' ccp-hidden-input') }}"
        value="{{ $cleanInitial }}">
    <button type="button"
        class="ccp-trigger"
        aria-haspopup="listbox"
        aria-expanded="false"
        title="Select country dial code">
        <span class="ccp-trigger-code">+{{ $cleanInitial }}</span>
        <span class="ccp-trigger-caret" aria-hidden="true">&#9662;</span>
    </button>
    <div class="ccp-dropdown" role="listbox">
        <div class="ccp-search-wrap">
            <input type="text"
                class="ccp-search-input"
                placeholder="Search code e.g. 974, +962, 880 or country..."
                autocomplete="off">
        </div>
        <div class="ccp-options-list"></div>
    </div>
</div>
