@extends('layouts.app')

@section('content')

<h4>{{ isset($qa) ? 'Edit QA Issue' : 'Add QA Issue' }}</h4>

<form method="POST" enctype="multipart/form-data"
      action="{{ isset($qa) ? route('qa.update', $qa->id) : route('qa.store') }}">
    @csrf
    @if(isset($qa))
        @method('PUT')
    @endif

    <div class="row g-3 mt-2">

        <div class="col-md-4">
            <label>Agent</label>
            <select name="agent_id" class="form-select" required>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}" 
                        {{ isset($qa) && $qa->agent_id == $a->id ? 'selected' : '' }}>
                        {{ $a->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <label>Customer Phone</label>
            <input name="customer_phone" class="form-control"
                   value="{{ $qa->customer_phone ?? '' }}" required>
        </div>

        <div class="col-md-4">
            <label>Issue Type</label>
            <select name="issue_type" class="form-select">
                @foreach (['wrong-info','poor-follow-up','bad-convincing','rude-behaviour','booking-error','other'] as $type)
                    <option value="{{ $type }}"
                        {{ isset($qa) && $qa->issue_type == $type ? 'selected' : '' }}>
                        {{ ucwords(str_replace('-', ' ', $type)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-12">
            <label>Notes</label>
            <textarea name="notes" class="form-control">{{ $qa->notes ?? '' }}</textarea>
        </div>

        <div class="col-md-3">
            <label>Severity</label>
            <select name="severity" class="form-select">
                @foreach (['low','medium','high'] as $level)
                    <option value="{{ $level }}"
                        {{ isset($qa) && $qa->severity == $level ? 'selected' : '' }}>
                        {{ ucfirst($level) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label>Status</label>
            <select name="status" class="form-select">
                @foreach (['pending','done'] as $st)
                    <option value="{{ $st }}"
                        {{ isset($qa) && $qa->status == $st ? 'selected' : '' }}>
                        {{ ucfirst($st) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label>Link Appointment (optional)</label>
            <select name="appointment_id" class="form-select">
                <option value="">None</option>
                @foreach ($appointments as $ap)
                    <option value="{{ $ap->id }}"
                        {{ isset($qa) && $qa->appointment_id == $ap->id ? 'selected' : '' }}>
                        #{{ $ap->id }} - {{ $ap->customer_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label>Proof Screenshot/PDF</label>
            <input type="file" name="proof_file" class="form-control">

            @if(isset($qa) && $qa->proof_file)
                <small class="text-muted">Current File: 
                    <a href="{{ asset('storage/'.$qa->proof_file) }}" target="_blank">View</a>
                </small>
            @endif
        </div>

    </div>

    <button class="btn btn-success mt-3">
        {{ isset($qa) ? 'Update' : 'Save' }}
    </button>

</form>

@endsection
