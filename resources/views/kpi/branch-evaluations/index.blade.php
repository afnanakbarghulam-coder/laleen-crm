@extends('layouts.app')
@section('title', 'Branch Evaluations')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Branch Evaluation</h4>
            <p>Mystery shopper and manager assessments, by branch.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('kpi.salon-staff.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Salon Staff</a>
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('kpi.branch-evaluation.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Branch Evaluation</a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label class="form-label mb-0 me-1 text-muted small">Filter by Branch</label>
        <select id="branchEvalFilter" class="form-select form-select-sm" style="max-width: 200px;">
            <option value="" @selected(!$branchFilter)>All Branches</option>
            @foreach ($branches as $key => $label)
                <option value="{{ $key }}" @selected($branchFilter === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @if (!empty($monthFilter))
            <span class="kpi-badge kpi-badge-gray ms-1">
                Month: {{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $monthFilter . '-01')->format('F Y') }}
            </span>
            <a href="{{ route('kpi.branch-evaluation.index', array_filter(['branch' => $branchFilter])) }}" class="btn btn-sm btn-outline-secondary">All Months</a>
        @endif
    </div>

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Manager</th>
                        <th class="text-end">Score</th>
                        <th class="text-end">Percentage</th>
                        <th>Grade</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evaluations as $evaluation)
                        @php
                            $gradeBadge = match ($evaluation->grade) {
                                'Excellent' => 'kpi-badge-green',
                                'Pass' => 'kpi-badge-green',
                                default => 'kpi-badge-amber',
                            };
                        @endphp
                        <tr>
                            <td>{{ $evaluation->evaluation_date->format('d M Y') }}</td>
                            <td><span class="kpi-badge kpi-badge-gray">{{ $branches[$evaluation->branch] ?? $evaluation->branch }}</span></td>
                            <td>{{ $evaluation->manager }}</td>
                            <td class="text-end">{{ $evaluation->total_score }}/{{ \App\Models\BranchEvaluation::maxScore() }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $evaluation->percentage, 2), '0'), '.') }}%</td>
                            <td><span class="kpi-badge {{ $gradeBadge }}">{{ $evaluation->grade }}</span></td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end align-items-center">
                                    <a href="{{ route('kpi.branch-evaluation.show', $evaluation) }}" class="btn btn-sm btn-outline-secondary">View Report</a>
                                    @if (auth()->user()?->isAdmin())
                                        <a href="{{ route('kpi.branch-evaluation.edit', $evaluation) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bx bx-pencil"></i></a>
                                        <button type="button"
                                                class="be-delete-icon-btn"
                                                title="Delete Evaluation"
                                                aria-label="Delete Evaluation"
                                                data-delete-url="{{ route('kpi.branch-evaluation.destroy', $evaluation) }}"
                                                data-delete-branch="{{ $branches[$evaluation->branch] ?? $evaluation->branch }}"
                                                data-delete-date="{{ $evaluation->evaluation_date->format('d M Y') }}"
                                                onclick="openBranchEvalDeleteModal(this)">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No branch evaluations logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $evaluations->links() }}</div>

    @if (auth()->user()?->isAdmin())
        <div class="modal fade be-delete-modal" id="beDeleteConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                <form method="POST" action="" id="beDeleteConfirmForm" class="modal-content m-0">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header d-flex justify-content-between align-items-center">
                        <h6 class="modal-title fw-bold mb-0" style="color: #f8efe6;">Delete Branch Evaluation</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0" id="beDeleteConfirmMessage" style="color: #dfd0ca; font-size: 13.5px; line-height: 1.55;"></p>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold">
                            <i class="bx bx-trash me-1"></i>Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <style>
            .be-delete-icon-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 31px;
                height: 31px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.04);
                border: 1px solid rgba(217, 143, 131, 0.22);
                color: #b8a29a;
                font-size: 15px;
                cursor: pointer;
                transition: all 0.15s ease;
                padding: 0;
            }

            .be-delete-icon-btn:hover {
                background: rgba(244, 63, 94, 0.16);
                border-color: rgba(244, 63, 94, 0.45);
                color: #fda4af;
            }

            .be-delete-modal .modal-content {
                background: linear-gradient(180deg, rgba(34, 22, 27, 0.98) 0%, rgba(20, 13, 17, 0.99) 100%);
                border: 1px solid rgba(229, 184, 168, 0.22);
                border-radius: 16px;
                color: #f8efe6;
                box-shadow: 0 24px 56px rgba(0, 0, 0, 0.62);
            }

            .be-delete-modal .modal-header {
                border-bottom: 1px solid rgba(255, 255, 255, 0.06);
                padding: 15px 20px;
            }

            .be-delete-modal .modal-body {
                padding: 18px 20px;
            }

            .be-delete-modal .modal-footer {
                border-top: 1px solid rgba(255, 255, 255, 0.06);
                padding: 13px 20px;
            }
        </style>
    @endif

    <script>
        document.getElementById('branchEvalFilter').addEventListener('change', function () {
            var url = new URL(window.location.href);
            if (this.value) {
                url.searchParams.set('branch', this.value);
            } else {
                url.searchParams.delete('branch');
            }
            window.location.href = url.toString();
        });

        @if (auth()->user()?->isAdmin())
            function openBranchEvalDeleteModal(btn) {
                var form = document.getElementById('beDeleteConfirmForm');
                var msg = document.getElementById('beDeleteConfirmMessage');
                var modalEl = document.getElementById('beDeleteConfirmModal');
                if (!form || !msg || !modalEl) return;

                var branch = btn.getAttribute('data-delete-branch') || 'this branch';
                var date = btn.getAttribute('data-delete-date') || '';
                form.action = btn.getAttribute('data-delete-url') || '';
                msg.textContent = 'Delete ' + branch + ' evaluation from ' + date + '? This cannot be undone.';

                if (window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else if (window.confirm(msg.textContent)) {
                    form.submit();
                }
            }
        @endif
    </script>
@endsection
