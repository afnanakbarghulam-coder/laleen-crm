@extends('layouts.app')
@section('title', 'Monthly Ads Plan')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Monthly Ads Plan</h4>
            <p>Simple phased pipeline for every ad — move a card between phases right from the board.</p>
        </div>
        <div class="kpi-header-meta">
            @moduleEdit('kpis')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#monthlyAdModal" onclick="resetMonthlyAdForm()">
                    <i class="bx bx-plus me-1"></i> Add Ad
                </button>
            @endmoduleEdit
            <a href="{{ route('kpi.marketing.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Marketing</a>
        </div>
    </div>

    <div class="table-responsive">
        <div class="row g-3 flex-nowrap" style="min-width: 1100px;">
            @foreach ($adsByPhase as $phase => $ads)
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <strong>{{ $phase }}</strong>
                            <span class="badge bg-secondary">{{ $ads->count() }}</span>
                        </div>
                        <div class="card-body d-flex flex-column gap-2">
                            @forelse ($ads as $ad)
                                @php $deadline = $ad->nextDeadline(); @endphp
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="fw-bold">{{ $ad->ad_name }}</div>
                                            @moduleEdit('kpis')
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-warning monthly-ad-edit-btn"
                                                        data-ad='@json($ad->toEditPayload())'
                                                        title="Edit">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <form action="{{ route('kpi.ads-plan.destroy', $ad) }}" method="POST" onsubmit="return confirm('Delete this ad?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            @endmoduleEdit
                                        </div>
                                        <div class="small text-muted mt-1"><i class="bx bx-user me-1"></i>{{ $ad->person_responsible }}</div>

                                        <div class="mt-2">
                                            @if ($deadline)
                                                <span class="badge {{ $deadline['date']->isPast() ? 'bg-danger' : 'bg-warning text-dark' }}">
                                                    {{ $deadline['label'] }}: {{ $deadline['date']->format('d M Y') }}
                                                </span>
                                            @else
                                                <span class="badge bg-light text-dark">No deadline set</span>
                                            @endif
                                        </div>

                                        @if ($phase === 'Planning')
                                            @if (auth()->user()->name === $ad->person_responsible)
                                                <form action="{{ route('kpi.ads-plan.complete-planning', $ad) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 mt-2" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">✔ Mark Phase 1 Completed</button>
                                                </form>
                                            @else
                                                <div class="text-muted text-center small mt-2 py-1 border rounded" style="border-color: #374151 !important;">Waiting for completion by assigned staff</div>
                                            @endif
                                        @elseif ($phase === 'Production')
                                            <div class="small text-muted mt-2">Checklist: {{ $ad->chk_hook + $ad->chk_value + $ad->chk_risk + $ad->chk_cta }}/4</div>
                                            @moduleEdit('kpis')
                                                <form action="{{ route('kpi.ads-plan.complete-production', $ad) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 mt-2" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);">🚀 Push to Testing</button>
                                                </form>
                                            @endmoduleEdit
                                        @else
                                            @moduleEdit('kpis')
                                                <form action="{{ route('kpi.ads-plan.update-phase', $ad) }}" method="POST" class="mt-2">
                                                    @csrf @method('PATCH')
                                                    <select name="phase" class="form-select form-select-sm" onchange="this.form.submit()">
                                                        @foreach (\App\Models\KpiMonthlyAd::PHASES as $option)
                                                            <option value="{{ $option }}" @selected($ad->phase === $option)>{{ $option }}</option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                            @endmoduleEdit
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted small py-4">No ads in {{ $phase }}.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @moduleEdit('kpis')
        @include('kpi.ads-plan._form')
    @endmoduleEdit
@endsection
