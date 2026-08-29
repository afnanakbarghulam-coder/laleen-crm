@extends('layouts.app')
@section('title', 'Beauty Planning')

<style>
    .bp-summary {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 10px;
        padding: 14px 18px;
        text-align: center;
        background: rgba(36, 30, 28, 0.5);
    }

    .bp-summary .value {
        font-size: 22px;
        font-weight: 700;
    }

    .bp-summary .label {
        font-size: 12px;
        color: #c9a39a;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .bp-row {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .bp-row.is-overdue {
        border-color: rgba(168, 82, 74, 0.4);
        box-shadow: 0 0 16px rgba(168, 82, 74, 0.12);
    }

    .bp-row.is-due-soon {
        border-color: rgba(201, 166, 107, 0.4);
        box-shadow: 0 0 16px rgba(201, 166, 107, 0.1);
    }

    .bp-status {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 999px;
        color: #fff;
        white-space: nowrap;
    }
</style>

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Beauty Planning</h4>
            <p class="text-muted small mb-0">Clients due for outreach — most overdue first. Send a script in one click.</p>
        </div>
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bx bx-arrow-back"></i> Back to Clients
        </a>
    </div>

    @php
        $overdueCount = $queue->where('urgency', 'overdue')->count();
        $dueSoonCount = $queue->where('urgency', 'due_soon')->count();
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="bp-summary">
                <div class="value" style="color:#a8524a">{{ $overdueCount }}</div>
                <div class="label">Overdue</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bp-summary">
                <div class="value" style="color:#c9a66b">{{ $dueSoonCount }}</div>
                <div class="label">Due within {{ \App\Support\ClientMaintenancePlanner::DUE_SOON_WINDOW_DAYS }} days</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bp-summary">
                <div class="value">{{ $queue->count() }}</div>
                <div class="label">Total in queue</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @forelse ($queue as $row)
                @php
                    $statusColors = ['overdue' => '#a8524a', 'due_soon' => '#c9a66b', 'upcoming' => '#8aa6ab'];
                    $statusLabels = ['overdue' => 'Overdue', 'due_soon' => 'Due Soon', 'upcoming' => 'Upcoming'];
                    $rowClass = $row->urgency === 'overdue' ? 'is-overdue' : ($row->urgency === 'due_soon' ? 'is-due-soon' : '');
                @endphp
                <div class="bp-row {{ $rowClass }}">
                    <div>
                        <strong>{{ $row->customer->name ?? 'Unnamed Client' }}</strong>
                        <div class="text-muted small">
                            {{ $row->service_name }} · last visit {{ $row->last_visit->format('d M Y') }}
                            · next due {{ $row->next_due->format('d M Y') }}
                            · {{ $row->customer->phone }}
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="bp-status" style="background:{{ $statusColors[$row->urgency] }}">
                            {{ $statusLabels[$row->urgency] }}
                            {{ $row->days_until < 0 ? '· ' . abs($row->days_until) . 'd ago' : ($row->days_until === 0 ? '· today' : '· in ' . $row->days_until . 'd') }}
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-success wa-trigger"
                            data-name="{{ $row->customer->name ?? 'there' }}"
                            data-phone="{{ $row->customer->phone }}"
                            data-service="{{ $row->service_name }}"
                            data-last-visit="{{ $row->last_visit->format('d M Y') }}"
                            data-days-since="{{ $row->days_since_visit }}"
                            data-loyalty="{{ $row->customer->loyalty_points }}"
                            data-interval="{{ $row->interval_days }}">
                            <i class="bx bxl-whatsapp"></i> Message
                        </button>
                        <a href="{{ route('customers.show', $row->customer_id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bx bx-user"></i> Profile
                        </a>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center mb-0 py-4">No clients due for outreach right now — the queue is clear.</p>
            @endforelse
        </div>
    </div>

    @include('customers._whatsapp_drawer')
@endsection
