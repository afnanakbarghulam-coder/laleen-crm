@extends('layouts.app')
@section('title', 'Weekly Content Calendar')

@include('ecommerce._styles')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Weekly Content Calendar</h4>
            <p>Monday&ndash;Sunday pillar rotation &mdash; Transformation, Mechanism, Bundle, Behind-the-Scenes, Social Proof and Direct Response.</p>
        </div>
        <a href="{{ route('ecommerce.marketing') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Marketing</a>
    </div>

    {{-- ============ The Weekly Content Factory ============ --}}
    <div class="ec-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
            <h6 class="mb-0">The Weekly Content Factory</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @foreach ($statuses as $status)
                    <span class="ec-badge {{ \App\Models\EcommerceContentCalendar::statusClass($status) }}">{{ $status }}: {{ $statusCounts[$status] }}</span>
                @endforeach
                @moduleEdit('ecommerce')
                    @if ($calendar->isEmpty())
                        <form action="{{ route('ecommerce.marketing.content.seed') }}" method="POST" class="d-inline">
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
                                                    <form action="{{ route('ecommerce.marketing.content.destroy', $slot->id) }}" method="POST" onsubmit="return confirm('Remove this content slot?')">
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
                                            <form action="{{ route('ecommerce.marketing.content.update-status', $slot->id) }}" method="POST" class="ec-slot-controls">
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
                                                <span class="ec-badge {{ \App\Models\EcommerceContentCalendar::statusClass($slot->status) }}">{{ $slot->status }}</span>
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


    @moduleEdit('ecommerce')
        <div class="modal fade" id="contentModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="contentForm" method="POST" action="{{ route('ecommerce.marketing.content.store') }}">
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
            window.resetContentForm = function () {
                document.getElementById('contentModalTitle').innerText = 'Add Content Slot';
                document.getElementById('contentForm').action = '{{ route('ecommerce.marketing.content.store') }}';
                document.getElementById('contentFormMethod').value = '';
                document.getElementById('contentForm').reset();
            };

            document.querySelectorAll('.ec-content-edit-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const slot = JSON.parse(this.dataset.slot);
                    resetContentForm();

                    document.getElementById('contentModalTitle').innerText = 'Edit Content Slot';
                    document.getElementById('contentForm').action = '/ecommerce/marketing/content-calendar/' + slot.id;
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

    <style>
        .ec-hub-wide {
            display: flex;
            align-items: center;
            gap: 18px;
            text-align: left;
        }

        .ec-hub-wide > div:nth-child(2) { flex: 1; min-width: 0; }

        .ec-hub-chevron {
            font-size: 24px;
            color: var(--ec-primary, #d98f83);
            flex-shrink: 0;
        }

        .ec-phase-pill {
            background: rgba(217, 143, 131, 0.12);
            color: var(--ec-muted);
        }
    </style>
@endsection
