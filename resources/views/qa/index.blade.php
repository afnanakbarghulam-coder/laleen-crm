@extends('layouts.app')

@section('title', 'QA & Correction List')


@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold">QA & Correction List</h4>
        <a href="{{ route('qa.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Add QA Issue
        </a>
    </div>

    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Agent</th>
                <th>Customer</th>
                <th>Issue</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Booking</th>
                <th>Proof</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($corrections as $c)
                <tr>
                    <td>{{ $c->agent->name }}</td>
                    <td>{{ $c->customer_phone }}</td>
                    <td>{{ ucfirst($c->issue_type) }}</td>
                    <td><span class="badge bg-danger">{{ ucfirst($c->severity) ?? $c->severity }}</span></td>
                    <td>
                        <span class="badge {{ $c->status == 'pending' ? 'bg-warning text-dark' : 'bg-success' }}">
                            {{ ucfirst($c->status) ?? $c->status }}
                        </span>
                    </td>
                    <td>{{ $c->appointment?->id ?? '-' }}</td>
                    <td>
                        @if ($c->proof_file)
                            <a href="{{ asset('storage/' . $c->proof_file) }}" target="_blank"
                                class="btn btn-sm btn-secondary">View</a>
                        @else
                            -
                        @endif
                    </td>
                    {{-- <td>
                <a href="{{ route('qa.edit', $c->id) }}" class="btn btn-sm btn-info">Edit</a>
                <form action="{{ route('qa.destroy', $c->id) }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
                </form>
            </td> --}}

                    <td>
                        <div class="dropdown">
                            <button type="button" class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown">
                                {{-- <i class="bx bx-dots-vertical-rounded"></i> --}}
                            </button>
                            <div class="dropdown-menu">
                                <!-- Edit -->
                                <a class="dropdown-item" href="{{ route('qa.edit', $c->id) }}">
                                    <i class="bx bx-edit-alt me-1"></i> Edit
                                </a>

                                <!-- Delete -->
                                <form action="{{ route('qa.destroy', $c->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button class="dropdown-item text-danger" type="submit"
                                        onclick="return confirm('Are you sure?')">
                                        <i class="bx bx-trash me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>

                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">No records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $corrections->links() }}
@endsection
