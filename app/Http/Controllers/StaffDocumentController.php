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
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (StaffDocument $doc) use ($todayQatar) {
                $exp = $doc->expiryStatus($todayQatar);

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
                    'original_filename' => $doc->original_filename,
                    'mime_type' => $doc->mime_type,
                    'file_size_label' => $doc->formattedFileSize(),
                    'uploaded_by' => $doc->uploader?->name ?? 'Admin',
                    'download_url' => route('staff-documents.download', $doc),
                    'inline_url' => route('staff-documents.download', ['document' => $doc->id, 'inline' => 1]),
                    'delete_url' => route('staff-documents.destroy', $doc),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'location_label' => $staff->location_label,
                'annual_leave_days' => (int) ($staff->annual_leave_days ?? 21),
                'sick_leave_days' => (int) ($staff->sick_leave_days ?? 14),
            ],
            'documents' => $docs,
        ]);
    }

    /**
     * Admin-only: upload a private staff document into Laravel's non-public `local` disk
     * (`private/staff-documents/{staff_id}/`).
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

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = 'private/staff-documents/' . $staff->id;
        $storedName = Str::uuid()->toString() . '.' . $ext;
        $storedPath = Storage::disk('local')->putFileAs($directory, $file, $storedName);

        $document = StaffDocument::create([
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
            'uploaded_by' => $request->user()?->id,
        ]);

        $exp = $document->expiryStatus();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Uploaded \"{$document->title}\" for {$staff->name}.",
                'document' => [
                    'id' => $document->id,
                    'category' => $document->category,
                    'category_label' => $document->categoryLabel(),
                    'title' => $document->title,
                    'document_number' => $document->document_number,
                    'issue_date_label' => $document->issue_date?->format('d M Y'),
                    'expiry_date_label' => $document->expiry_date?->format('d M Y'),
                    'expiry_status' => $exp,
                    'original_filename' => $document->original_filename,
                    'file_size_label' => $document->formattedFileSize(),
                    'download_url' => route('staff-documents.download', $document),
                    'inline_url' => route('staff-documents.download', ['document' => $document->id, 'inline' => 1]),
                    'delete_url' => route('staff-documents.destroy', $document),
                ],
            ], 201);
        }

        return redirect()->back()->with('success', "Uploaded \"{$document->title}\" for {$staff->name}.");
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
     * Admin-only: delete both the private stored file and its DB row.
     */
    public function destroy(Request $request, StaffDocument $document)
    {
        Gate::authorize('delete', $document);

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

        return redirect()->back()->with('success', "Deleted document \"{$title}\".");
    }
}
