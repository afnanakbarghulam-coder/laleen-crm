<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\LegalTask;
use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LegalTaskController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->canView('legal_tasks'), 403, 'You do not have permission to access this section.');

        $activeTab = in_array($request->query('tab'), ['work', 'vault'], true)
            ? (string) $request->query('tab')
            : 'work';
        $vaultSubTab = in_array($request->query('vault'), ['branch', 'staff'], true)
            ? (string) $request->query('vault')
            : 'branch';

        $workEntries = LegalTask::with(['creator:id,name', 'updater:id,name'])
            ->orderByRaw('COALESCE(serial_number, 999999) asc')
            ->orderBy('id', 'asc')
            ->get();

        $pendingTasks = $workEntries->where('status', '!=', LegalTask::STATUS_COMPLETED)->values();
        $completedTasks = $workEntries->where('status', LegalTask::STATUS_COMPLETED)->values();

        $statusCounts = [
            'all' => $workEntries->count(),
            LegalTask::STATUS_UNDER_PROCESS => $workEntries->where('status', LegalTask::STATUS_UNDER_PROCESS)->count(),
            LegalTask::STATUS_HOLD => $workEntries->where('status', LegalTask::STATUS_HOLD)->count(),
            LegalTask::STATUS_PENDING => $workEntries->where('status', LegalTask::STATUS_PENDING)->count(),
            LegalTask::STATUS_COMPLETED => $workEntries->where('status', LegalTask::STATUS_COMPLETED)->count(),
        ];

        $legalDocuments = LegalDocument::with('uploader:id,name')
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END asc')
            ->orderBy('expiry_date', 'asc')
            ->orderByDesc('created_at')
            ->get();

        $staffLegalDocuments = StaffDocument::with(['staff:id,name,branch,is_remote', 'uploader:id,name'])
            ->whereIn('category', array_keys(StaffDocument::LEGAL_STAFF_CATEGORIES))
            ->orderByDesc('created_at')
            ->get();

        $staffMembers = Staff::query()
            ->orderBy('name')
            ->get(['id', 'name', 'branch', 'is_remote']);

        $canEditLegal = (bool) ($request->user()?->canEdit('legal_tasks'));
        $todayQatar = Carbon::now('Asia/Qatar');

        return view('legal.tasks', compact(
            'activeTab',
            'vaultSubTab',
            'workEntries',
            'pendingTasks',
            'completedTasks',
            'statusCounts',
            'legalDocuments',
            'staffLegalDocuments',
            'staffMembers',
            'canEditLegal',
            'todayQatar'
        ));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->canEdit('legal_tasks'), 403, 'You do not have permission to access this section.');

        $validated = $request->validate([
            'serial_number' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'nature_of_work' => ['required_without:title', 'nullable', 'string', 'max:255'],
            'title' => ['required_without:nature_of_work', 'nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(LegalTask::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'why_pending' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'deadline' => ['nullable', 'date'],
            'person_responsible' => ['nullable', 'string', 'max:255'],
        ]);

        $workText = trim((string) ($validated['nature_of_work'] ?? $validated['title'] ?? ''));

        LegalTask::create([
            'serial_number' => $validated['serial_number'] ?? null,
            'nature_of_work' => $workText,
            'title' => $workText,
            'status' => $validated['status'] ?? LegalTask::STATUS_PENDING,
            'remarks' => isset($validated['remarks']) && trim((string) $validated['remarks']) !== ''
                ? trim((string) $validated['remarks'])
                : null,
            'why_pending' => isset($validated['why_pending']) && trim((string) $validated['why_pending']) !== ''
                ? trim((string) $validated['why_pending'])
                : null,
            'description' => $validated['description'] ?? null,
            'deadline' => $validated['deadline'] ?? null,
            'person_responsible' => $validated['person_responsible'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('legal.tasks.index', ['tab' => 'work'])
            ->with('success', 'Work entry added to Laleen Salon Work.');
    }

    public function update(Request $request, LegalTask $legalTask)
    {
        abort_unless($request->user()?->canEdit('legal_tasks'), 403, 'You do not have permission to access this section.');

        $validated = $request->validate([
            'serial_number' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99999'],
            'nature_of_work' => ['sometimes', 'required', 'string', 'max:255'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(array_keys(LegalTask::STATUSES))],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'why_pending' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'person_responsible' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('nature_of_work', $validated) && !array_key_exists('title', $validated)) {
            $validated['title'] = trim((string) $validated['nature_of_work']);
            $validated['nature_of_work'] = $validated['title'];
        } elseif (array_key_exists('title', $validated) && !array_key_exists('nature_of_work', $validated)) {
            $validated['nature_of_work'] = trim((string) $validated['title']);
            $validated['title'] = $validated['nature_of_work'];
        }

        foreach (['remarks', 'why_pending'] as $textCol) {
            if (array_key_exists($textCol, $validated)) {
                $trimmed = trim((string) ($validated[$textCol] ?? ''));
                $validated[$textCol] = $trimmed !== '' ? $trimmed : null;
            }
        }

        $validated['updated_by'] = Auth::id();
        $legalTask->update($validated);

        return redirect()
            ->route('legal.tasks.index', ['tab' => 'work'])
            ->with('success', 'Legal work entry updated.');
    }

    public function destroy(Request $request, LegalTask $legalTask)
    {
        abort_unless($request->user()?->canEdit('legal_tasks'), 403, 'You do not have permission to access this section.');

        $legalTask->delete();

        return redirect()
            ->route('legal.tasks.index', ['tab' => 'work'])
            ->with('success', 'Legal work entry deleted.');
    }

    /**
     * Upload a Branch or Company Legal Document into private `local` disk storage.
     */
    public function storeDocument(Request $request)
    {
        abort_unless($request->user()?->canEdit('legal_tasks'), 403, 'You do not have permission to upload legal documents.');

        $validated = $request->validate([
            'scope' => ['required', Rule::in(array_keys(LegalDocument::SCOPES))],
            'category' => ['required', Rule::in(array_keys(LegalDocument::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $uploadedFile = $request->file('file');
        $ext = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->guessExtension() ?: 'pdf');
        $storedName = Str::uuid()->toString() . '.' . $ext;
        $scope = (string) $validated['scope'];
        $relativeDir = 'private/legal-documents/' . $scope;
        $storedPath = $uploadedFile->storeAs($relativeDir, $storedName, 'local');

        LegalDocument::create([
            'scope' => $scope,
            'category' => $validated['category'],
            'title' => trim((string) $validated['title']),
            'document_number' => isset($validated['document_number']) && trim((string) $validated['document_number']) !== ''
                ? trim((string) $validated['document_number'])
                : null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                ? trim((string) $validated['notes'])
                : null,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName() ?: $storedName,
            'mime_type' => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $uploadedFile->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return redirect()
            ->route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'branch'])
            ->with('success', 'Branch/Company legal document uploaded to vault.');
    }

    /**
     * Policy-locked private file download for Branch & Company Legal Documents.
     * Strictly limited to Admins and users with `legal_tasks` view permission (HTTP 403 otherwise).
     */
    public function downloadDocument(Request $request, LegalDocument $legalDocument)
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->canView('legal_tasks')), 403, 'You do not have permission to access this legal document.');

        if (!Storage::disk('local')->exists($legalDocument->file_path)) {
            abort(404, 'Legal document file could not be located on disk.');
        }

        $safeFilename = basename($legalDocument->original_filename ?: ($legalDocument->title . '.pdf'));
        $absolutePath = Storage::disk('local')->path($legalDocument->file_path);

        if ($request->boolean('inline')) {
            return response()->file($absolutePath, [
                'Content-Type' => $legalDocument->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes($safeFilename) . '"',
            ]);
        }

        return response()->download($absolutePath, $safeFilename, [
            'Content-Type' => $legalDocument->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function destroyDocument(Request $request, LegalDocument $legalDocument)
    {
        abort_unless($request->user()?->canEdit('legal_tasks'), 403, 'You do not have permission to delete legal documents.');

        if ($legalDocument->file_path && Storage::disk('local')->exists($legalDocument->file_path)) {
            Storage::disk('local')->delete($legalDocument->file_path);
        }

        $legalDocument->delete();

        return redirect()
            ->route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'branch'])
            ->with('success', 'Legal document deleted from vault.');
    }

    /**
     * Upload a Staff Legal Document (`6-Month Agreement Paper`, `Health Certificate / Health Card`, QID, Passport, etc.)
     * directly into `staff_documents` as `verified` so it immediately appears on that employee's `/my-profile -> Documents`.
     */
    public function storeStaffDocument(Request $request)
    {
        abort_unless($request->user()?->canEdit('legal_tasks'), 403, 'You do not have permission to upload staff legal documents.');

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'category' => ['required', Rule::in(array_keys(StaffDocument::LEGAL_STAFF_CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $staff = Staff::findOrFail((int) $validated['staff_id']);
        $uploadedFile = $request->file('file');
        $ext = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->guessExtension() ?: 'pdf');
        $storedName = Str::uuid()->toString() . '.' . $ext;
        $relativeDir = 'private/staff-documents/' . $staff->id;
        $storedPath = $uploadedFile->storeAs($relativeDir, $storedName, 'local');

        StaffDocument::create([
            'staff_id' => $staff->id,
            'category' => $validated['category'],
            'title' => trim((string) $validated['title']),
            'document_number' => isset($validated['document_number']) && trim((string) $validated['document_number']) !== ''
                ? trim((string) $validated['document_number'])
                : null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName() ?: $storedName,
            'mime_type' => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $uploadedFile->getSize(),
            'uploaded_by' => Auth::id(),
            'verification_status' => StaffDocument::STATUS_VERIFIED,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        return redirect()
            ->route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'staff'])
            ->with('success', "Uploaded verified legal document for {$staff->name} (synced to their portal profile).");
    }

    public function destroyStaffDocument(Request $request, StaffDocument $staffDocument)
    {
        abort_unless($request->user()?->canEdit('legal_tasks'), 403, 'You do not have permission to delete staff legal documents.');
        $this->authorize('delete', $staffDocument);

        if ($staffDocument->file_path && Storage::disk('local')->exists($staffDocument->file_path)) {
            Storage::disk('local')->delete($staffDocument->file_path);
        }

        $staffDocument->delete();

        return redirect()
            ->route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'staff'])
            ->with('success', 'Staff legal document removed.');
    }
}
