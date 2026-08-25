<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shiftModalTitle">Edit shift schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3" id="shiftStaffPickerWrap">
                    <label class="form-label">Team member</label>
                    <select id="shiftStaffPicker" class="form-select">
                        <option value="">Select a team member</option>
                        @foreach ($allStaff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="shiftModalBody" class="d-none">
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item"><button type="button" class="nav-link active" data-shift-tab="working">Working hours</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-shift-tab="timeoff">Time off</button></li>
                    </ul>

                    <!-- WORKING HOURS -->
                    <div class="shift-tab-pane active" id="shiftTab-working">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Repeats</label>
                                <select id="shiftRepeat" class="form-select">
                                    <option value="weekly">Weekly</option>
                                    <option value="does_not_repeat">Does not repeat</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start date</label>
                                <input type="date" id="shiftStartDate" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End date</label>
                                <input type="date" id="shiftEndDate" class="form-control" disabled>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="shiftNoEndDate" checked>
                                    <label class="form-check-label small" for="shiftNoEndDate">No end date</label>
                                </div>
                            </div>
                        </div>

                        <div id="shiftDaysWrap">
                            @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $i => $day)
                                <div class="shift-day-row" data-day="{{ $i }}">
                                    <div class="shift-day-header">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input day-toggle" type="checkbox" id="dayToggle{{ $i }}" data-day="{{ $i }}">
                                            <label class="form-check-label fw-semibold" for="dayToggle{{ $i }}">{{ $day }}</label>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-link add-block-btn d-none" data-day="{{ $i }}">+ Add block</button>
                                    </div>
                                    <div class="shift-blocks" id="shiftBlocks{{ $i }}"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- TIME OFF -->
                    <div class="shift-tab-pane" id="shiftTab-timeoff">
                        <p class="text-muted small">Recording time off does not change this team member's permanent weekly schedule.</p>

                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Start date</label>
                                <input type="date" id="toStartDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">End date</label>
                                <input type="date" id="toEndDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Reason</label>
                                <select id="toReason" class="form-select form-select-sm">
                                    <option value="on-leave">Annual leave</option>
                                    <option value="sick">Sick leave</option>
                                    <option value="unpaid">Unpaid leave</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-sm btn-dark w-100" id="toAddBtn">+ Add time off</button>
                            </div>
                            <div class="col-12">
                                <input type="text" id="toNotes" class="form-control form-control-sm" placeholder="Notes (optional)">
                            </div>
                        </div>

                        <div id="timeOffList"></div>
                        <div id="timeOffEmpty" class="text-muted small">No time off recorded.</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-dark" id="shiftSaveBtn" disabled>Save working hours</button>
            </div>
        </div>
    </div>
</div>

<template id="shiftBlockTemplate">
    <div class="shift-block-row">
        <input type="time" class="form-control form-control-sm block-start">
        <span>-</span>
        <input type="time" class="form-control form-control-sm block-end">
        <button type="button" class="btn btn-sm btn-outline-danger remove-block-btn"><i class="bx bx-x"></i></button>
    </div>
</template>

<script>
    (function() {
        const modalEl = document.getElementById('shiftModal');
        let currentStaffId = null;

        function getBsModal() {
            return bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        function dayBlocksContainer(day) {
            return document.getElementById('shiftBlocks' + day);
        }

        function addBlockRow(day, start, end) {
            const tpl = document.getElementById('shiftBlockTemplate').content.cloneNode(true);
            if (start) tpl.querySelector('.block-start').value = start;
            if (end) tpl.querySelector('.block-end').value = end;
            tpl.querySelector('.remove-block-btn').addEventListener('click', function() {
                this.closest('.shift-block-row').remove();
            });
            dayBlocksContainer(day).appendChild(tpl);
        }

        document.querySelectorAll('.add-block-btn').forEach(btn => {
            btn.addEventListener('click', () => addBlockRow(btn.dataset.day, '', ''));
        });

        document.querySelectorAll('.day-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const day = this.dataset.day;
                const container = dayBlocksContainer(day);
                const addBtn = document.querySelector(`.add-block-btn[data-day="${day}"]`);
                container.querySelectorAll('input').forEach(i => i.disabled = !this.checked);
                addBtn.classList.toggle('d-none', !this.checked);
                if (this.checked && container.children.length === 0) {
                    addBlockRow(day, '09:00', '17:00');
                }
                if (!this.checked) {
                    container.innerHTML = '';
                }
            });
        });

        document.getElementById('shiftNoEndDate').addEventListener('change', function() {
            document.getElementById('shiftEndDate').disabled = this.checked;
            if (this.checked) document.getElementById('shiftEndDate').value = '';
        });

        modalEl.querySelectorAll('[data-shift-tab]').forEach(tabBtn => {
            tabBtn.addEventListener('click', () => {
                modalEl.querySelectorAll('[data-shift-tab]').forEach(t => t.classList.remove('active'));
                modalEl.querySelectorAll('.shift-tab-pane').forEach(p => p.classList.remove('active'));
                tabBtn.classList.add('active');
                document.getElementById('shiftTab-' + tabBtn.dataset.shiftTab).classList.add('active');
            });
        });

        function resetDays() {
            for (let d = 0; d < 7; d++) {
                document.getElementById('dayToggle' + d).checked = false;
                document.querySelector(`.add-block-btn[data-day="${d}"]`).classList.add('d-none');
                dayBlocksContainer(d).innerHTML = '';
            }
        }

        function renderTimeOffs(timeOffs) {
            const list = document.getElementById('timeOffList');
            list.innerHTML = '';
            document.getElementById('timeOffEmpty').classList.toggle('d-none', timeOffs.length > 0);

            const reasonLabels = {'on-leave': 'Annual leave', 'sick': 'Sick leave', 'unpaid': 'Unpaid leave', 'other': 'Other'};

            timeOffs.forEach(t => {
                const row = document.createElement('div');
                row.className = 'timeoff-row';
                row.innerHTML = `
                    <div>
                        <span class="badge ${t.reason}">${reasonLabels[t.reason] || t.reason}</span>
                        <span class="ms-2">${t.start_date} &rarr; ${t.end_date}</span>
                        ${t.notes ? `<div class="text-muted small mt-1">${t.notes}</div>` : ''}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-timeoff-btn"><i class="bx bx-trash"></i></button>
                `;
                row.querySelector('.delete-timeoff-btn').addEventListener('click', () => deleteTimeOff(t.id, row));
                list.appendChild(row);
            });
        }

        function deleteTimeOff(id, row) {
            if (!confirm('Remove this time off entry?')) return;
            fetch(`/scheduled-shifts/time-off/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            }).then(r => r.json()).then(data => {
                if (data.success) row.remove();
            });
        }

        document.getElementById('toAddBtn').addEventListener('click', function() {
            if (!currentStaffId) return;
            const startDate = document.getElementById('toStartDate').value;
            const endDate = document.getElementById('toEndDate').value;
            if (!startDate || !endDate) {
                alert('Please choose a start and end date.');
                return;
            }
            fetch(`/scheduled-shifts/${currentStaffId}/time-off`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate,
                    reason: document.getElementById('toReason').value,
                    notes: document.getElementById('toNotes').value,
                }),
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    window.SHIFT_CONFIGS[currentStaffId].time_offs.unshift(data.timeOff);
                    renderTimeOffs(window.SHIFT_CONFIGS[currentStaffId].time_offs);
                    document.getElementById('toStartDate').value = '';
                    document.getElementById('toEndDate').value = '';
                    document.getElementById('toNotes').value = '';
                }
            });
        });

        window.openShiftModal = function(staffId) {
            currentStaffId = staffId;
            resetDays();
            document.getElementById('shiftRepeat').value = 'weekly';
            document.getElementById('shiftStartDate').value = new Date().toISOString().slice(0, 10);
            document.getElementById('shiftEndDate').value = '';
            document.getElementById('shiftEndDate').disabled = true;
            document.getElementById('shiftNoEndDate').checked = true;
            renderTimeOffs([]);

            const picker = document.getElementById('shiftStaffPicker');

            if (staffId) {
                document.getElementById('shiftStaffPickerWrap').classList.add('d-none');
                document.getElementById('shiftModalBody').classList.remove('d-none');
                document.getElementById('shiftSaveBtn').disabled = false;
                picker.value = staffId;
                document.getElementById('shiftModalTitle').innerText = 'Edit shift schedule — ' + window.SHIFT_CONFIGS[staffId].name;
                applyConfig(window.SHIFT_CONFIGS[staffId]);
            } else {
                document.getElementById('shiftStaffPickerWrap').classList.remove('d-none');
                document.getElementById('shiftModalBody').classList.add('d-none');
                document.getElementById('shiftSaveBtn').disabled = true;
                picker.value = '';
                document.getElementById('shiftModalTitle').innerText = 'Add shift schedule';
            }

            getBsModal().show();
        };

        function applyConfig(config) {
            renderTimeOffs(config.time_offs || []);

            if (!config.pattern) return;

            document.getElementById('shiftRepeat').value = config.pattern.repeat_frequency;
            document.getElementById('shiftStartDate').value = config.pattern.start_date;
            if (config.pattern.end_date) {
                document.getElementById('shiftNoEndDate').checked = false;
                document.getElementById('shiftEndDate').disabled = false;
                document.getElementById('shiftEndDate').value = config.pattern.end_date;
            }

            const byDay = {};
            (config.pattern.blocks || []).forEach(b => {
                byDay[b.day_of_week] = byDay[b.day_of_week] || [];
                byDay[b.day_of_week].push(b);
            });

            Object.keys(byDay).forEach(day => {
                const toggle = document.getElementById('dayToggle' + day);
                toggle.checked = true;
                document.querySelector(`.add-block-btn[data-day="${day}"]`).classList.remove('d-none');
                byDay[day].forEach(b => addBlockRow(day, b.start_time, b.end_time));
            });
        }

        document.getElementById('shiftStaffPicker').addEventListener('change', function() {
            if (!this.value) {
                document.getElementById('shiftModalBody').classList.add('d-none');
                document.getElementById('shiftSaveBtn').disabled = true;
                currentStaffId = null;
                return;
            }
            currentStaffId = Number(this.value);
            resetDays();
            document.getElementById('shiftRepeat').value = 'weekly';
            document.getElementById('shiftStartDate').value = new Date().toISOString().slice(0, 10);
            document.getElementById('shiftEndDate').value = '';
            document.getElementById('shiftEndDate').disabled = true;
            document.getElementById('shiftNoEndDate').checked = true;
            document.getElementById('shiftModalBody').classList.remove('d-none');
            document.getElementById('shiftSaveBtn').disabled = false;
            applyConfig(window.SHIFT_CONFIGS[currentStaffId]);
        });

        document.getElementById('shiftSaveBtn').addEventListener('click', function() {
            if (!currentStaffId) return;

            const blocks = {};
            for (let d = 0; d < 7; d++) {
                if (!document.getElementById('dayToggle' + d).checked) continue;
                const rows = dayBlocksContainer(d).querySelectorAll('.shift-block-row');
                blocks[d] = [];
                rows.forEach(row => {
                    const start = row.querySelector('.block-start').value;
                    const end = row.querySelector('.block-end').value;
                    if (start && end) blocks[d].push({ start, end });
                });
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/scheduled-shifts/${currentStaffId}/pattern`;

            const fields = {
                _token: '{{ csrf_token() }}',
                repeat_frequency: document.getElementById('shiftRepeat').value,
                start_date: document.getElementById('shiftStartDate').value,
                end_date: document.getElementById('shiftEndDate').value,
                no_end_date: document.getElementById('shiftNoEndDate').checked ? '1' : '0',
            };

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            Object.entries(blocks).forEach(([day, dayBlocks]) => {
                dayBlocks.forEach((b, i) => {
                    const startInput = document.createElement('input');
                    startInput.type = 'hidden';
                    startInput.name = `blocks[${day}][${i}][start]`;
                    startInput.value = b.start;
                    form.appendChild(startInput);

                    const endInput = document.createElement('input');
                    endInput.type = 'hidden';
                    endInput.name = `blocks[${day}][${i}][end]`;
                    endInput.value = b.end;
                    form.appendChild(endInput);
                });
            });

            document.body.appendChild(form);
            form.submit();
        });
    })();
</script>
