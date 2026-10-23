<?php

namespace App\Http\Controllers;

use App\Models\LegalTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegalTaskController extends Controller
{
    public function index()
    {
        $tasks = LegalTask::with('creator')
            ->orderBy('deadline', 'asc')
            ->get();

        $pendingTasks = $tasks->where('status', 'pending')->values();
        $completedTasks = $tasks->where('status', 'completed')->values();

        return view('legal.tasks', compact('pendingTasks', 'completedTasks'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'required|date',
            'person_responsible' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,completed',
        ]);

        LegalTask::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'deadline' => $validated['deadline'],
            'status' => $validated['status'] ?? 'pending',
            'person_responsible' => $validated['person_responsible'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Legal task created successfully.');
    }

    public function update(Request $request, LegalTask $legalTask)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:pending,completed',
            'person_responsible' => 'nullable|string|max:255',
        ]);

        $legalTask->update($validated);

        return back()->with('success', 'Legal task updated successfully.');
    }

    public function destroy(LegalTask $legalTask)
    {
        $legalTask->delete();

        return back()->with('success', 'Legal task deleted successfully.');
    }
}
