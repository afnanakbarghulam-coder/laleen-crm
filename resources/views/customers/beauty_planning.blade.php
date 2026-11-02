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
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Beauty Planning — Warm Outreach Engine</h4>
            <p class="text-muted small mb-0">Clients due for outreach — most overdue first. Generate an AI script in EN or عربي and launch WhatsApp in one click.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form action="{{ route('customers.sync-retention-leads') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm tooltip-bottom"
                    data-tooltip="Sends overdue clients to the Leads page for follow-up">
                    🚀 Send Overdue to Leads
                </button>
            </form>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bx bx-arrow-back"></i> Back to Clients
            </a>
        </div>
    </div>

    @php
        $overdueCount = $queue->where('urgency', 'overdue')->count();
        $dueSoonCount = $queue->where('urgency', 'due_soon')->count();
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="bp-summary tooltip-bottom" data-tooltip="Past their due date with no upcoming booking.">
                <div class="value" style="color:#a8524a">{{ $overdueCount }}</div>
                <div class="label">Overdue</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bp-summary tooltip-bottom" data-tooltip="Due for a service refresh within the next 7 days.">
                <div class="value" style="color:#c9a66b">{{ $dueSoonCount }}</div>
                <div class="label">Due within {{ \App\Support\ClientMaintenancePlanner::DUE_SOON_WINDOW_DAYS }} days</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bp-summary tooltip-bottom" data-tooltip="All clients currently overdue or due within 7 days.">
                <div class="value">{{ $queue->count() }}</div>
                <div class="label">Total in queue</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @forelse ($queue as $index => $row)
                @php
                    $statusColors = ['overdue' => '#a8524a', 'due_soon' => '#c9a66b', 'upcoming' => '#8aa6ab', 'scheduled' => '#8ea88a'];
                    $statusLabels = ['overdue' => 'Overdue', 'due_soon' => 'Due Soon', 'upcoming' => 'Upcoming Booking', 'scheduled' => 'Upcoming Booking'];
                    $statusTooltips = [
                        'overdue' => 'Past due date with no upcoming appointment.',
                        'due_soon' => 'Due for a refresh within the next 7 days.',
                        'upcoming' => 'On track within normal maintenance window.',
                        'scheduled' => 'Already has an upcoming appointment booked.',
                    ];
                    $rowClass = $row->urgency === 'overdue' ? 'is-overdue' : ($row->urgency === 'due_soon' ? 'is-due-soon' : '');
                    $rawPhone = preg_replace('/\D+/', '', (string) $row->customer->phone);
                    $waPhone = ($rawPhone !== '' && strlen($rawPhone) <= 8) ? '974' . $rawPhone : $rawPhone;
                    $daysOverdueVal = $row->days_until < 0 ? abs((int) $row->days_until) : 0;
                    $calendarUrl = route('appointments.calendar', array_filter([
                        'customer_id' => $row->customer_id,
                        'phone' => $row->customer->phone,
                        'name' => $row->customer->name,
                        'service_name' => $row->service_name,
                        'branch' => $row->branch ?? null,
                    ]));
                @endphp
                <div class="bp-row {{ $rowClass }}" data-row-index="{{ $index }}">
                    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
                        <div>
                            <strong>{{ $row->customer->name ?? 'Unnamed Client' }}</strong>
                            @if (($row->customer->loyalty_points ?? 0) > 0)
                                <span class="badge bg-warning text-dark ms-1"
                                    data-tooltip="1 point = 1 QAR discount at checkout.">💎 {{ $row->customer->loyalty_points }} pts</span>
                            @endif
                            <div class="text-muted small">
                                {{ $row->service_name }} · last visit {{ $row->last_visit->format('d M Y') }}
                                @if (!empty($row->last_staff_name))
                                    with {{ $row->last_staff_name }}
                                @endif
                                · next due {{ $row->next_due->format('d M Y') }}
                                · {{ $row->customer->phone }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="bp-status"
                                style="background:{{ $statusColors[$row->urgency] }}"
                                data-tooltip="{{ $statusTooltips[$row->urgency] ?? '' }}">
                                {{ $statusLabels[$row->urgency] }}
                                {{ $row->days_until < 0 ? '· ' . abs($row->days_until) . 'd ago' : ($row->days_until === 0 ? '· today' : '· in ' . $row->days_until . 'd') }}
                            </span>
                            <button type="button"
                                class="btn btn-sm btn-warning text-dark fw-semibold ai-retention-toggle"
                                data-target="#aiRetentionDrawer-{{ $index }}"
                                data-endpoint="{{ route('customers.ai-retention-script', $row->customer_id) }}"
                                data-service="{{ $row->service_name }}"
                                data-days-overdue="{{ $daysOverdueVal }}"
                                data-staff="{{ $row->last_staff_name ?? '' }}"
                                data-branch="{{ $row->branch ?? '' }}"
                                data-wa-phone="{{ $waPhone }}"
                                data-tooltip="Write a quick WhatsApp message in English or Arabic">
                                ✨ AI Outreach Script
                            </button>
                            <a href="{{ $calendarUrl }}" class="btn btn-sm btn-outline-info">
                                📅 Book on Calendar
                            </a>
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

                    <!-- Expandable Inline AI Warm Outreach Drawer -->
                    <div id="aiRetentionDrawer-{{ $index }}" class="w-100 mt-3 p-3 rounded d-none ai-retention-panel"
                        style="background: rgba(24, 20, 19, 0.78); border: 1px solid rgba(201, 166, 107, 0.35);"
                        data-endpoint="{{ route('customers.ai-retention-script', $row->customer_id) }}"
                        data-service="{{ $row->service_name }}"
                        data-days-overdue="{{ $daysOverdueVal }}"
                        data-staff="{{ $row->last_staff_name ?? '' }}"
                        data-branch="{{ $row->branch ?? '' }}"
                        data-wa-phone="{{ $waPhone }}"
                        data-lang="en"
                        data-mode="maintenance">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="small fw-bold text-warning">✨ AI Warm Outreach Generator</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Language Toggle">
                                    <button type="button" class="btn btn-outline-light ai-lang-btn active" data-lang="en">EN</button>
                                    <button type="button" class="btn btn-outline-light ai-lang-btn" data-lang="ar">عربي</button>
                                </div>
                                <select class="form-select form-select-sm ai-mode-select" style="width: auto;">
                                    <option value="maintenance">Maintenance Refresh</option>
                                    <option value="vip_winback">VIP Win-Back</option>
                                    <option value="package_expiry">Package Expiry</option>
                                    <option value="second_visit">2nd-Visit Nudge</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-warning ai-regenerate-btn">
                                ✨ AI Outreach Script
                            </button>
                        </div>

                        <textarea class="form-control form-control-sm mb-2 ai-script-textarea" rows="3"
                            placeholder="Generating warm outreach script..."></textarea>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-primary ai-copy-btn">
                                📋 Copy
                            </button>
                            <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                                class="btn btn-sm btn-success ai-wa-launch-btn">
                                🚀 Open in WhatsApp
                            </a>
                            <a href="{{ $calendarUrl }}" class="btn btn-sm btn-outline-info">
                                📅 Book on Calendar
                            </a>
                            <span class="small text-success d-none ai-copied-feedback">Copied!</span>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center mb-0 py-4">No clients due for outreach right now — the queue is clear.</p>
            @endforelse
        </div>
    </div>

    <script>
    (function () {
        const csrfToken = '{{ csrf_token() }}';

        function updateWaLink(panel) {
            const phone = panel.dataset.waPhone || '';
            const text = encodeURIComponent(panel.querySelector('.ai-script-textarea').value || '');
            const waBtn = panel.querySelector('.ai-wa-launch-btn');
            if (waBtn) {
                waBtn.href = 'https://wa.me/' + phone + '?text=' + text;
            }
        }

        function fetchAiRetentionScript(panel) {
            const textarea = panel.querySelector('.ai-script-textarea');
            const modeSelect = panel.querySelector('.ai-mode-select');
            const lang = panel.dataset.lang || 'en';
            const mode = modeSelect ? modeSelect.value : (panel.dataset.mode || 'maintenance');

            textarea.value = lang === 'ar' ? 'جاري تجهيز الرسالة...' : 'Generating warm outreach script...';
            textarea.dir = lang === 'ar' ? 'rtl' : 'ltr';

            fetch(panel.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    service_name: panel.dataset.service,
                    days_overdue: parseInt(panel.dataset.daysOverdue || '0', 10),
                    last_staff_name: panel.dataset.staff || '',
                    branch: panel.dataset.branch || '',
                    mode: mode,
                    language: lang
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.script) {
                    textarea.value = data.script;
                    updateWaLink(panel);
                }
            })
            .catch(() => {
                textarea.value = 'Hi! You are due for your ' + (panel.dataset.service || 'treatment') + ' refresh. Would a morning or afternoon slot work better this week?';
                updateWaLink(panel);
            });
        }

        document.querySelectorAll('.ai-retention-toggle').forEach(btn => {
            btn.addEventListener('click', function () {
                const panel = document.querySelector(this.dataset.target);
                if (!panel) return;
                const wasHidden = panel.classList.contains('d-none');
                panel.classList.toggle('d-none');
                if (wasHidden && !panel.dataset.loaded) {
                    panel.dataset.loaded = '1';
                    fetchAiRetentionScript(panel);
                }
            });
        });

        document.querySelectorAll('.ai-retention-panel').forEach(panel => {
            panel.querySelectorAll('.ai-lang-btn').forEach(langBtn => {
                langBtn.addEventListener('click', function () {
                    panel.querySelectorAll('.ai-lang-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    panel.dataset.lang = this.dataset.lang;
                    fetchAiRetentionScript(panel);
                });
            });

            const modeSelect = panel.querySelector('.ai-mode-select');
            if (modeSelect) {
                modeSelect.addEventListener('change', function () {
                    panel.dataset.mode = this.value;
                    fetchAiRetentionScript(panel);
                });
            }

            const regenBtn = panel.querySelector('.ai-regenerate-btn');
            if (regenBtn) {
                regenBtn.addEventListener('click', function () {
                    fetchAiRetentionScript(panel);
                });
            }

            const textarea = panel.querySelector('.ai-script-textarea');
            if (textarea) {
                textarea.addEventListener('input', function () {
                    updateWaLink(panel);
                });
            }

            const copyBtn = panel.querySelector('.ai-copy-btn');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    navigator.clipboard.writeText(textarea.value || '').then(() => {
                        const fb = panel.querySelector('.ai-copied-feedback');
                        if (fb) {
                            fb.classList.remove('d-none');
                            setTimeout(() => fb.classList.add('d-none'), 1800);
                        }
                    });
                });
            }
        });
    })();
    </script>

    @include('customers._whatsapp_drawer')
@endsection
