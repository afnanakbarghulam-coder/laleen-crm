{{-- @extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

<h4 class="mb-4 fw-bold">Dashboard Overview</h4>

<div class="row g-4">

    <!-- Today's Appointments -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Today's Appointments</p>
                    <h3 class="fw-bold mb-0">{{ $todayAppointments ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-primary-light text-primary">
                    <i class="bx bx-calendar fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Leads -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Total Leads</p>
                    <h3 class="fw-bold mb-0">{{ $totalLeads ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-success-light text-success">
                    <i class="bx bx-user-plus fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Followups -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Pending Followups</p>
                    <h3 class="fw-bold mb-0">{{ $pendingFollowups ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-warning-light text-warning">
                    <i class="bx bx-time-five fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Staff -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Total Staff</p>
                    <h3 class="fw-bold mb-0">{{ $availableStaff ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-purple-light text-purple">
                    <i class="bx bx-group fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue -->
    <div class="col-md-6 col-lg-4">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Monthly Revenue</p>
                    <h3 class="fw-bold mb-0">{{ number_format($monthlyRevenue ?? 0, 2) }} QAR</h3>
                </div>
                <div class="icon-circle bg-info-light text-info">
                    <i class="bx bx-wallet fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Revenue -->
    <div class="col-md-6 col-lg-4">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Total Revenue</p>
                    <h3 class="fw-bold mb-0">{{ number_format($totalRevenue ?? 0, 2) }} QAR</h3>
                </div>
                <div class="icon-circle bg-danger-light text-danger">
                    <i class="bx bx-bar-chart fs-4"></i>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection --}}




















@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

<style>
.dashboard-card {
    border-radius: 18px;
    transition: all 0.25s ease-in-out;
    background: var(--luxe-surface);
    border: 1px solid var(--luxe-border);
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.35);
}

.icon-circle {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bg-primary-light { background: rgba(217, 143, 131, 0.14); }
.bg-success-light { background: rgba(142, 168, 138, 0.14); }
.bg-warning-light { background: rgba(201, 166, 107, 0.16); }
.bg-danger-light { background: rgba(168, 82, 74, 0.14); }
.bg-info-light { background: rgba(138, 166, 171, 0.14); }
.bg-purple-light { background: rgba(185, 142, 163, 0.14); }
.bg-orange-light { background: rgba(201, 123, 74, 0.16); }

.text-purple { color: #b98ea3; }
.text-orange { color: #c97b4a; }

.dashboard-title {
    font-size: 14px;
    color: var(--luxe-muted);
    margin-bottom: 5px;
}

.dashboard-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--luxe-ink);
}
</style>

<div class="row g-4">

    <!-- Welcome Card -->
    <div class="col-12">
        <div class="card dashboard-card p-4">
            <h4 class="fw-bold mb-1">Welcome to Laleen Ops 💇‍♀️</h4>
            <p class="text-muted mb-0">Manage appointments, leads, staff, performance and customer experience.</p>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="col-md-4">
        <a href="{{ route('appointments.index') }}" class="text-decoration-none">
            <div class="card dashboard-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="dashboard-title">Today's Appointments</div>
                        <div class="dashboard-value text-dark">{{ $todayAppointments ?? 0 }}</div>
                    </div>
                    <div class="icon-circle bg-primary-light text-primary">
                        <i class="bx bx-calendar fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Total Leads -->
    <div class="col-md-4">
        <a href="{{ route('leads.index') }}" class="text-decoration-none">
            <div class="card dashboard-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="dashboard-title">Total Leads</div>
                        <div class="dashboard-value text-dark">{{ $totalLeads ?? 0 }}</div>
                    </div>
                    <div class="icon-circle bg-success-light text-success">
                        <i class="bx bx-user-plus fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Pending Follow-ups -->
    <div class="col-md-4">
        <a href="{{ route('leads.index') }}" class="text-decoration-none">
            <div class="card dashboard-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="dashboard-title">Pending Follow-ups</div>
                        <div class="dashboard-value text-dark">{{ $pendingFollowups ?? 0 }}</div>
                    </div>
                    <div class="icon-circle bg-warning-light text-warning">
                        <i class="bx bx-time-five fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Total Staff -->
    <div class="col-md-4">
        <a href="{{ route('staffs.index') }}" class="text-decoration-none">
            <div class="card dashboard-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="dashboard-title">Total Staff</div>
                        <div class="dashboard-value text-dark">{{ $staffCount ?? 0 }}</div>
                    </div>
                    <div class="icon-circle bg-purple-light text-purple">
                        <i class="bx bx-group fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Staff on Leave -->
    <div class="col-md-4">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="dashboard-title">Staff on Leave</div>
                    <div class="dashboard-value text-dark">{{ $staffOnLeave ?? 0 }}</div>
                </div>
                <div class="icon-circle bg-danger-light text-danger">
                    <i class="bx bx-moon fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Staff -->
    <div class="col-md-4">
        <a href="{{ route('staffs.index') }}" class="text-decoration-none">
            <div class="card dashboard-card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="dashboard-title">Available Staff</div>
                        <div class="dashboard-value text-dark">{{ $availableStaff ?? 0 }}</div>
                    </div>
                    <div class="icon-circle bg-info-light text-info">
                        <i class="bx bx-user-check fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

</div>

@endsection