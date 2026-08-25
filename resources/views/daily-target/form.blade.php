@extends('layouts.app')

@section('content')
    <h4>{{ $isEdit ? 'Edit Daily Target' : 'Add Daily Target' }}</h4>

    <form method="POST" action="{{ $action }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="row g-3 mt-2">

            <div class="col-md-3">
                <label>Date</label>
                <input type="date" name="date" value="{{ old('date', $data->date ?? '') }}" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label>Daily Target</label>
                <input type="number" name="daily_target" value="{{ old('daily_target', $data->daily_target ?? '') }}"
                    class="form-control" required>
            </div>

            <div class="col-md-3">
                <label>Actual Bookings</label>
                <input type="number" name="actual_bookings"
                    value="{{ old('actual_bookings', $data->actual_bookings ?? '') }}" class="form-control" required>
            </div>

            <div class="col-md-12">
                <label>Notes</label>
                <textarea name="notes" class="form-control">{{ old('notes', $data->notes ?? '') }}</textarea>
            </div>

        </div>

        <button class="btn btn-success mt-3">
            {{ $isEdit ? 'Update' : 'Save' }}
        </button>

    </form>
@endsection
