@extends('layouts.app')
@section('title', 'Salon Staff Evaluation')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Salon Staff Evaluation</h4>
            <p>Weekly ROI-focused performance scoring — Wakrah (Rhea) &amp; Old Airport (Ahana).</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('kpi.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to KPIs</a>
            @moduleEdit('kpis')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newEvaluationModal">
                    <i class="bx bx-plus me-1"></i> New Evaluation
                </button>
            @endmoduleEdit
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ($weeklyStatus as $status)
            <div class="col-md-4">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">{{ $status['label'] }} ({{ $status['evaluator_name'] }})</div>
                    <div class="kpi-stat-value">{{ $status['evaluated'] }} / {{ $status['total'] }}</div>
                    <div class="kpi-stat-sub d-flex align-items-center gap-2">
                        Evaluated This Week
                        @if ($status['total'] > 0)
                            <span class="kpi-badge {{ $status['complete'] ? 'kpi-badge-green' : 'kpi-badge-amber' }}">
                                {{ $status['complete'] ? 'Fully Evaluated' : ($status['total'] - $status['evaluated']) . ' Remaining' }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-md-4">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Salon Weakest Metric</div>
                @if ($weakestMetric)
                    <div class="kpi-stat-value" style="font-size:19px;">{{ $weakestMetric['title'] }}</div>
                    <div class="kpi-stat-sub">{{ $weakestMetric['count'] }} of {{ $weakestMetric['total_evaluations'] }} evaluations scored a 1 here this week</div>
                @else
                    <div class="kpi-stat-value" style="font-size:19px;">&mdash;</div>
                    <div class="kpi-stat-sub">No evaluations logged yet this week</div>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('kpi.evaluations.index', array_filter(['period' => 'current', 'branch' => $branchFilter])) }}" class="btn btn-sm {{ $period === 'current' ? 'btn-dark' : 'btn-outline-secondary' }}">This Week</a>
        <a href="{{ route('kpi.evaluations.index', array_filter(['period' => 'previous', 'branch' => $branchFilter])) }}" class="btn btn-sm {{ $period === 'previous' ? 'btn-dark' : 'btn-outline-secondary' }}">Previous Weeks</a>

        <select id="dashboardBranchFilter" class="form-select form-select-sm ms-md-2" style="max-width: 200px;">
            <option value="" @selected(!$branchFilter)>All Branches</option>
            @foreach ($branches as $key => $label)
                <option value="{{ $key }}" @selected($branchFilter === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff Member</th>
                        <th>Branch</th>
                        <th>Evaluated By</th>
                        <th class="text-end">Overall Score</th>
                        <th>Leader Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evaluations as $evaluation)
                        @php
                            $avg = (float) $evaluation->overall_average;
                            $badge = $avg >= 4 ? 'kpi-badge-green' : ($avg >= 3 ? 'kpi-badge-amber' : 'kpi-badge-red');
                            $branchLabel = $branches[$evaluation->branch] ?? $evaluation->branch;
                            $viewData = [
                                'staff' => $evaluation->staff->name ?? '—',
                                'branch' => $branchLabel,
                                'evaluator' => $evaluation->evaluator->name ?? '—',
                                'date' => $evaluation->evaluation_date->format('d M Y'),
                                'average' => number_format($avg, 2),
                                'notes' => $evaluation->leader_notes,
                                'scores' => collect(\App\Models\KpiStaffEvaluation::SCORE_FIELDS)->mapWithKeys(
                                    fn ($field) => [$field => (int) $evaluation->$field]
                                ),
                            ];
                        @endphp
                        <tr>
                            <td>{{ $evaluation->evaluation_date->format('d M Y') }}</td>
                            <td>{{ $evaluation->staff->name ?? '—' }}</td>
                            <td><span class="kpi-badge kpi-badge-gray">{{ $branchLabel }}</span></td>
                            <td>{{ $evaluation->evaluator->name ?? '—' }}</td>
                            <td class="text-end"><span class="kpi-badge {{ $badge }}">{{ number_format($avg, 2) }} / 5</span></td>
                            <td class="text-muted small">{{ \Illuminate\Support\Str::limit($evaluation->leader_notes, 40) ?: '—' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary se-view-btn" data-evaluation='@json($viewData)'>
                                    👁️ View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No evaluations {{ $period === 'current' ? 'logged this week' : 'found for previous weeks' }} yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $evaluations->links() }}</div>

    <div class="modal fade" id="newEvaluationModal" tabindex="-1" @if ($errors->any()) data-autoshow="1" @endif>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('kpi.evaluations.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">New Staff Evaluation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Branch</label>
                                <select name="branch" id="evalBranchSelect" class="form-select" required>
                                    <option value="">Select branch&hellip;</option>
                                    @foreach ($branches as $key => $label)
                                        <option value="{{ $key }}" @selected(old('branch', $defaultBranch) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Staff Member</label>
                                <select name="staff_id" id="evalStaffSelect" class="form-select" required>
                                    <option value="">Select a branch first&hellip;</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Evaluation Date</label>
                                <input type="date" name="evaluation_date" class="form-control" value="{{ old('evaluation_date', now()->format('Y-m-d')) }}" required>
                            </div>
                        </div>

                        @foreach ($rubric as $field => $block)
                            <div class="se-score-block">
                                <h6 class="se-score-title">{{ $block['title'] }}</h6>

                                <div class="se-choice-row" role="radiogroup" aria-label="{{ $block['title'] }} score">
                                    <div class="se-choice se-choice-1">
                                        <input type="radio" name="{{ $field }}" id="{{ $field }}-1" value="1" @checked(old($field) == 1) required>
                                        <label for="{{ $field }}-1">1</label>
                                        <p class="se-choice-text">{{ $block['one_star'] }}</p>
                                    </div>
                                    <div class="se-choice se-choice-5">
                                        <input type="radio" name="{{ $field }}" id="{{ $field }}-5" value="5" @checked(old($field) == 5) required>
                                        <label for="{{ $field }}-5">5</label>
                                        <p class="se-choice-text">{{ $block['five_star'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-4">
                            <label class="form-label">Leader Notes</label>
                            <textarea name="leader_notes" class="form-control" rows="3" placeholder="Coaching notes, context, or action items&hellip;">{{ old('leader_notes') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer flex-wrap justify-content-between">
                        @unless ($isMonday)
                            <div class="se-monday-warning w-100 text-center mb-2">
                                <i class="bx bx-lock-alt me-1"></i>Evaluations can only be submitted on Mondays.
                            </div>
                        @endunless
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button
                                type="submit"
                                class="btn btn-primary {{ $isMonday ? '' : 'opacity-50 cursor-not-allowed' }}"
                                @disabled(!$isMonday)
                                title="{{ $isMonday ? '' : 'Evaluations can only be submitted on Mondays.' }}"
                            >Save Evaluation</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewEvaluationModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Evaluation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 col-6">
                            <div class="kpi-stat-card">
                                <div class="kpi-stat-label">Staff</div>
                                <div class="kpi-stat-value" style="font-size:16px;" id="viewEvalStaff"></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="kpi-stat-card">
                                <div class="kpi-stat-label">Branch</div>
                                <div class="kpi-stat-value" style="font-size:16px;" id="viewEvalBranch"></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="kpi-stat-card">
                                <div class="kpi-stat-label">Date</div>
                                <div class="kpi-stat-value" style="font-size:16px;" id="viewEvalDate"></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="kpi-stat-card">
                                <div class="kpi-stat-label">Overall</div>
                                <div class="kpi-stat-value" style="font-size:16px;" id="viewEvalAverage"></div>
                            </div>
                        </div>
                    </div>

                    <div class="kpi-stat-label mb-2">Score Breakdown</div>
                    <div id="viewEvalScores"></div>

                    <div class="mt-4">
                        <div class="kpi-stat-label mb-2">Leader Notes</div>
                        <p class="se-view-notes" id="viewEvalNotes"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* The modal's <form> sits between .modal-content and .modal-header/
           body/footer, which breaks Bootstrap's flex-column scroll chain
           (.modal-dialog-scrollable expects those as direct children). Make
           the form itself a flex column so .modal-body still shrinks and
           scrolls internally instead of overflowing the modal. */
        #newEvaluationModal .modal-content > form {
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .se-score-block {
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-radius: var(--luxe-radius-sm);
            padding: 16px 18px;
            margin-bottom: 16px;
        }

        .se-score-title {
            color: var(--luxe-ink);
            font-weight: 700;
            font-size: 14.5px;
            margin-bottom: 14px;
        }

        .se-choice-row {
            display: flex;
            justify-content: space-between;
            gap: 2rem;
        }

        .se-choice {
            display: flex;
            flex-direction: column;
            flex: 1 1 0;
        }

        .se-choice-5 {
            align-items: flex-end;
            text-align: right;
        }

        .se-choice input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .se-choice label {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--luxe-border-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: var(--luxe-muted);
            cursor: pointer;
            margin-bottom: 10px;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .se-choice label:hover {
            border-color: var(--luxe-accent);
        }

        .se-choice input[type="radio"]:checked + label {
            background: var(--luxe-accent);
            border-color: var(--luxe-accent);
            color: var(--luxe-accent-ink);
        }

        .se-choice input[type="radio"]:focus-visible + label {
            outline: 2px solid var(--luxe-accent);
            outline-offset: 2px;
        }

        .se-choice-text {
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
            color: var(--luxe-muted);
        }

        .se-view-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: var(--luxe-radius-sm);
            border: 1px solid var(--luxe-border);
            margin-bottom: 8px;
            font-size: 13.5px;
            color: var(--luxe-body);
        }

        .se-view-row.is-pass {
            border-color: rgba(142, 168, 138, .4);
            background: rgba(142, 168, 138, .08);
        }

        .se-view-row.is-fail {
            border-color: rgba(168, 82, 74, .4);
            background: rgba(168, 82, 74, .08);
        }

        .se-view-icon {
            font-size: 16px;
            line-height: 1;
        }

        .se-view-notes {
            font-size: 13.5px;
            line-height: 1.5;
            color: var(--luxe-muted);
            white-space: pre-wrap;
            margin: 0;
        }

        .opacity-50 {
            opacity: .5;
        }

        .cursor-not-allowed {
            cursor: not-allowed;
        }

        .se-monday-warning {
            font-size: 13px;
            font-weight: 600;
            color: var(--luxe-warning);
            background: rgba(201, 166, 107, .12);
            border: 1px solid rgba(201, 166, 107, .35);
            border-radius: var(--luxe-radius-sm);
            padding: 8px 14px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('newEvaluationModal');
            if (modalEl.dataset.autoshow === '1') {
                new bootstrap.Modal(modalEl).show();
            }
        });

        // Dashboard "Filter by Branch" — reuses the current URL so the
        // active period (This Week / Previous Weeks) survives the change.
        document.getElementById('dashboardBranchFilter').addEventListener('change', function () {
            var url = new URL(window.location.href);
            if (this.value) {
                url.searchParams.set('branch', this.value);
            } else {
                url.searchParams.delete('branch');
            }
            window.location.href = url.toString();
        });

        // New Evaluation modal: Staff Member options depend on the chosen
        // Branch, filtered client-side with no page reload.
        var STAFF_BY_BRANCH = @json($staffByBranch);
        var OLD_STAFF_ID = @json(old('staff_id'));

        function populateEvalStaffDropdown(branch) {
            var staffSelect = document.getElementById('evalStaffSelect');
            staffSelect.innerHTML = '';

            var staffForBranch = branch ? STAFF_BY_BRANCH[branch] : null;

            if (!staffForBranch) {
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select a branch first…';
                staffSelect.appendChild(placeholder);
                staffSelect.disabled = true;
                return;
            }

            staffSelect.disabled = false;

            var emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'Select staff member…';
            staffSelect.appendChild(emptyOption);

            staffForBranch.forEach(function (staff) {
                var option = document.createElement('option');
                option.value = staff.id;
                option.textContent = staff.name;
                if (OLD_STAFF_ID && String(OLD_STAFF_ID) === String(staff.id)) {
                    option.selected = true;
                }
                staffSelect.appendChild(option);
            });
        }

        document.getElementById('evalBranchSelect').addEventListener('change', function () {
            populateEvalStaffDropdown(this.value);
        });

        populateEvalStaffDropdown(document.getElementById('evalBranchSelect').value);

        var STAFF_EVAL_RUBRIC_TITLES = @json(collect($rubric)->map(fn ($block) => $block['title']));

        document.querySelectorAll('.se-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var data = JSON.parse(this.dataset.evaluation);

                document.getElementById('viewEvalStaff').textContent = data.staff;
                document.getElementById('viewEvalBranch').textContent = data.branch;
                document.getElementById('viewEvalDate').textContent = data.date;
                document.getElementById('viewEvalAverage').textContent = data.average + ' / 5';
                document.getElementById('viewEvalNotes').textContent = data.notes || 'No notes left for this evaluation.';

                var scoresEl = document.getElementById('viewEvalScores');
                scoresEl.innerHTML = '';

                Object.keys(STAFF_EVAL_RUBRIC_TITLES).forEach(function (field) {
                    var score = data.scores[field];
                    var isPass = score === 5;

                    var row = document.createElement('div');
                    row.className = 'se-view-row ' + (isPass ? 'is-pass' : 'is-fail');
                    row.innerHTML =
                        '<span class="se-view-icon">' + (isPass ? '✅' : '❌') + '</span>' +
                        '<span>' + STAFF_EVAL_RUBRIC_TITLES[field] + '</span>';

                    scoresEl.appendChild(row);
                });

                new bootstrap.Modal(document.getElementById('viewEvaluationModal')).show();
            });
        });
    </script>
@endsection
