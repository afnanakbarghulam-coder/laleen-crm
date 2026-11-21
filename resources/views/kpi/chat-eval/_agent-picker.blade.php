@php
    $selectedIds = collect($selectedAgentIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $selectableCount = $agents->count();
    $allCheckedInitially = $selectableCount > 1 && $agents->every(fn ($a) => in_array((int) $a->id, $selectedIds, true));
    $hasAgentError = $errors->has('agent_ids') || $errors->has('agent_ids.*') || $errors->has('agent_id');
@endphp

<div class="ce-agent-picker {{ $hasAgentError ? 'is-invalid' : '' }}" data-agent-picker>
    <div class="ce-agent-picker-top">
        <label class="form-label mb-0">Agent(s) <span class="text-danger">*</span></label>
        <div class="ce-agent-picker-actions">
            <span class="ce-agent-summary" data-agent-summary></span>
            @if ($selectableCount > 1)
                <label class="ce-all-agents-toggle {{ $allCheckedInitially ? 'is-active' : '' }}" data-all-agents-label>
                    <input type="checkbox" data-all-agents-checkbox @checked($allCheckedInitially)>
                    <i class="bx bx-group"></i>
                    <span>All Agents</span>
                </label>
            @endif
        </div>
    </div>

    <div class="ce-agent-pills" role="group" aria-label="Select evaluated agents">
        @forelse ($agents as $agent)
            @php $checked = in_array((int) $agent->id, $selectedIds, true); @endphp
            <label class="ce-agent-pill {{ $checked ? 'is-selected' : '' }}" data-agent-pill>
                <input
                    type="checkbox"
                    name="agent_ids[]"
                    value="{{ $agent->id }}"
                    data-agent-checkbox
                    data-agent-name="{{ $agent->name }}"
                    @checked($checked)
                >
                <span class="ce-agent-check" aria-hidden="true">
                    <i class="bx bx-check"></i>
                </span>
                <span class="ce-agent-name">{{ $agent->name }}</span>
            </label>
        @empty
            <div class="text-muted small py-1">No selectable chat evaluation agents found.</div>
        @endforelse
    </div>

    @if ($hasAgentError)
        <div class="ce-agent-error">
            {{ $errors->first('agent_ids') ?: ($errors->first('agent_ids.*') ?: $errors->first('agent_id')) }}
        </div>
    @else
        <div class="ce-agent-error d-none" data-agent-client-error>
            Please select at least one agent to evaluate.
        </div>
    @endif
</div>

<style>
    .ce-agent-picker {
        background: var(--luxe-surface-2);
        border: 1px solid var(--luxe-border);
        border-radius: var(--luxe-radius-sm);
        padding: 12px 14px;
        transition: border-color .15s ease;
    }

    .ce-agent-picker.is-invalid {
        border-color: rgba(220, 82, 74, .7);
    }

    .ce-agent-picker-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 10px;
    }

    .ce-agent-picker-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .ce-agent-summary {
        font-size: 11.5px;
        color: var(--luxe-muted);
        font-weight: 500;
    }

    .ce-all-agents-toggle {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 999px;
        border: 1px solid var(--luxe-border-strong);
        background: var(--luxe-surface);
        color: var(--luxe-body);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }

    .ce-all-agents-toggle input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        pointer-events: none;
    }

    .ce-all-agents-toggle:hover {
        border-color: var(--luxe-accent);
        color: var(--luxe-ink);
    }

    .ce-all-agents-toggle.is-active {
        background: rgba(200, 157, 124, .18);
        border-color: var(--luxe-accent);
        color: var(--luxe-accent);
    }

    .ce-agent-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .ce-agent-pill {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 13px;
        border-radius: 999px;
        border: 1px solid var(--luxe-border-strong);
        background: var(--luxe-surface);
        color: var(--luxe-muted);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }

    .ce-agent-pill input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        pointer-events: none;
    }

    .ce-agent-pill:hover {
        border-color: var(--luxe-accent);
        color: var(--luxe-ink);
    }

    .ce-agent-check {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 4px;
        border: 1px solid var(--luxe-border-strong);
        background: transparent;
        color: transparent;
        font-size: 12px;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }

    .ce-agent-pill.is-selected {
        background: rgba(200, 157, 124, .16);
        border-color: var(--luxe-accent);
        color: var(--luxe-ink);
    }

    .ce-agent-pill.is-selected .ce-agent-check {
        background: var(--luxe-accent);
        border-color: var(--luxe-accent);
        color: #1c1512;
    }

    .ce-agent-error {
        margin-top: 8px;
        font-size: 12px;
        color: #e2a299;
        font-weight: 500;
    }
</style>

<script>
    (function () {
        document.querySelectorAll('[data-agent-picker]').forEach(function (picker) {
            var checkboxes = Array.from(picker.querySelectorAll('[data-agent-checkbox]'));
            var allCheckbox = picker.querySelector('[data-all-agents-checkbox]');
            var allLabel = picker.querySelector('[data-all-agents-label]');
            var summaryEl = picker.querySelector('[data-agent-summary]');
            var clientErrorEl = picker.querySelector('[data-agent-client-error]');
            var form = picker.closest('form');

            function syncUi() {
                var selected = [];

                checkboxes.forEach(function (cb) {
                    var pill = cb.closest('[data-agent-pill]');
                    if (pill) {
                        pill.classList.toggle('is-selected', cb.checked);
                    }
                    if (cb.checked) {
                        selected.push(cb.dataset.agentName || '');
                    }
                });

                var allChecked = checkboxes.length > 1 && selected.length === checkboxes.length;

                if (allCheckbox) {
                    allCheckbox.checked = allChecked;
                }
                if (allLabel) {
                    allLabel.classList.toggle('is-active', allChecked);
                }

                if (summaryEl) {
                    if (selected.length === 0) {
                        summaryEl.textContent = 'Select at least 1 agent';
                    } else if (allChecked) {
                        summaryEl.textContent = 'All Agents (' + selected.join(', ') + ')';
                    } else if (selected.length === 1) {
                        summaryEl.textContent = selected[0];
                    } else {
                        var sorted = selected.slice().sort(function (a, b) { return a.localeCompare(b); });
                        summaryEl.textContent = sorted.join(' + ');
                    }
                }

                if (selected.length > 0) {
                    picker.classList.remove('is-invalid');
                    if (clientErrorEl) {
                        clientErrorEl.classList.add('d-none');
                    }
                }
            }

            if (allCheckbox) {
                allCheckbox.addEventListener('change', function () {
                    var targetState = allCheckbox.checked;
                    checkboxes.forEach(function (cb) {
                        cb.checked = targetState;
                    });
                    syncUi();
                });
            }

            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', syncUi);
            });

            if (form) {
                form.addEventListener('submit', function (e) {
                    var anyChecked = checkboxes.some(function (cb) { return cb.checked; });
                    if (!anyChecked) {
                        e.preventDefault();
                        picker.classList.add('is-invalid');
                        if (clientErrorEl) {
                            clientErrorEl.classList.remove('d-none');
                        }
                        picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }

            syncUi();
        });
    })();
</script>
