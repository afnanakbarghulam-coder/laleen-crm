@extends('layouts.app')
@section('title', 'Clients')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold">Clients</h4>
        <a href="{{ route('appointments.calendar') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Book Appointment
        </a>
    </div>

    <div class="mb-4">
        <form method="GET" action="{{ route('customers.index') }}">
            <div class="input-group" style="max-width:420px">
                <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by name or phone"
                    value="{{ request('search') }}">
                <button class="btn btn-outline-secondary" type="submit">Search</button>
                @if (request('search'))
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Phone</th>
                    <th>Visits</th>
                    <th>Last Visit</th>
                    <th>Lifetime Value</th>
                    <th width="220">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="fw-semibold">{{ $customer->name ?? 'Unnamed' }}</td>
                        <td>{{ $customer->phone }}</td>
                        <td>{{ $customer->appointments_count }}</td>
                        <td>
                            @if ($lastVisit[$customer->id] ?? null)
                                {{ \Illuminate\Support\Carbon::parse($lastVisit[$customer->id])->format('d M Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ number_format($ltv[$customer->id] ?? 0, 2) }} QAR</td>
                        <td class="text-nowrap">
                            <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-user"></i> View Profile
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No clients found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $customers->links() }}
@endsection
