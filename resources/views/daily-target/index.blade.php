@extends('layouts.app')

@section('title', 'Daily Target Tracker')

@section('content')
    <h4>Daily Target Tracker</h4>

    <a href="{{ route('daily-target.create') }}" class="btn btn-primary mb-3">+ Add New</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Date</th>
                <th>Daily Target</th>
                <th>Actual</th>
                <th>% Achieved</th>
                <th>Notes</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($records as $r)
                <tr>
                    <td>{{ $r->date }}</td>
                    <td>{{ $r->daily_target }}</td>
                    <td>{{ $r->actual_bookings }}</td>
                    <td>{{ $r->percentage_achieved }}%</td>
                    <td>{{ $r->notes }}</td>
                    {{-- <td>
                        <a href="{{ route('daily-target.edit', $r->id) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('daily-target.destroy', $r->id) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this?')">X</button>
                        </form>
                    </td> --}}
                    <td class="text-center">
                        <!-- Edit -->
                        <a href="{{ route('daily-target.edit', $r->id) }}" class="btn btn-sm btn-outline-warning"
                            title="Edit">
                            <i class="bx bx-edit-alt"></i></a>

                        <!-- Delete -->
                        <form action="{{ route('daily-target.destroy', $r->id) }}" method="POST" class="d-inline"
                            onsubmit="return confirm('Delete this?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bx bx-trash"></i>
                            </button>
                        </form>
                    </td>

                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $records->links() }}
@endsection
