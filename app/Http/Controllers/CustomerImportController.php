<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ClientImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class CustomerImportController extends Controller
{
    private const UPLOAD_SUBDIR = 'app/imports/uploads';
    private const MAX_UPLOAD_KB = 10240; // 10 MB

    public function __construct(
        private readonly ClientImportService $importService
    ) {}

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        return view('customers.import', [
            'existingClientCount' => Customer::count(),
            'chunkSize' => ClientImportService::CHUNK_SIZE,
            'defaultImportSource' => ClientImportService::DEFAULT_IMPORT_SOURCE,
        ]);
    }

    /**
     * Stage 1: Upload CSV privately inside storage/app/imports/uploads/ and run
     * a full Dry-Run preview with ZERO database writes.
     */
    public function dryRun(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'csv_file' => ['required', 'file', 'max:' . self::MAX_UPLOAD_KB],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $uploadedFile = $request->file('csv_file');
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload a valid .csv or .txt file.',
                'errors' => ['csv_file' => ['Please upload a valid .csv or .txt file.']],
            ], 422);
        }

        $uploadDir = storage_path(self::UPLOAD_SUBDIR);
        if (!is_dir($uploadDir)) {
            File::ensureDirectoryExists($uploadDir, 0755, true);
        }

        // Prune abandoned uploads older than 24 hours
        $this->pruneStaleUploads($uploadDir);

        $token = (string) Str::uuid();
        $storedFilename = $token . '.csv';
        $uploadedFile->move($uploadDir, $storedFilename);
        $storedPath = $uploadDir . DIRECTORY_SEPARATOR . $storedFilename;

        $importSource = trim((string) $request->input('source', ClientImportService::DEFAULT_IMPORT_SOURCE))
            ?: ClientImportService::DEFAULT_IMPORT_SOURCE;

        try {
            $summary = $this->importService->importFromCsv(
                filePath: $storedPath,
                dryRun: true,
                normalizeExisting: true,
                importSource: $importSource,
            );
        } catch (Throwable $e) {
            if (is_file($storedPath)) {
                @unlink($storedPath);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'file_size_kb' => round((@filesize($storedPath) ?: 0) / 1024, 1),
            'import_source' => $importSource,
            'summary' => [
                'dry_run' => true,
                'total_csv_rows' => $summary['total_csv_rows'],
                'normalized' => $summary['normalized'],
                'already_normalized' => $summary['already_normalized'],
                'collisions_merged' => $summary['collisions_merged'],
                'updated' => $summary['updated'],
                'created' => $summary['created'],
                'skipped' => $summary['skipped'],
                'skipped_unchanged' => $summary['skipped_unchanged'],
                'skipped_invalid' => $summary['skipped_invalid'],
                'skipped_duplicate_in_csv' => $summary['skipped_duplicate_in_csv'],
                'collisions' => array_slice($summary['collisions'], 0, 50),
            ],
        ]);
    }

    /**
     * Stage 2A: Normalize existing CRM clients to unified E.164 (+974 / +<cc>) once
     * before sequential 500-row CSV chunks begin.
     */
    public function normalizeExisting(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $storedPath = $this->resolveUploadedCsvPath($validated['token']);
        if ($storedPath === null) {
            return response()->json([
                'success' => false,
                'message' => 'Uploaded CSV file was not found or has expired. Please upload and run Dry-Run again.',
            ], 404);
        }

        try {
            $summary = $this->importService->normalizeExistingCustomers(dryRun: false);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to normalize existing client phone numbers: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'total_existing' => $summary['total_existing'],
                'normalized' => $summary['normalized'],
                'already_normalized' => $summary['already_normalized'],
                'collisions_merged' => $summary['collisions_merged'],
                'skipped_invalid' => $summary['skipped_invalid'],
                'collisions' => array_slice($summary['collisions'], 0, 50),
            ],
        ]);
    }

    /**
     * Stage 2B: Process a single 500-row slice of the uploaded CSV file.
     * Automatically deletes the private upload as soon as the final chunk finishes.
     */
    public function processChunk(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . ClientImportService::CHUNK_SIZE],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $storedPath = $this->resolveUploadedCsvPath($validated['token']);
        if ($storedPath === null) {
            return response()->json([
                'success' => false,
                'message' => 'Uploaded CSV file was not found or has already been cleaned up.',
            ], 404);
        }

        $offset = (int) $validated['offset'];
        $limit = (int) ($validated['limit'] ?? ClientImportService::CHUNK_SIZE);
        $importSource = trim((string) ($validated['source'] ?? ClientImportService::DEFAULT_IMPORT_SOURCE))
            ?: ClientImportService::DEFAULT_IMPORT_SOURCE;

        try {
            $chunkResult = $this->importService->importCsvChunk(
                filePath: $storedPath,
                offset: $offset,
                limit: $limit,
                importSource: $importSource,
            );
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chunk import failed at offset ' . $offset . ': ' . $e->getMessage(),
            ], 500);
        }

        $fileDeleted = false;
        if (!$chunkResult['has_more']) {
            $fileDeleted = @unlink($storedPath);
        }

        return response()->json([
            'success' => true,
            'chunk' => $chunkResult,
            'file_deleted' => $fileDeleted,
            'total_clients_now' => !$chunkResult['has_more'] ? Customer::count() : null,
        ]);
    }

    /**
     * Cancel an abandoned Dry-Run / import and immediately delete the uploaded CSV file.
     */
    public function cancel(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $storedPath = $this->resolveUploadedCsvPath($validated['token']);
        $deleted = false;
        if ($storedPath !== null && is_file($storedPath)) {
            $deleted = @unlink($storedPath);
        }

        return response()->json([
            'success' => true,
            'file_deleted' => $deleted,
            'message' => $deleted ? 'Uploaded CSV file deleted.' : 'No pending CSV file found.',
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can import client CSV files.');
    }

    private function resolveUploadedCsvPath(string $token): ?string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
            return null;
        }

        $path = storage_path(self::UPLOAD_SUBDIR . DIRECTORY_SEPARATOR . strtolower($token) . '.csv');

        return is_file($path) && is_readable($path) ? $path : null;
    }

    private function pruneStaleUploads(string $uploadDir): void
    {
        $cutoff = time() - 86400;
        foreach (glob($uploadDir . DIRECTORY_SEPARATOR . '*.csv') ?: [] as $file) {
            if (is_file($file) && (@filemtime($file) ?: time()) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
