@extends('layouts.app')
@section('title', 'Ecommerce Dashboard')

@include('ecommerce._styles')

@php
    $canEdit = auth()->check() && auth()->user()->canEdit('ecommerce');

    // null = no data (render an em dash), true = healthy, false = needs attention.
    $borderClass = fn ($healthy) => $healthy === null ? '' : ($healthy ? 'ec-kpi-good' : 'ec-kpi-bad');
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <h4>Ecommerce Dashboard</h4>
            <p>The Dashboard of Truth &mdash; blended acquisition economics, plus the weekly content factory that feeds them.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kpiModal">+ Log Daily KPIs</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <p class="ec-sub mb-0">{{ $filterText }} &middot; {{ $metrics['day_count'] }} day(s) of data</p>
        <form method="GET" action="{{ route('ecommerce.index') }}" class="d-flex align-items-center flex-wrap gap-2">
            <label for="startDateFilter" class="ec-sub mb-0">From</label>
            <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $startDate }}">
            <label for="endDateFilter" class="ec-sub mb-0">To</label>
            <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $endDate }}">
            <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
        </form>
    </div>

    @unless ($metrics['has_data'])
        <div class="ec-accounting-note">
            <span>📊</span>
            <div>
                <strong>No KPI snapshots in this range.</strong>
                Log a day's blended revenue, ad spend and order counts to populate MER, CAC, AOV and Repeat Rate.
            </div>
        </div>
    @endunless

    {{-- ============ The Dashboard of Truth ============ --}}
    <div class="row g-3">
        <div class="col-md-3">
            <div class="ec-card ec-kpi-card {{ $borderClass($metrics['mer_healthy']) }}">
                <h6>MER</h6>
                <div class="ec-kpi-value">
                    {{ $metrics['mer'] === null ? '—' : number_format($metrics['mer'], 2) . 'x' }}
                </div>
                <div class="ec-sub">Revenue &divide; Ad Spend &middot; target &ge; {{ number_format($merTarget, 1) }}x</div>
                @if ($metrics['mer'] !== null)
                    <div class="ec-kpi-flag {{ $metrics['mer_healthy'] ? 'ec-positive' : 'ec-negative' }}">
                        {{ $metrics['mer_healthy'] ? '✓ Efficient' : '⚠ Below target — ads are eating margin' }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-3">
            <div class="ec-card ec-kpi-card {{ $borderClass($metrics['cac_healthy']) }}">
                <h6>Blended CAC</h6>
                <div class="ec-kpi-value">
                    {{ $metrics['cac'] === null ? '—' : 'PKR ' . number_format($metrics['cac'], 2) }}
                </div>
                <div class="ec-sub">Ad Spend &divide; New Customers</div>
                @if ($metrics['cac_healthy'] !== null)
                    <div class="ec-kpi-flag {{ $metrics['cac_healthy'] ? 'ec-positive' : 'ec-negative' }}">
                        {{ $metrics['cac_healthy'] ? '✓ Below AOV' : '⚠ Costs more than an order is worth' }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-3">
            <div class="ec-card ec-kpi-card">
                <h6>AOV</h6>
                <div class="ec-kpi-value">
                    {{ $metrics['aov'] === null ? '—' : 'PKR ' . number_format($metrics['aov'], 2) }}
                </div>
                <div class="ec-sub">Revenue &divide; Orders</div>
                @if ($metrics['aov'] !== null)
                    <div class="ec-kpi-flag ec-sub">{{ number_format($metrics['total_orders']) }} orders in range</div>
                @endif
            </div>
        </div>

        <div class="col-md-3">
            <div class="ec-card ec-kpi-card {{ $borderClass($metrics['repeat_rate_healthy']) }}">
                <h6>Repeat Purchase Rate</h6>
                <div class="ec-kpi-value">
                    {{ $metrics['repeat_rate'] === null ? '—' : number_format($metrics['repeat_rate'], 1) . '%' }}
                </div>
                <div class="ec-sub">Repeat &divide; Orders &middot; target &ge; {{ number_format($repeatRateTarget, 0) }}%</div>
                @if ($metrics['repeat_rate'] !== null)
                    <div class="ec-kpi-flag {{ $metrics['repeat_rate_healthy'] ? 'ec-positive' : 'ec-negative' }}">
                        {{ $metrics['repeat_rate_healthy'] ? '✓ Healthy retention' : '⚠ Too reliant on new buyers' }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($metrics['has_data'])
        <div class="ec-card mt-3">
            <div class="d-flex flex-wrap gap-4">
                <div><span class="ec-sub">Revenue</span><div class="ec-stock-emphasis">PKR {{ number_format($metrics['total_revenue'], 2) }}</div></div>
                <div><span class="ec-sub">Ad Spend</span><div class="ec-stock-emphasis">PKR {{ number_format($metrics['total_ad_spend'], 2) }}</div></div>
                <div><span class="ec-sub">Orders</span><div class="ec-stock-emphasis">{{ number_format($metrics['total_orders']) }}</div></div>
                <div><span class="ec-sub">New Customers</span><div class="ec-stock-emphasis">{{ number_format($metrics['new_customers']) }}</div></div>
                <div><span class="ec-sub">Repeat Customers</span><div class="ec-stock-emphasis">{{ number_format($metrics['repeat_customers']) }}</div></div>
            </div>
        </div>
    @endif

    {{-- ============ The Weekly Content Factory ============ --}}
    <div class="ec-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
            <h6 class="mb-0">The Weekly Content Factory</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @foreach ($statuses as $status)
                    <span class="ec-badge ec-status-{{ Str::slug($status) }}">{{ $status }}: {{ $statusCounts[$status] }}</span>
                @endforeach
                @moduleEdit('ecommerce')
                    @if ($calendar->isEmpty())
                        <form action="{{ route('ecommerce.content.seed') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">Seed Mon–Sun</button>
                        </form>
                    @endif
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#contentModal" onclick="resetContentForm()">+ Add Slot</button>
                @endmoduleEdit
            </div>
        </div>
        <p class="ec-sub mb-3">Monday&ndash;Sunday pillar rotation. Change status or assignee inline &mdash; it saves immediately.</p>

        <div class="table-responsive">
            <div class="row g-3 flex-nowrap ec-content-board">
                @foreach ($days as $day)
                    @php $slots = $calendarByDay[$day]; @endphp
                    <div class="col">
                        <div class="ec-day-column">
                            <div class="ec-day-header">
                                <span>{{ strtoupper($day) }}</span>
                                <span class="ec-badge bg-secondary">{{ $slots->count() }}</span>
                            </div>
                            <div class="ec-day-body">
                                @forelse ($slots as $slot)
                                    <div class="ec-content-slot {{ $slot->isLive() ? 'is-live' : '' }}">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <span class="ec-pillar-badge">{{ $slot->content_type ?: 'No pillar' }}</span>
                                            @moduleEdit('ecommerce')
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-warning ec-content-edit-btn"
                                                        data-slot='@json($slot->toEditPayload())'
                                                        title="Edit"><i class="bi bi-pencil-square"></i></button>
                                                    <form action="{{ route('ecommerce.content.destroy', $slot->id) }}" method="POST" onsubmit="return confirm('Remove this content slot?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            @endmoduleEdit
                                        </div>

                                        @if ($slot->objective)
                                            <div class="ec-slot-objective">{{ $slot->objective }}</div>
                                        @endif
                                        @if ($slot->script_concept)
                                            <div class="ec-slot-script">{{ $slot->script_concept }}</div>
                                        @endif

                                        @moduleEdit('ecommerce')
                                            <form action="{{ route('ecommerce.content.update-status', $slot->id) }}" method="POST" class="ec-slot-controls">
                                                @csrf @method('PATCH')
                                                <select name="status" class="cc-mini-select" onchange="this.form.submit()">
                                                    @foreach ($statuses as $status)
                                                        <option value="{{ $status }}" @selected($slot->status === $status)>{{ $status }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="person_responsible" class="cc-mini-select" onchange="this.form.submit()">
                                                    <option value="">&mdash; Assign &mdash;</option>
                                                    @foreach ($staff as $member)
                                                        <option value="{{ $member->name }}" @selected($slot->person_responsible === $member->name)>{{ $member->name }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            <div class="ec-slot-controls">
                                                <span class="ec-badge ec-status-{{ Str::slug($slot->status) }}">{{ $slot->status }}</span>
                                                <span class="ec-sub">{{ $slot->person_responsible ?: 'Unassigned' }}</span>
                                            </div>
                                        @endmoduleEdit
                                    </div>
                                @empty
                                    <div class="ec-sub text-center py-3">No slot</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============ Logged KPI snapshots ============ --}}
    @if ($kpiRows->isNotEmpty())
        <div class="ec-card mt-3">
            <h6 class="mb-3">Daily KPI Snapshots</h6>
            <div class="table-responsive">
                <table class="table ec-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Ad Spend</th>
                            <th class="text-end">Orders</th>
                            <th class="text-end">New</th>
                            <th class="text-end">Repeat</th>
                            <th class="text-end">MER</th>
                            @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kpiRows as $row)
                            @php $rowMer = (float) $row->total_ad_spend > 0 ? (float) $row->total_revenue / (float) $row->total_ad_spend : null; @endphp
                            <tr>
                                <td>{{ $row->date->format('d M Y') }}</td>
                                <td class="text-end">PKR {{ number_format($row->total_revenue, 2) }}</td>
                                <td class="text-end">PKR {{ number_format($row->total_ad_spend, 2) }}</td>
                                <td class="text-end">{{ number_format($row->total_orders) }}</td>
                                <td class="text-end">{{ number_format($row->new_customers) }}</td>
                                <td class="text-end">{{ number_format($row->repeat_customers) }}</td>
                                <td class="text-end {{ $rowMer === null ? '' : ($rowMer >= $merTarget ? 'ec-positive' : 'ec-negative') }}">
                                    {{ $rowMer === null ? '—' : number_format($rowMer, 2) . 'x' }}
                                </td>
                                @moduleEdit('ecommerce')
                                    <td class="text-end">
                                        <form action="{{ route('ecommerce.kpis.destroy', $row->id) }}" method="POST" onsubmit="return confirm('Delete this snapshot?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                @endmoduleEdit
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @moduleEdit('ecommerce')
        {{-- Log Daily KPIs Modal --}}
        <div class="modal fade" id="kpiModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.kpis.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log Daily KPI Snapshot</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="ec-sub mb-3">Blended figures for one day, straight from Shopify + Meta. Re-submitting a date overwrites it rather than adding a second row.</p>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total Revenue (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="total_revenue" id="kpiRevenue" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total Ad Spend (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="total_ad_spend" id="kpiAdSpend" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total Orders</label>
                                    <input type="number" step="1" min="0" name="total_orders" id="kpiOrders" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">New Customers</label>
                                    <input type="number" step="1" min="0" name="new_customers" id="kpiNew" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Repeat Customers</label>
                                    <input type="number" step="1" min="0" name="repeat_customers" id="kpiRepeat" class="form-control" required>
                                </div>
                            </div>
                            <div class="ec-accounting-note mt-3 mb-0" id="kpiPreview" style="display: none;">
                                <span>🧮</span>
                                <div id="kpiPreviewText"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save Snapshot</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Add / Edit Content Slot Modal --}}
        <div class="modal fade" id="contentModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="contentForm" method="POST" action="{{ route('ecommerce.content.store') }}">
                        @csrf
                        <input type="hidden" name="_method" id="contentFormMethod">
                        <div class="modal-header">
                            <h5 class="modal-title" id="contentModalTitle">Add Content Slot</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Day</label>
                                <select name="day_of_week" id="c_day" class="form-select" required>
                                    @foreach ($days as $day)
                                        <option value="{{ $day }}">{{ $day }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Content Pillar</label>
                                <select name="content_type" id="c_type" class="form-select">
                                    <option value="">&mdash; Select pillar &mdash;</option>
                                    @foreach ($contentTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" id="c_status" class="form-select" required>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Objective</label>
                                <input type="text" name="objective" id="c_objective" class="form-control" placeholder="e.g. Drive first-time purchase">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Person Responsible</label>
                                <select name="person_responsible" id="c_person" class="form-select">
                                    <option value="">&mdash; Unassigned &mdash;</option>
                                    @foreach ($staff as $member)
                                        <option value="{{ $member->name }}">{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Script / Concept</label>
                                <textarea name="script_concept" id="c_script" class="form-control" rows="4" placeholder="Hook, demo, proof, CTA…"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const MER_TARGET = {{ $merTarget }};

            // Live preview so the operator sees the consequence of the numbers
            // before they commit the snapshot.
            const kpiFields = ['kpiRevenue', 'kpiAdSpend', 'kpiOrders', 'kpiNew', 'kpiRepeat'].map((id) => document.getElementById(id));
            const kpiPreview = document.getElementById('kpiPreview');
            const kpiPreviewText = document.getElementById('kpiPreviewText');

            function refreshKpiPreview() {
                const [rev, spend, orders, , repeat] = kpiFields.map((f) => parseFloat(f.value) || 0);

                if (rev <= 0 && spend <= 0 && orders <= 0) {
                    kpiPreview.style.display = 'none';
                    return;
                }

                const parts = [];
                parts.push(spend > 0 ? 'MER ' + (rev / spend).toFixed(2) + 'x' + ((rev / spend) < MER_TARGET ? ' ⚠' : ' ✓') : 'MER —');
                parts.push(orders > 0 ? 'AOV PKR ' + (rev / orders).toFixed(2) : 'AOV —');
                parts.push(orders > 0 ? 'Repeat ' + ((repeat / orders) * 100).toFixed(1) + '%' : 'Repeat —');

                kpiPreviewText.innerHTML = '<strong>This day computes to:</strong> ' + parts.join(' · ');
                kpiPreview.style.display = '';
            }

            kpiFields.forEach((f) => f.addEventListener('input', refreshKpiPreview));
            document.getElementById('kpiModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                kpiPreview.style.display = 'none';
            });

            window.resetContentForm = function () {
                document.getElementById('contentModalTitle').innerText = 'Add Content Slot';
                document.getElementById('contentForm').action = '{{ route('ecommerce.content.store') }}';
                document.getElementById('contentFormMethod').value = '';
                document.getElementById('contentForm').reset();
            };

            document.querySelectorAll('.ec-content-edit-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const slot = JSON.parse(this.dataset.slot);
                    resetContentForm();

                    document.getElementById('contentModalTitle').innerText = 'Edit Content Slot';
                    document.getElementById('contentForm').action = '/ecommerce/content-calendar/' + slot.id;
                    document.getElementById('contentFormMethod').value = 'PUT';

                    document.getElementById('c_day').value = slot.day_of_week || 'Monday';
                    document.getElementById('c_type').value = slot.content_type || '';
                    document.getElementById('c_status').value = slot.status || 'Scripted';
                    document.getElementById('c_objective').value = slot.objective || '';
                    document.getElementById('c_person').value = slot.person_responsible || '';
                    document.getElementById('c_script').value = slot.script_concept || '';

                    new bootstrap.Modal(document.getElementById('contentModal')).show();
                });
            });
        </script>
    @endmoduleEdit
@endsection
