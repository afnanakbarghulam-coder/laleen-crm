@extends('layouts.app')
@section('title', 'Daily Tracker Records')

@section('content')
    <h4>Daily Tracker Records</h4>

    <div class="card p-3 mb-3">
        <form class="row g-3">
            <div class="col-md-3">
                <label>Date</label>
                <input type="date" name="date" value="{{ request('date') }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label>Shift</label>
                <select name="shift" class="form-select">
                    <option value="">All</option>
                    <option value="morning" {{ request('shift') == 'morning' ? 'selected' : '' }}>Morning</option>
                    <option value="night" {{ request('shift') == 'night' ? 'selected' : '' }}>Night</option>
                </select>
            </div>

            <div class="col-md-3">
                <label>Agent</label>
                <select name="agent_id" class="form-select">
                    <option value="">All</option>
                    @foreach ($agents as $a)
                        <option value="{{ $a->id }}" {{ request('agent_id') == $a->id ? 'selected' : '' }}>
                            {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 mt-7">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('daily-tracker.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <a href="{{ route('daily-tracker.create') }}" class="btn btn-success mb-3">+ Add Record</a>

    <div class="card">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Agent</th>
                    <th>Leads</th>
                    <th>Bookings</th>
                    <th>Reminders</th>
                    <th>Feedbacks</th>
                    <th>No-Shows</th>
                    <th>Excel Reviewed</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($trackers as $t)
                    <tr>
                        <td>{{ $t->date }}</td>
                        <td>{{ ucwords($t->shift) }}</td>
                        <td>{{ $t->agent->name }}</td>
                        <td>{{ $t->leads_received }}</td>
                        <td>{{ $t->bookings_done }}</td>
                        <td>{{ strtoupper($t->sent_reminders) }}</td>
                        <td>{{ strtoupper($t->asked_feedbacks) }}</td>
                        <td>{{ strtoupper($t->updated_no_shows) }}</td>
                        <td>{{ strtoupper($t->excel_reviewed) }}</td>

                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2">

                                <!-- View -->
                                <button class="btn btn-sm btn-outline-info" onclick="viewDaily({{ $t->id }})"
                                    title="View">
                                    <i class="bx bx-show"></i> </button>

                                <!-- Edit -->
                                <a href="{{ route('daily-tracker.edit', $t->id) }}" class="btn btn-sm btn-outline-warning"
                                    title="Edit">
                                    <i class="bx bx-edit-alt"></i> </a>

                                <!-- Delete -->
                                <form action="{{ route('daily-tracker.destroy', $t->id) }}" method="POST"
                                    onsubmit="return confirm('Delete this record?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>

                            </div>
                        </td>

                    </tr>
                @endforeach
            </tbody>

        </table>

        <div class="p-3">
            {{ $trackers->links() }}
        </div>
    </div>

    <!-- View Daily Tracker Modal -->
    <div class="modal fade" id="viewDailyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Daily Tracker Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>Date</th>
                            <td id="v-date"></td>
                        </tr>
                        <tr>
                            <th>Shift</th>
                            <td id="v-shift"></td>
                        </tr>
                        <tr>
                            <th>Agent</th>
                            <td id="v-agent"></td>
                        </tr>
                        <tr>
                            <th>Check In</th>
                            <td id="v-checkin"></td>
                        </tr>
                        <tr>
                            <th>Check Out</th>
                            <td id="v-checkout"></td>
                        </tr>
                        <tr>
                            <th>Leads</th>
                            <td id="v-leads"></td>
                        </tr>
                        <tr>
                            <th>Bookings</th>
                            <td id="v-bookings"></td>
                        </tr>
                        <tr>
                            <th>Sent Reminders</th>
                            <td id="v-reminders"></td>
                        </tr>
                        <tr>
                            <th>Asked Feedbacks</th>
                            <td id="v-feedbacks"></td>
                        </tr>
                        <tr>
                            <th>No Shows Updated</th>
                            <td id="v-noshow"></td>
                        </tr>
                        <tr>
                            <th>Excel Reviewed</th>
                            <td id="v-excel"></td>
                        </tr>
                        <tr>
                            <th>Notes</th>
                            <td id="v-notes"></td>
                        </tr>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <script>
        function viewDaily(id) {
            fetch(`/daily-tracker/${id}`)
                .then(res => res.json())
                .then(data => {

                    document.getElementById('v-date').innerText = data.date;
                    document.getElementById('v-shift').innerText = data.shift;
                    document.getElementById('v-agent').innerText = data.agent.name;
                    document.getElementById('v-checkin').innerText = data.check_in ?? '-';
                    document.getElementById('v-checkout').innerText = data.check_out ?? '-';
                    document.getElementById('v-leads').innerText = data.leads_received ?? '-';
                    document.getElementById('v-bookings').innerText = data.bookings_done ?? '-';
                    document.getElementById('v-reminders').innerText = data.sent_reminders.toUpperCase();
                    document.getElementById('v-feedbacks').innerText = data.asked_feedbacks.toUpperCase();
                    document.getElementById('v-noshow').innerText = data.updated_no_shows.toUpperCase();
                    document.getElementById('v-excel').innerText = data.excel_reviewed.toUpperCase();
                    document.getElementById('v-notes').innerText = data.notes ?? '-';

                    new bootstrap.Modal(document.getElementById('viewDailyModal')).show();
                });
        }
    </script>

@endsection
