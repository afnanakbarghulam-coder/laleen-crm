@extends('layouts.app')
@section('title', $customer->name ?? 'Client Profile')

<style>
    .profile-stat {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 10px;
        padding: 14px 18px;
        text-align: center;
    }

    .profile-stat .value {
        font-size: 22px;
        font-weight: 700;
        color: #e79a91;
    }

    .profile-stat .label {
        font-size: 12px;
        color: #c9a39a;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .appt-row {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }

    .status-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 999px;
        color: #fff;
        text-transform: capitalize;
    }

    .fav-chip {
        display: inline-block;
        background: rgba(217, 143, 131,0.08);
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 12.5px;
        margin: 0 6px 6px 0;
    }

    .profile-stat.loyalty {
        background: linear-gradient(135deg, rgba(201,166,107,0.14), rgba(36,30,28,0.6));
        border-color: rgba(201,166,107,0.3);
    }

    .profile-stat.loyalty .value {
        color: #c97b4a;
    }

    .loyalty-row {
        display: flex;
        justify-content: space-between;
        font-size: 12.5px;
        padding: 5px 0;
        border-bottom: 1px solid rgba(217, 143, 131,0.07);
    }

    .loyalty-row .pts-earn { color: #8ea88a; font-weight: 700; }
    .loyalty-row .pts-redeem { color: #a8524a; font-weight: 700; }

    .planner-row {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .planner-status {
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
            <h4 class="fw-bold mb-1">{{ $customer->name ?? 'Unnamed Client' }}</h4>
            <div class="text-muted">
                <i class="bx bx-phone"></i> {{ $customer->phone }}
                @if ($customer->email)
                    &nbsp;·&nbsp; <i class="bx bx-envelope"></i> {{ $customer->email }}
                @endif
                &nbsp;·&nbsp; Client since {{ $customer->created_at->format('M Y') }}
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bx bx-arrow-back"></i> Back to Clients
            </a>
            <a href="{{ route('appointments.calendar') }}" class="btn btn-primary btn-sm">
                <i class="bx bx-plus"></i> Book Appointment
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="profile-stat">
                <div class="value">{{ number_format($lifetimeValue, 2) }}</div>
                <div class="label">Lifetime Value (QAR)</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="profile-stat">
                <div class="value">{{ $customer->appointments_count }}</div>
                <div class="label">Total Visits</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="profile-stat">
                <div class="value">{{ $upcoming->count() }}</div>
                <div class="label">Upcoming</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="profile-stat">
                <div class="value">
                    {{ $past->isNotEmpty() ? $past->first()->appointment_datetime->format('d M Y') : '—' }}
                </div>
                <div class="label">Last Visit</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="profile-stat loyalty">
                <div class="value"><i class="bx bx-diamond"></i> {{ $customer->loyalty_points }}</div>
                <div class="label">Loyalty Points</div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">
            Beauty Planner — Maintenance &amp; Re-booking Timeline
            <span class="text-muted small fw-normal">Auto-tracked from treatment history</span>
        </div>
        <div class="card-body">
            @php
                $plannerColors = ['overdue' => '#a8524a', 'due_soon' => '#c9a66b', 'upcoming' => '#8aa6ab'];
                $plannerLabels = ['overdue' => 'Overdue', 'due_soon' => 'Due Soon', 'upcoming' => 'On Track'];
            @endphp
            @forelse ($maintenanceSchedule as $row)
                <div class="planner-row">
                    <div>
                        <strong>{{ $row->service_name }}</strong>
                        <div class="text-muted small">
                            Last visit {{ $row->last_visit->format('d M Y') }} · re-books every {{ $row->interval_days }} days
                            · next due {{ $row->next_due->format('d M Y') }}
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="planner-status" style="background:{{ $plannerColors[$row->urgency] }}">
                            {{ $plannerLabels[$row->urgency] }}
                            {{ $row->days_until < 0 ? '· ' . abs($row->days_until) . 'd ago' : ($row->days_until === 0 ? '· today' : '· in ' . $row->days_until . 'd') }}
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-success wa-trigger"
                            data-name="{{ $customer->name ?? 'there' }}"
                            data-phone="{{ $customer->phone }}"
                            data-service="{{ $row->service_name }}"
                            data-last-visit="{{ $row->last_visit->format('d M Y') }}"
                            data-days-since="{{ $row->days_since_visit }}"
                            data-loyalty="{{ $customer->loyalty_points }}"
                            data-interval="{{ $row->interval_days }}">
                            <i class="bx bxl-whatsapp"></i> Message
                        </button>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">No recurring treatments tracked yet. Set a re-booking interval on a service to start tracking it here.</p>
            @endforelse
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Upcoming Appointments</div>
                <div class="card-body">
                    @forelse ($upcoming as $a)
                        <div class="appt-row">
                            <div>
                                <strong>{{ $a->appointment_datetime->format('D, d M Y · h:i A') }}</strong>
                                <div class="text-muted small">{{ $a->service_name }} · {{ $a->staff->name ?? 'Unassigned' }}</div>
                            </div>
                            <span class="status-badge" style="background:#d98f83">{{ $a->status }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No upcoming appointments.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">Past Appointments</div>
                <div class="card-body">
                    @php
                        $statusColors = ['pending' => '#d98f83', 'arrived' => '#b98ea3', 'in_progress' => '#c97b4a', 'completed' => '#8ea88a', 'no_show' => '#c9a39a', 'cancelled' => '#a8524a'];
                    @endphp
                    @forelse ($past as $a)
                        <div class="appt-row">
                            <div>
                                <strong>{{ $a->appointment_datetime->format('d M Y · h:i A') }}</strong>
                                <div class="text-muted small">
                                    {{ $a->service_name }} · {{ $a->staff->name ?? 'Unassigned' }}
                                    @if ($a->price)
                                        · {{ number_format($a->price, 2) }} QAR
                                    @endif
                                </div>
                            </div>
                            <span class="status-badge" style="background:{{ $statusColors[$a->status] ?? '#c9a39a' }}">
                                {{ str_replace('_', ' ', $a->status) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No past appointments yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Favorite Services</div>
                <div class="card-body">
                    @forelse ($favoriteServices as $service => $count)
                        <span class="fav-chip">{{ $service }} &times; {{ $count }}</span>
                    @empty
                        <p class="text-muted mb-0">No service history yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Loyalty &amp; Rewards</span>
                    <span class="badge bg-warning text-dark"><i class="bx bx-diamond"></i> {{ $customer->loyalty_points }} pts</span>
                </div>
                <div class="card-body">
                    @moduleEdit('clients')
                        <form action="{{ route('customers.loyalty.redeem', $customer->id) }}" method="POST" class="mb-3">
                            @csrf
                            <div class="row g-2">
                                <div class="col-4">
                                    <input type="number" name="points" class="form-control form-control-sm" placeholder="Points" min="1" max="{{ $customer->loyalty_points }}" required>
                                </div>
                                <div class="col-8">
                                    <input type="text" name="reward" class="form-control form-control-sm" placeholder="Reward (e.g. 50 QAR off)" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-warning mt-2 w-100"
                                {{ $customer->loyalty_points < 1 ? 'disabled' : '' }}>
                                <i class="bx bx-gift"></i> Redeem Points
                            </button>
                        </form>
                    @endmoduleEdit

                    <h6 class="small fw-bold text-muted text-uppercase mb-2">Recent Activity</h6>
                    @forelse ($loyaltyHistory as $tx)
                        <div class="loyalty-row">
                            <span>{{ $tx->description }}</span>
                            <span class="{{ $tx->points >= 0 ? 'pts-earn' : 'pts-redeem' }}">
                                {{ $tx->points >= 0 ? '+' : '' }}{{ $tx->points }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No loyalty activity yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card mb-4 border-danger-subtle">
                <div class="card-header fw-semibold text-danger">
                    <i class="bx bx-error-circle"></i> Allergies &amp; Staff Alerts
                </div>
                <div class="card-body">
                    @moduleEdit('clients')
                        <form id="allergiesForm">
                            @csrf
                            <textarea name="allergies" id="allergiesField" class="form-control mb-2" rows="3"
                                placeholder="e.g. Mild allergy to peroxide, avoid ammonia-based dyes...">{{ $customer->allergies }}</textarea>
                            <button type="submit" class="btn btn-outline-danger btn-sm">Save Alert</button>
                            <span class="text-success small ms-2 d-none" id="allergiesSaved">Saved</span>
                        </form>
                    @else
                        <p class="mb-0">{{ $customer->allergies ?: 'No allergy notes on file.' }}</p>
                    @endmoduleEdit
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">Client Notes</div>
                <div class="card-body">
                    @moduleEdit('clients')
                        <form id="notesForm">
                            @csrf
                            <textarea name="notes" id="notesField" class="form-control mb-2" rows="6"
                                placeholder="Preferences, styling notes...">{{ $customer->notes }}</textarea>
                            <button type="submit" class="btn btn-primary btn-sm">Save Notes</button>
                            <span class="text-success small ms-2 d-none" id="notesSaved">Saved</span>
                        </form>
                    @else
                        <p class="mb-0">{{ $customer->notes ?: 'No notes on file.' }}</p>
                    @endmoduleEdit
                </div>
            </div>
        </div>
    </div>

    @moduleEdit('clients')
        <script>
            document.getElementById('notesForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const notes = document.getElementById('notesField').value;

                fetch("{{ route('customers.notes.update', $customer->id) }}", {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ notes })
                    })
                    .then(r => r.json())
                    .then(() => {
                        const badge = document.getElementById('notesSaved');
                        badge.classList.remove('d-none');
                        setTimeout(() => badge.classList.add('d-none'), 2000);
                    });
            });

            document.getElementById('allergiesForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const allergies = document.getElementById('allergiesField').value;

                fetch("{{ route('customers.allergies.update', $customer->id) }}", {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ allergies })
                    })
                    .then(r => r.json())
                    .then(() => {
                        const badge = document.getElementById('allergiesSaved');
                        badge.classList.remove('d-none');
                        setTimeout(() => badge.classList.add('d-none'), 2000);
                    });
            });
        </script>
    @endmoduleEdit

    @include('customers._whatsapp_drawer')
@endsection
