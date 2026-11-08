@php
    $selected = is_array($selected ?? null) ? array_values(array_filter($selected)) : [];
    $combosList = collect($serviceCombos ?? []);
    $servicesList = collect($services ?? []);
@endphp

@once
<style>
    .service-interest-multiselect {
        position: relative;
    }

    .service-interest-display {
        min-height: 42px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        background-color: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.3);
        border-radius: 6px;
        padding: 6px 10px;
        color: #e79a91;
    }

    .service-interest-display:focus,
    .service-interest-display.is-open {
        outline: none;
        border-color: #d98f83;
        box-shadow: 0 0 0 3px rgba(217, 143, 131, .15);
    }

    .service-interest-display.is-invalid {
        border-color: #a8524a;
        box-shadow: 0 0 0 3px rgba(168, 82, 74, .2);
    }

    .service-interest-placeholder {
        color: #8d7f79;
        font-size: 14px;
    }

    .service-interest-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(217, 143, 131, 0.16);
        color: #e79a91;
        border-radius: 999px;
        padding: 3px 6px 3px 12px;
        font-size: 12.5px;
        font-weight: 600;
        white-space: nowrap;
    }

    .service-interest-pill.is-combo-pill {
        background: rgba(201, 166, 107, 0.22);
        color: #f3d9a4;
        border: 1px solid rgba(201, 166, 107, 0.35);
    }

    .service-interest-pill button {
        background: none;
        border: none;
        color: inherit;
        opacity: .75;
        line-height: 1;
        padding: 0 2px;
        font-size: 15px;
    }

    .service-interest-pill button:hover {
        opacity: 1;
        color: #a8524a;
    }

    .service-interest-panel {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        width: 100%;
        min-width: 280px;
        z-index: 1070;
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.3);
        border-radius: 10px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .35);
        overflow: hidden;
    }

    .service-interest-panel.is-open {
        display: block;
    }

    .service-interest-search-wrap {
        position: sticky;
        top: 0;
        background: #241e1c;
        padding: 10px;
        border-bottom: 1px solid rgba(217, 143, 131, 0.16);
    }

    .service-interest-search-wrap input {
        width: 100%;
        background-color: #1a1513;
        border: 1px solid rgba(217, 143, 131, 0.2);
        border-radius: 6px;
        padding: 6px 10px;
        color: #e79a91;
        font-size: 13.5px;
    }

    .service-interest-search-wrap input:focus {
        outline: none;
        border-color: #d98f83;
    }

    .service-interest-list {
        max-height: 280px;
        overflow-y: auto;
    }

    .service-interest-section-header {
        padding: 7px 14px 5px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #c9a66b;
        background: rgba(201, 166, 107, 0.08);
        border-top: 1px solid rgba(201, 166, 107, 0.16);
        border-bottom: 1px solid rgba(201, 166, 107, 0.16);
        user-select: none;
    }

    .service-interest-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 14px;
        font-size: 13.5px;
        color: #cbb8b0;
        cursor: pointer;
    }

    .service-interest-option:hover {
        background: rgba(217, 143, 131, 0.08);
    }

    .service-interest-option.is-selected {
        color: #e79a91;
        font-weight: 600;
        background: rgba(217, 143, 131, 0.1);
    }

    .service-interest-option.is-combo-option.is-selected {
        color: #f3d9a4;
        background: rgba(201, 166, 107, 0.14);
    }

    .service-interest-option .service-interest-check {
        display: none;
        color: #d98f83;
    }

    .service-interest-option.is-selected .service-interest-check {
        display: inline;
    }

    .service-interest-combo-sub {
        display: block;
        font-size: 11px;
        font-weight: 400;
        color: #9b8b84;
        margin-top: 1px;
    }

    .service-interest-empty {
        padding: 14px;
        text-align: center;
        font-size: 13px;
        color: #8d7f79;
    }

    .service-interest-footer {
        padding: 8px 10px;
        text-align: right;
        border-top: 1px solid rgba(217, 143, 131, 0.16);
    }

    .service-interest-footer button {
        background: none;
        border: none;
        color: #c9a39a;
        font-size: 12.5px;
        font-weight: 600;
        padding: 4px 8px;
    }

    .service-interest-footer button:hover {
        color: #e79a91;
        text-decoration: underline;
    }
</style>
@endonce

<div class="service-interest-multiselect" data-uid="{{ $uid }}">
    <label class="form-label">Service Interest <span class="text-danger">*</span></label>

    <div class="service-interest-display" id="serviceInterestDisplay-{{ $uid }}" tabindex="0">
        <span class="service-interest-placeholder" id="serviceInterestPlaceholder-{{ $uid }}">-- Select Service(s) --</span>
    </div>

    <div class="service-interest-hidden-inputs" id="serviceInterestHiddenInputs-{{ $uid }}">
        @foreach ($selected as $service)
            <input type="hidden" name="service_interest[]" value="{{ $service }}">
        @endforeach
    </div>

    <div class="service-interest-panel" id="serviceInterestPanel-{{ $uid }}">
        <div class="service-interest-search-wrap">
            <input type="text" id="serviceInterestSearch-{{ $uid }}" placeholder="Search combos or services...">
        </div>
        <div class="service-interest-list" id="serviceInterestList-{{ $uid }}">
            {{-- Section 1: 🎁 Service Combos (always visible and selectable alongside individual services) --}}
            <div class="service-interest-section-header" data-section-header="combos">🎁 Service Combos</div>
            @forelse ($combosList as $combo)
                @php
                    $comboName = is_object($combo) ? (string) $combo->name : (string) ($combo['name'] ?? $combo);
                    $comboPrice = is_object($combo) ? (float) ($combo->price ?? 0) : (float) ($combo['price'] ?? 0);
                    $formattedPrice = rtrim(rtrim(number_format($comboPrice, 2, '.', ''), '0'), '.');
                    $comboLabel = "[Combo] {$comboName}" . ($comboPrice > 0 ? " ({$formattedPrice} QAR)" : '');
                    $shortComboAlias = "[Combo] {$comboName}";
                    $includedNames = (is_object($combo) && isset($combo->services) && $combo->services instanceof \Illuminate\Support\Collection)
                        ? $combo->services->pluck('name')->filter()->values()->all()
                        : [];

                    // Support matching whether stored as full "[Combo] Name (Price QAR)", "[Combo] Name", or raw "Name"
                    $matchedSelectedValue = null;
                    foreach ($selected as $selItem) {
                        $trimmedSel = trim((string) $selItem);
                        if (
                            strcasecmp($trimmedSel, $comboLabel) === 0
                            || strcasecmp($trimmedSel, $shortComboAlias) === 0
                            || strcasecmp($trimmedSel, $comboName) === 0
                        ) {
                            $matchedSelectedValue = $trimmedSel;
                            break;
                        }
                    }
                    $effectiveValue = $matchedSelectedValue ?: $comboLabel;
                @endphp
                <div class="service-interest-option is-combo-option {{ $matchedSelectedValue !== null ? 'is-selected' : '' }}"
                    data-section="combos"
                    data-type="combo"
                    data-combo-name="{{ $comboName }}"
                    data-value="{{ $effectiveValue }}">
                    <div>
                        <span>{{ $comboLabel }}</span>
                        @if (!empty($includedNames))
                            <small class="service-interest-combo-sub">Includes: {{ implode(', ', $includedNames) }}</small>
                        @endif
                    </div>
                    <span class="service-interest-check"><i class="bx bx-check"></i></span>
                </div>
            @empty
                <div class="service-interest-empty service-interest-combos-empty" data-section="combos">No combos configured yet.</div>
            @endforelse

            {{-- Section 2: ✂️ Individual Services --}}
            <div class="service-interest-section-header" data-section-header="services">✂️ Individual Services</div>
            @forelse ($servicesList as $serviceItem)
                @php
                    $serviceName = is_object($serviceItem) ? (string) $serviceItem->name : (string) $serviceItem;
                @endphp
                <div class="service-interest-option {{ in_array($serviceName, $selected, true) ? 'is-selected' : '' }}"
                    data-section="services"
                    data-type="service"
                    data-value="{{ $serviceName }}">
                    <span>{{ $serviceName }}</span>
                    <span class="service-interest-check"><i class="bx bx-check"></i></span>
                </div>
            @empty
                <div class="service-interest-empty" data-section="services">No services available.</div>
            @endforelse
        </div>
        <div class="service-interest-footer">
            <button type="button" class="service-interest-close">Close</button>
        </div>
    </div>
</div>

@once
<script>
    (function () {
        function initServiceInterestMultiselect(container) {
            const uid = container.dataset.uid;
            const display = document.getElementById('serviceInterestDisplay-' + uid);
            const placeholder = document.getElementById('serviceInterestPlaceholder-' + uid);
            const hiddenWrap = document.getElementById('serviceInterestHiddenInputs-' + uid);
            const panel = document.getElementById('serviceInterestPanel-' + uid);
            const search = document.getElementById('serviceInterestSearch-' + uid);
            const list = document.getElementById('serviceInterestList-' + uid);
            const closeBtn = panel.querySelector('.service-interest-close');

            function getSelected() {
                return Array.from(hiddenWrap.querySelectorAll('input[name="service_interest[]"]')).map(function (input) {
                    return input.value;
                });
            }

            function isSelected(value) {
                return getSelected().includes(value);
            }

            function renderPills() {
                const selected = getSelected();

                Array.from(display.querySelectorAll('.service-interest-pill')).forEach(function (pill) {
                    pill.remove();
                });

                if (selected.length === 0) {
                    placeholder.style.display = '';
                    return;
                }

                placeholder.style.display = 'none';

                selected.forEach(function (value) {
                    const pill = document.createElement('span');
                    const isCombo = value.toLowerCase().startsWith('[combo]') ||
                        Array.from(list.querySelectorAll('.service-interest-option.is-combo-option')).some(function (opt) {
                            return opt.dataset.value === value || opt.dataset.comboName === value;
                        });
                    pill.className = 'service-interest-pill' + (isCombo ? ' is-combo-pill' : '');
                    pill.innerHTML = '<span></span><button type="button" aria-label="Remove">&times;</button>';
                    pill.querySelector('span').textContent = value;
                    pill.querySelector('button').addEventListener('click', function (e) {
                        e.stopPropagation();
                        removeValue(value);
                    });
                    display.insertBefore(pill, placeholder);
                });
            }

            function syncListSelectedState() {
                list.querySelectorAll('.service-interest-option').forEach(function (option) {
                    option.classList.toggle('is-selected', isSelected(option.dataset.value));
                });
            }

            function addValue(value) {
                if (isSelected(value)) {
                    return;
                }
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'service_interest[]';
                input.value = value;
                hiddenWrap.appendChild(input);
                renderPills();
                syncListSelectedState();
                display.classList.remove('is-invalid');
            }

            function removeValue(value) {
                hiddenWrap.querySelectorAll('input[name="service_interest[]"]').forEach(function (input) {
                    if (input.value === value) {
                        input.remove();
                    }
                });
                renderPills();
                syncListSelectedState();
            }

            function openPanel() {
                panel.classList.add('is-open');
                display.classList.add('is-open');
                search.value = '';
                filterList('');
                search.focus();
            }

            function closePanel() {
                panel.classList.remove('is-open');
                display.classList.remove('is-open');
            }

            function filterList(term) {
                const normalized = term.trim().toLowerCase();
                let anyVisible = false;

                list.querySelectorAll('.service-interest-option').forEach(function (option) {
                    const hay = (option.dataset.value + ' ' + option.textContent).toLowerCase();
                    const matches = normalized === '' || hay.includes(normalized);
                    option.style.display = matches ? '' : 'none';
                    if (matches) {
                        anyVisible = true;
                    }
                });

                // Always keep section headers visible when searching is blank or when options in that section match
                list.querySelectorAll('[data-section-header]').forEach(function (header) {
                    const sec = header.dataset.sectionHeader;
                    if (sec === 'combos') {
                        // Always keep 🎁 Service Combos section visible when search is empty or matches a combo
                        const hasMatchingCombo = normalized === '' || Array.from(list.querySelectorAll('.service-interest-option[data-section="combos"]')).some(function (o) {
                            return o.style.display !== 'none';
                        });
                        header.style.display = hasMatchingCombo ? '' : 'none';
                    } else {
                        const hasMatchingService = normalized === '' || Array.from(list.querySelectorAll('.service-interest-option[data-section="services"]')).some(function (o) {
                            return o.style.display !== 'none';
                        });
                        header.style.display = hasMatchingService ? '' : 'none';
                    }
                });

                let emptyEl = list.querySelector('.service-interest-no-results');
                if (!anyVisible) {
                    if (!emptyEl) {
                        emptyEl = document.createElement('div');
                        emptyEl.className = 'service-interest-empty service-interest-no-results';
                        emptyEl.textContent = 'No matching combos or services.';
                        list.appendChild(emptyEl);
                    }
                } else if (emptyEl) {
                    emptyEl.remove();
                }
            }

            display.addEventListener('click', function () {
                if (panel.classList.contains('is-open')) {
                    closePanel();
                } else {
                    openPanel();
                }
            });

            display.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    display.click();
                }
            });

            search.addEventListener('input', function () {
                filterList(search.value);
            });

            search.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            list.addEventListener('click', function (e) {
                const option = e.target.closest('.service-interest-option');
                if (!option) {
                    return;
                }
                const value = option.dataset.value;
                if (isSelected(value)) {
                    removeValue(value);
                } else {
                    addValue(value);
                }
            });

            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                closePanel();
            });

            document.addEventListener('click', function (e) {
                if (!container.contains(e.target)) {
                    closePanel();
                }
            });

            const form = container.closest('form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    if (getSelected().length === 0) {
                        e.preventDefault();
                        display.classList.add('is-invalid');
                        closePanel();
                        display.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }

            renderPills();
        }

        function initAll() {
            document.querySelectorAll('.service-interest-multiselect').forEach(initServiceInterestMultiselect);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    })();
</script>
@endonce
