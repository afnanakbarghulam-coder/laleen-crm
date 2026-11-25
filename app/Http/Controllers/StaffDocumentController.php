<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffDocumentController extends Controller
{
    /**
     * Admin-only: return JSON listing of a staff member's private documents for the
     * Directory & Profiles Documents drawer/modal.
     */
    public function index(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can manage staff documents.');

        $todayQatar = Carbon::now('Asia/Qatar')->startOfDay();
        $docs = $staff->documents()
            ->with(['uploader:id,name', 'verifier:id,name'])
            ->orderByRaw("CASE verification_status WHEN 'pending_review' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StaffDocument $doc) => $this->serializeDocument($doc, $todayQatar))
            ->values();

        return response()->json([
            'success' => true,
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'location_label' => $staff->location_label,
                'annual_leave_days' => (int) ($staff->annual_leave_days ?? 21),
            ],
            'documents' => $docs,
        ]);
    }

    /**
     * Admin-only: upload a private staff document (any category, saved immediately as `verified`)
     * into Laravel's non-public `local` disk (`private/staff-documents/{staff_id}/`).
     */
    public function store(Request $request, Staff $staff)
    {
        Gate::authorize('create', StaffDocument::class);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(StaffDocument::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $document = $this->persistPrivateDocument(
            staff: $staff,
            validated: $validated,
            file: $request->file('file'),
            uploadedById: $request->user()?->id,
            verificationStatus: StaffDocument::STATUS_VERIFIED,
            verifiedById: $request->user()?->id,
            verifiedAt: now()
        );

        $message = "Uploaded \"{$document->title}\" for {$staff->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'document' => $this->serializeDocument($document->load(['uploader:id,name', 'verifier:id,name'])),
            ], 201);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Staff self-upload (`POST /my-profile/documents`):
     * Strictly scoped to `auth()->user()->staff` (NEVER accepts `staff_id` from request),
     * restricted to personal ID/medical document categories only, and saved as `pending_review`.
     */
    public function storeSelfUpload(Request $request)
    {
        $user = $request->user();
        $staff = $user?->staff()->first();

        if (!$staff) {
            $msg = 'Your account is not linked to a Staff HR Profile yet. Please contact management.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('portal.profile', ['tab' => 'documents'])->with('error', $msg);
        }

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(StaffDocument::SELF_UPLOAD_CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'category.in' => 'You can only upload personal documents (QID / Government ID, Passport, National ID, Health Card, or Medical Certificate).',
        ]);

        $document = $this->persistPrivateDocument(
            staff: $staff,
            validated: $validated,
            file: $request->file('file'),
            uploadedById: $user->id,
            verificationStatus: StaffDocument::STATUS_PENDING_REVIEW,
            verifiedById: null,
            verifiedAt: null
        );

        $message = "Uploaded \"{$document->title}\" — pending HR verification.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'document' => $this->serializeDocument($document->load(['uploader:id,name', 'verifier:id,name'])),
            ], 201);
        }

        return redirect()->route('portal.profile', ['tab' => 'documents'])->with('success', $message);
    }

    /**
     * Admin-only: verify a pending staff-uploaded document (`POST /staff-documents/{document}/verify`).
     */
    public function verify(Request $request, StaffDocument $document)
    {
        Gate::authorize('verify', $document);

        $document->forceFill([
            'verification_status' => StaffDocument::STATUS_VERIFIED,
            'verified_by' => $request->user()?->id,
            'verified_at' => now(),
        ])->save();

        $message = "Verified \"{$document->title}\".";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'document' => $this->serializeDocument($document->fresh(['uploader:id,name', 'verifier:id,name'])),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Policy-protected download/inline preview for a private staff document.
     * ONLY Admin or the exact employee owning the document may access; all others receive 403.
     */
    public function download(Request $request, StaffDocument $document)
    {
        Gate::authorize('view', $document);

        if (!$document->file_path || !Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Document file not found.');
        }

        $safeFilename = str_replace(['"', "\r", "\n"], '', $document->original_filename ?: ('document_' . $document->id));
        $headers = [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        if ($request->boolean('inline')) {
            return Storage::disk('local')->response($document->file_path, $safeFilename, $headers, 'inline');
        }

        return Storage::disk('local')->download($document->file_path, $safeFilename, $headers);
    }

    /**
     * Staff self-delete (`DELETE /my-profile/documents/{document}`):
     * Strictly allows deleting ONLY own `pending_review` self-uploaded documents;
     * blocks `verified`, HR-uploaded, or other staff's documents with HTTP 403.
     */
    public function destroySelfUpload(Request $request, StaffDocument $document)
    {
        Gate::authorize('delete', $document);

        return $this->deleteDocumentAndRespond($request, $document, redirectToPortal: true);
    }

    /**
     * Admin-only: delete both the private stored file and its DB row.
     */
    public function destroy(Request $request, StaffDocument $document)
    {
        Gate::authorize('delete', $document);

        return $this->deleteDocumentAndRespond($request, $document, redirectToPortal: false);
    }

    private function persistPrivateDocument(
        Staff $staff,
        array $validated,
        \Illuminate\Http\UploadedFile $file,
        ?int $uploadedById,
        string $verificationStatus,
        ?int $verifiedById,
        ?Carbon $verifiedAt
    ): StaffDocument {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = 'private/staff-documents/' . $staff->id;
        $storedName = Str::uuid()->toString() . '.' . $ext;
        $storedPath = Storage::disk('local')->putFileAs($directory, $file, $storedName);

        return StaffDocument::create([
            'staff_id' => $staff->id,
            'category' => $validated['category'],
            'title' => trim($validated['title']),
            'document_number' => isset($validated['document_number']) && trim((string) $validated['document_number']) !== ''
                ? trim((string) $validated['document_number'])
                : null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'file_path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'file_size' => (int) ($file->getSize() ?: 0),
            'uploaded_by' => $uploadedById,
            'verification_status' => $verificationStatus,
            'verified_by' => $verifiedById,
            'verified_at' => $verifiedAt,
        ]);
    }

    private function deleteDocumentAndRespond(Request $request, StaffDocument $document, bool $redirectToPortal)
    {
        $title = $document->title;
        $filePath = $document->file_path;

        $document->delete();

        if ($filePath && Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Deleted document \"{$title}\".",
            ]);
        }

        if ($redirectToPortal) {
            return redirect()->route('portal.profile', ['tab' => 'documents'])->with('success', "Deleted document \"{$title}\".");
        }

        return redirect()->back()->with('success', "Deleted document \"{$title}\".");
    }

    private function serializeDocument(StaffDocument $doc, ?Carbon $todayQatar = null): array
    {
        $exp = $doc->expiryStatus($todayQatar);
        $ver = $doc->verificationStatusBadge();

        return [
            'id' => $doc->id,
            'staff_id' => $doc->staff_id,
            'category' => $doc->category,
            'category_label' => $doc->categoryLabel(),
            'title' => $doc->title,
            'document_number' => $doc->document_number,
            'issue_date' => $doc->issue_date?->format('Y-m-d'),
            'issue_date_label' => $doc->issue_date?->format('d M Y'),
            'expiry_date' => $doc->expiry_date?->format('Y-m-d'),
            'expiry_date_label' => $doc->expiry_date?->format('d M Y'),
            'expiry_status' => $exp,
            'verification_status' => $doc->verification_status ?? StaffDocument::STATUS_VERIFIED,
            'verification_badge' => $ver,
            'is_pending_review' => $doc->isPendingReview(),
            'verified_by' => $doc->verifier?->name,
            'verified_at_label' => $doc->verified_at?->copy()->timezone('Asia/Qatar')->format('d M Y'),
            'original_filename' => $doc->original_filename,
            'mime_type' => $doc->mime_type,
            'file_size_label' => $doc->formattedFileSize(),
            'uploaded_by' => $doc->uploader?->name ?? 'Admin',
            'download_url' => route('staff-documents.download', $doc),
            'inline_url' => route('staff-documents.download', ['document' => $doc->id, 'inline' => 1]),
            'verify_url' => route('staff-documents.verify', $doc),
            'delete_url' => route('staff-documents.destroy', $doc),
        ];
    }
}
