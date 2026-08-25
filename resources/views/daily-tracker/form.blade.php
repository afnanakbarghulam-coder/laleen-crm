@extends('layouts.app')

@section('content')
    <h4>{{ isset($daily) ? 'Edit Daily Tracker' : 'Add Daily Tracker' }}</h4>

    <form method="POST" action="{{ isset($daily) ? route('daily-tracker.update', $daily) : route('daily-tracker.store') }}">
        @csrf
        @if (isset($daily))
            @method('PUT')
        @endif

        <div class="row g-3 mt-2">

            <div class="col-md-3">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="{{ old('date', $daily->date ?? '') }}"
                    required>
            </div>

            <div class="col-md-3">
                <label>Shift</label>
                <select name="shift" class="form-select" required>
                    <option value="morning" {{ old('shift', $daily->shift ?? '') == 'morning' ? 'selected' : '' }}>
                        Morning
                    </option>
                    <option value="night" {{ old('shift', $daily->shift ?? '') == 'night' ? 'selected' : '' }}>
                        Night
                    </option>
                </select>
            </div>

            <div class="col-md-6">
                <label>Agent</label>
                <select name="agent_id" class="form-select" required>
                    @foreach ($agents as $a)
                        <option value="{{ $a->id }}"
                            {{ old('agent_id', $daily->agent_id ?? '') == $a->id ? 'selected' : '' }}>
                            {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label>Check-in</label>
                <input type="time" name="check_in" value="{{ old('check_in', $daily->check_in ?? '') }}"
                    class="form-control">
            </div>

            <div class="col-md-3">
                <label>Check-out</label>
                <input type="time" name="check_out" value="{{ old('check_out', $daily->check_out ?? '') }}"
                    class="form-control">
            </div>

            {{-- YES/NO/NA dropdown generator --}}
            @php
                $yn = ['yes' => 'YES', 'no' => 'NO', 'na' => 'Not Applicable'];
            @endphp

            @foreach ([
            'sent_reminders' => 'Sent Reminders',
            'asked_feedbacks' => 'Asked Feedbacks',
            'updated_no_shows' => 'Updated No Shows',
            'excel_reviewed' => 'Excel Reviewed',
            'checked_bookings_vs_sales' => 'Checked Bookings vs Sales',
            'corrections_done' => 'Corrections Done',
        ] as $field => $label)
                <div class="col-md-4">
                    <label>{{ $label }}</label>
                    <select name="{{ $field }}" class="form-select">
                        @foreach ($yn as $val => $text)
                            <option value="{{ $val }}"
                                {{ old($field, $daily->$field ?? '') == $val ? 'selected' : '' }}>
                                {{ $text }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <div class="col-md-3">
                <label>Leads Received</label>
                <input type="number" name="leads_received" class="form-control"
                    value="{{ old('leads_received', $daily->leads_received ?? '') }}">
            </div>

            <div class="col-md-3">
                <label>Bookings Done</label>
                <input type="number" name="bookings_done" class="form-control"
                    value="{{ old('bookings_done', $daily->bookings_done ?? '') }}">
            </div>

            <div class="col-md-12">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $daily->notes ?? '') }}</textarea>
            </div>

        </div>

        <button class="btn btn-success mt-3">
            {{ isset($daily) ? 'Update' : 'Save' }}
        </button>

    </form>
@endsection
