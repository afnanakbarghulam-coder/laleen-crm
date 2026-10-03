@php
    $selected = $selected ?? [];
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

    .service-interest-pill button {
        background: none;
        border: none;
        color: #e79a91;
        opacity: .7;
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
        max-height: 240px;
        overflow-y: auto;
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

    .service-interest-option .service-interest-check {
        display: none;
        color: #d98f83;
    }

    .service-interest-option.is-selected .service-interest-check {
        display: inline;
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
            <input type="text" id="serviceInterestSearch-{{ $uid }}" placeholder="Search services...">
        </div>
        <div class="service-interest-list" id="serviceInterestList-{{ $uid }}">
            @forelse ($services ?? [] as $serviceName)
                <div class="service-interest-option {{ in_array($serviceName, $selected, true) ? 'is-selected' : '' }}" data-value="{{ $serviceName }}">
                    <span>{{ $serviceName }}</span>
                    <span class="service-interest-check"><i class="bx bx-check"></i></span>
                </div>
            @empty
                <div class="service-interest-empty">No services available.</div>
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
                    pill.className = 'service-interest-pill';
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
                    const matches = option.dataset.value.toLowerCase().includes(normalized);
                    option.style.display = matches ? '' : 'none';
                    if (matches) {
                        anyVisible = true;
                    }
                });

                let emptyEl = list.querySelector('.service-interest-no-results');
                if (!anyVisible) {
                    if (!emptyEl) {
                        emptyEl = document.createElement('div');
                        emptyEl.className = 'service-interest-empty service-interest-no-results';
                        emptyEl.textContent = 'No matching services.';
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

        // This deduplicated script block is emitted at the position of
        // whichever instance of this partial appears FIRST in the document -
        // which may be an edit-modal instance that comes before the Add Lead
        // modal's markup. Running the query immediately would miss any
        // instance that hasn't been parsed into the DOM yet, so wait for the
        // full document instead.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    })();
</script>
@endonce
