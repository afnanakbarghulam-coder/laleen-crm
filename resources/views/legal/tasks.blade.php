@extends('layouts.app')

@section('title', 'Legal Tasks — Laleen Ops')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="mb-1 fw-bold">
                <i class="bx bx-briefcase-alt-2 me-2 text-primary"></i>Legal Tasks
            </h4>
            <p class="text-muted mb-0 small">Manage legal deadlines, compliance filings, and assigned responsibilities.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bx bx-plus me-1"></i>Create New Task
        </button>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Pending Tasks Section --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">
                <i class="bx bx-time-five text-warning me-2"></i>Pending Tasks
            </h5>
            <span class="badge btn-primary rounded-pill">{{ $pendingTasks->count() }} Pending</span>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Task</th>
                        <th>Person Responsible</th>
                        <th>Deadline</th>
                        <th>Created By</th>
                        <th class="text-end" style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingTasks as $task)
                        @php
                            $isOverdue = $task->deadline && $task->deadline->lt(today());
                            $isDueToday = $task->deadline && $task->deadline->isToday();
                        @endphp
                        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                            <td class="text-wrap" style="min-width: 240px;">
                                <div class="fw-semibold {{ $isOverdue ? 'text-danger' : '' }}">{{ $task->title }}</div>
                                @if ($task->description)
                                    <div class="text-muted small mt-1">{{ $task->description }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($task->person_responsible)
                                    <span class="badge btn-primary">
                                        <i class="bx bx-user me-1"></i>{{ $task->person_responsible }}
                                    </span>
                                @else
                                    <span class="text-muted small">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $isOverdue ? 'text-danger fw-bold' : ($isDueToday ? 'text-warning fw-semibold' : '') }}">
                                    <i class="bx bx-calendar me-1"></i>{{ $task->deadline->format('d M Y') }}
                                </span>
                                @if ($isOverdue)
                                    <span class="badge bg-danger ms-1">Overdue</span>
                                @elseif ($isDueToday)
                                    <span class="badge bg-warning text-dark ms-1">Due Today</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted small">{{ $task->creator?->name ?? '—' }}</span>
                            </td>
                            <td class="text-end">
                                {{-- One-click mark as completed --}}
                                <form action="{{ route('legal.tasks.update', $task) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-sm btn-success" title="Mark as Completed">
                                        <i class="bx bx-check me-1"></i>Complete
                                    </button>
                                </form>

                                {{-- Edit button --}}
                                <button type="button"
                                    class="btn btn-sm btn-outline-secondary edit-task-btn"
                                    data-id="{{ $task->id }}"
                                    data-title="{{ $task->title }}"
                                    data-description="{{ $task->description }}"
                                    data-deadline="{{ $task->deadline->format('Y-m-d') }}"
                                    data-person="{{ $task->person_responsible }}"
                                    data-status="{{ $task->status }}"
                                    title="Edit Task">
                                    <i class="bx bx-edit-alt"></i>
                                </button>

                                {{-- Delete button --}}
                                <form action="{{ route('legal.tasks.destroy', $task) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure you want to delete this task?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Task">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bx bx-check-shield fs-3 d-block mb-1"></i>
                                No pending legal tasks.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Completed Tasks Section --}}
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">
                <i class="bx bx-check-circle text-success me-2"></i>Completed Tasks
            </h5>
            <span class="badge btn-primary rounded-pill">{{ $completedTasks->count() }} Completed</span>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Task</th>
                        <th>Person Responsible</th>
                        <th>Deadline</th>
                        <th>Created By</th>
                        <th class="text-end" style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($completedTasks as $task)
                        <tr>
                            <td class="text-wrap" style="min-width: 240px;">
                                <div class="fw-semibold text-decoration-line-through text-muted">{{ $task->title }}</div>
                                @if ($task->description)
                                    <div class="text-muted small mt-1">{{ $task->description }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($task->person_responsible)
                                    <span class="badge btn-primary">
                                        <i class="bx bx-user me-1"></i>{{ $task->person_responsible }}
                                    </span>
                                @else
                                    <span class="text-muted small">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted">
                                    <i class="bx bx-calendar me-1"></i>{{ $task->deadline->format('d M Y') }}
                                </span>
                                <span class="badge btn-primary ms-1">Completed</span>
                            </td>
                            <td>
                                <span class="text-muted small">{{ $task->creator?->name ?? '—' }}</span>
                            </td>
                            <td class="text-end">
                                {{-- Reopen / toggle back to pending --}}
                                <form action="{{ route('legal.tasks.update', $task) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="pending">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Reopen Task">
                                        <i class="bx bx-undo"></i>
                                    </button>
                                </form>

                                {{-- Delete button --}}
                                <form action="{{ route('legal.tasks.destroy', $task) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure you want to delete this task?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Task">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No completed tasks yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create New Task Modal --}}
    <div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('legal.tasks.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createTaskModalLabel">Create New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createTitle" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="createTitle" class="form-control"
                            placeholder="e.g. Renew Commercial Registration" value="{{ old('title') }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="createDescription" class="form-label">Description</label>
                        <textarea name="description" id="createDescription" rows="3" class="form-control"
                            placeholder="Add details, notes, or required documents...">{{ old('description') }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="createDeadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                            <input type="date" name="deadline" id="createDeadline" class="form-control"
                                value="{{ old('deadline') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="createPersonResponsible" class="form-label">Person Responsible</label>
                            <input type="text" name="person_responsible" id="createPersonResponsible" class="form-control"
                                placeholder="e.g. Sarah / Legal Advisor" value="{{ old('person_responsible') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Task Modal --}}
    <div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="editTaskForm" method="POST" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editTaskModalLabel">Edit Legal Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editTitle" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="editTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea name="description" id="editDescription" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editDeadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                            <input type="date" name="deadline" id="editDeadline" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editPersonResponsible" class="form-label">Person Responsible</label>
                            <input type="text" name="person_responsible" id="editPersonResponsible" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.edit-task-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const taskId = this.getAttribute('data-id');
                document.getElementById('editTaskForm').action = `/legal/tasks/${taskId}`;
                document.getElementById('editTitle').value = this.getAttribute('data-title') || '';
                document.getElementById('editDescription').value = this.getAttribute('data-description') || '';
                document.getElementById('editDeadline').value = this.getAttribute('data-deadline') || '';
                document.getElementById('editPersonResponsible').value = this.getAttribute('data-person') || '';

                const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
                modal.show();
            });
        });
    </script>
@endsection
