<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ClientImportService
{
    public const CHUNK_SIZE = 500;
    public const DEFAULT_IMPORT_SOURCE = 'legacy_excel_2026_11';
    public const LEGACY_NOTE_PREFIX = '[Legacy Import 2026-11]';

    /**
     * Generic placeholder names that should be upgraded when a real CSV name exists.
     */
    private const GENERIC_PLACEHOLDERS = [
        'walk-in',
        'walk in',
        'walkin',
        'guest',
        'unnamed',
        'unnamed client',
        'unknown',
        'client',
        'customer',
        'n/a',
        'na',
        'none',
        'null',
    ];

    /**
     * Tables carrying a `customer_id` foreign key that must be reassigned when
     * merging duplicate client records during phone normalization.
     */
    private const CUSTOMER_FK_TABLES = [
        'appointments',
        'sales',
        'leads',
        'ad_lead_entries',
        'client_packages',
        'client_freebie_credits',
        'loyalty_transactions',
        'staff_complaints',
    ];

    /**
     * Determine whether a stored or imported client name is blank or a junk placeholder
     * (e.g., '', ',', '.', '..', '-', '—', '?', '????', punctuation-only, 'Walk-in', 'Unknown').
     */
    public static function isPlaceholderName(?string $name): bool
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            return true;
        }

        // Reject names that contain zero Unicode letters or digits (pure punctuation/symbols like ",", ".", "????", "---")
        if (!preg_match('/[\p{L}\p{N}]/u', $trimmed)) {
            return true;
        }

        return in_array(mb_strtolower($trimmed), self::GENERIC_PLACEHOLDERS, true);
    }

    /**
     * Clean a CSV name value, returning '' when blank or junk punctuation.
     */
    public static function cleanCsvName(?string $rawName): string
    {
        $trimmed = trim((string) preg_replace('/\s+/u', ' ', (string) $rawName));

        return self::isPlaceholderName($trimmed) ? '' : $trimmed;
    }

    /**
     * Clean stray WhatsApp card prefixes from CSV `last_service` (e.g., "💆‍♀️ Service: Haircut",
     * "💆‍♀️ Complimentary Service: Collage hair spa", or "Service: Blow dry").
     */
    public static function cleanLegacyService(?string $rawService): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $rawService));
        if ($clean === '') {
            return '';
        }

        $clean = (string) preg_replace(
            '/^(?:[\p{So}\x{200D}\x{FE0F}\x{2640}\x{2642}\s•\-\*]+)?(?:(?:complimentary|selected|booked)\s+)?services?\s*:\s*/iu',
            '',
            $clean
        );
        $clean = trim($clean);

        if ($clean === '' || mb_strtolower($clean) === 'pending') {
            return '';
        }

        return mb_substr($clean, 0, 120);
    }

    /**
     * Build a compact, idempotent legacy history note line from CSV columns.
     *
     * @param  array<string, string>  $row
     */
    public static function buildLegacyHistoryNote(array $row): ?string
    {
        $parts = [];

        $totalBookings = trim((string) ($row['total_bookings'] ?? ''));
        if ($totalBookings !== '' && is_numeric($totalBookings) && (int) $totalBookings > 0) {
            $parts[] = 'Bookings: ' . (int) $totalBookings;
        }

        $firstDate = trim((string) ($row['first_booking_date'] ?? ''));
        $lastDate = trim((string) ($row['last_booking_date'] ?? ''));
        if ($lastDate !== '') {
            $parts[] = 'Last Visit: ' . $lastDate;
        } elseif ($firstDate !== '') {
            $parts[] = 'First Visit: ' . $firstDate;
        }

        $lastService = self::cleanLegacyService($row['last_service'] ?? null);
        if ($lastService !== '') {
            $parts[] = 'Last Service: ' . $lastService;
        }

        $visitedBranches = trim((string) ($row['visited_branches'] ?? ''));
        if ($visitedBranches !== '') {
            $parts[] = 'Visited: ' . $visitedBranches;
        }

        $estSpend = trim((string) ($row['est_booking_spend_qar'] ?? ''));
        if ($estSpend !== '' && is_numeric($estSpend) && (float) $estSpend > 0) {
            $parts[] = 'Est Spend: ' . rtrim(rtrim(number_format((float) $estSpend, 2, '.', ''), '0'), '.') . ' QAR';
        }

        if (empty($parts)) {
            return null;
        }

        return self::LEGACY_NOTE_PREFIX . ' ' . implode(' | ', $parts);
    }

    /**
     * Append the legacy history snippet to existing notes without overwriting human notes
     * and without duplicating the legacy block on re-runs.
     */
    public static function mergeNotes(?string $existingNotes, ?string $legacyNote): ?string
    {
        $existing = trim((string) $existingNotes);
        if ($legacyNote === null || trim($legacyNote) === '') {
            return $existing !== '' ? $existing : null;
        }

        if ($existing !== '' && str_contains($existing, self::LEGACY_NOTE_PREFIX)) {
            return $existing;
        }

        if ($existing === '') {
            return $legacyNote;
        }

        return $existing . "\n" . $legacyNote;
    }

    /**
     * Normalize all existing CRM customers to unified E.164 format (+974XXXXXXXX / +<cc><number>),
     * safely resolving phone collisions by merging duplicates without losing appointments, sales,
     * loyalty points, or packages.
     *
     * @return array{
     *     dry_run: bool,
     *     total_existing: int,
     *     normalized: int,
     *     already_normalized: int,
     *     collisions_merged: int,
     *     skipped_invalid: int,
     *     collisions: list<array<string, mixed>>
     * }
     */
    public function normalizeExistingCustomers(bool $dryRun = false): array
    {
        $selectCols = ['id', 'name', 'phone', 'email', 'notes', 'allergies', 'loyalty_points'];
        if (Schema::hasColumn('customers', 'branch')) {
            $selectCols[] = 'branch';
        }

        $all = DB::table('customers')
            ->orderBy('id')
            ->get($selectCols);

        $totalExisting = $all->count();
        $normalized = 0;
        $alreadyNormalized = 0;
        $collisionsMerged = 0;
        $skippedInvalid = 0;
        $collisions = [];

        // Group every existing row by its target E.164 phone
        $groups = [];
        foreach ($all as $row) {
            $e164 = Customer::normalizePhoneE164($row->phone);
            if ($e164 === '') {
                $skippedInvalid++;
                continue;
            }
            $groups[$e164][] = $row;
        }

        $now = now();

        foreach ($groups as $e164 => $rows) {
            if (count($rows) === 1) {
                $row = $rows[0];
                if ((string) $row->phone === $e164) {
                    $alreadyNormalized++;
                    continue;
                }

                $normalized++;
                if (!$dryRun) {
                    DB::table('customers')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $e164,
                            'updated_at' => $now,
                        ]);
                }

                continue;
            }

            // Collision: multiple existing rows normalize to the exact same E.164 phone.
            // Pick the winning canonical customer (preferring the row already holding E.164,
            // or highest appointment/sale activity, tie-breaking by lowest id).
            $winner = $this->pickCollisionWinner($rows, $e164);
            $losers = array_values(array_filter($rows, fn ($r) => (int) $r->id !== (int) $winner->id));

            $winnerWasNormalized = ((string) $winner->phone === $e164);
            if ($winnerWasNormalized) {
                $alreadyNormalized++;
            } else {
                $normalized++;
            }

            foreach ($losers as $loser) {
                $collisionsMerged++;
                $collisionRecord = [
                    'e164' => $e164,
                    'winner_id' => (int) $winner->id,
                    'winner_phone' => (string) $winner->phone,
                    'merged_duplicate_id' => (int) $loser->id,
                    'merged_duplicate_phone' => (string) $loser->phone,
                ];
                $collisions[] = $collisionRecord;

                Log::warning('ClientImportService phone collision detected during normalization', $collisionRecord);

                if (!$dryRun) {
                    $this->mergeDuplicateCustomer((int) $winner->id, (int) $loser->id, $e164);
                    $winner->phone = $e164;
                }
            }

            if (!$dryRun && (string) $winner->phone !== $e164) {
                DB::table('customers')
                    ->where('id', $winner->id)
                    ->update([
                        'phone' => $e164,
                        'updated_at' => $now,
                    ]);
            }
        }

        return [
            'dry_run' => $dryRun,
            'total_existing' => $totalExisting,
            'normalized' => $normalized,
            'already_normalized' => $alreadyNormalized,
            'collisions_merged' => $collisionsMerged,
            'skipped_invalid' => $skippedInvalid,
            'collisions' => $collisions,
        ];
    }

    /**
     * Stream and import cleaned clients from CSV in 500-row chunks.
     *
     * @return array{
     *     dry_run: bool,
     *     file: string,
     *     import_source: string,
     *     total_csv_rows: int,
     *     normalized: int,
     *     already_normalized: int,
     *     collisions_merged: int,
     *     updated: int,
     *     created: int,
     *     skipped: int,
     *     skipped_unchanged: int,
     *     skipped_invalid: int,
     *     skipped_duplicate_in_csv: int,
     *     collisions: list<array<string, mixed>>
     * }
     */
    public function importFromCsv(
        string $filePath,
        bool $dryRun = false,
        bool $normalizeExisting = true,
        string $importSource = self::DEFAULT_IMPORT_SOURCE
    ): array {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("CSV file not found or not readable: {$filePath}");
        }

        $normSummary = [
            'normalized' => 0,
            'already_normalized' => 0,
            'collisions_merged' => 0,
            'skipped_invalid' => 0,
            'collisions' => [],
        ];

        if ($normalizeExisting) {
            $normSummary = $this->normalizeExistingCustomers($dryRun);
        }

        $hasBranchCol = Schema::hasColumn('customers', 'branch');
        $hasStatusCol = Schema::hasColumn('customers', 'status');
        $hasImportSourceCol = Schema::hasColumn('customers', 'import_source');

        // Build an in-memory map keyed by canonical E.164 phone for O(1) matching across 11,000+ rows.
        $selectCols = ['id', 'name', 'phone', 'notes'];
        if ($hasBranchCol) {
            $selectCols[] = 'branch';
        }
        if ($hasStatusCol) {
            $selectCols[] = 'status';
        }
        if ($hasImportSourceCol) {
            $selectCols[] = 'import_source';
        }

        $existingByE164 = [];
        foreach (DB::table('customers')->orderBy('id')->get($selectCols) as $row) {
            $e164 = Customer::normalizePhoneE164($row->phone);
            if ($e164 === '') {
                continue;
            }
            // If dry-run had collisions, keep the first/already-E.164 record in the lookup map
            if (!isset($existingByE164[$e164]) || (string) $row->phone === $e164) {
                $existingByE164[$e164] = [
                    'id' => (int) $row->id,
                    'phone' => (string) $row->phone,
                    'name' => $row->name !== null ? (string) $row->name : null,
                    'branch' => $hasBranchCol ? Customer::normalizeBranch($row->branch ?? null) : null,
                    'notes' => $row->notes !== null ? (string) $row->notes : null,
                ];
            }
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$filePath}");
        }

        try {
            $rawHeader = fgetcsv($handle);
            if (!is_array($rawHeader) || empty($rawHeader)) {
                throw new RuntimeException("CSV file is empty or missing header row: {$filePath}");
            }

            // Strip UTF-8 BOM if present on the first header column
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]);
            $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rawHeader);

            if (!in_array('phone', $headers, true)) {
                throw new RuntimeException('CSV header row is missing required "phone" column.');
            }

            $headerCount = count($headers);
            $totalCsvRows = 0;
            $updated = 0;
            $created = 0;
            $skippedUnchanged = 0;
            $skippedInvalid = 0;
            $skippedDuplicateInCsv = 0;
            $phoneUpgradedDuringImport = 0;
            $seenCsvPhones = [];

            $chunk = [];
            while (($rawRow = fgetcsv($handle)) !== false) {
                // Skip completely blank lines
                if ($rawRow === [null] || (count($rawRow) === 1 && trim((string) $rawRow[0]) === '')) {
                    continue;
                }

                $totalCsvRows++;

                if (count($rawRow) < $headerCount) {
                    $rawRow = array_pad($rawRow, $headerCount, '');
                } elseif (count($rawRow) > $headerCount) {
                    $rawRow = array_slice($rawRow, 0, $headerCount);
                }

                $chunk[] = array_combine($headers, $rawRow);

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $this->processChunk(
                        $chunk,
                        $existingByE164,
                        $seenCsvPhones,
                        $dryRun,
                        $normalizeExisting,
                        $importSource,
                        $hasBranchCol,
                        $hasStatusCol,
                        $hasImportSourceCol,
                        $updated,
                        $created,
                        $skippedUnchanged,
                        $skippedInvalid,
                        $skippedDuplicateInCsv,
                        $phoneUpgradedDuringImport
                    );
                    $chunk = [];
                }
            }

            if (!empty($chunk)) {
                $this->processChunk(
                    $chunk,
                    $existingByE164,
                    $seenCsvPhones,
                    $dryRun,
                    $normalizeExisting,
                    $importSource,
                    $hasBranchCol,
                    $hasStatusCol,
                    $hasImportSourceCol,
                    $updated,
                    $created,
                    $skippedUnchanged,
                    $skippedInvalid,
                    $skippedDuplicateInCsv,
                    $phoneUpgradedDuringImport
                );
            }
        } finally {
            fclose($handle);
        }

        $totalNormalized = $normSummary['normalized'] + $phoneUpgradedDuringImport;
        $totalSkipped = $skippedUnchanged + $skippedInvalid + $skippedDuplicateInCsv;

        return [
            'dry_run' => $dryRun,
            'file' => $filePath,
            'import_source' => $importSource,
            'total_csv_rows' => $totalCsvRows,
            'normalized' => $totalNormalized,
            'already_normalized' => $normSummary['already_normalized'],
            'collisions_merged' => $normSummary['collisions_merged'],
            'updated' => $updated,
            'created' => $created,
            'skipped' => $totalSkipped,
            'skipped_unchanged' => $skippedUnchanged,
            'skipped_invalid' => $skippedInvalid,
            'skipped_duplicate_in_csv' => $skippedDuplicateInCsv,
            'collisions' => $normSummary['collisions'],
        ];
    }

    /**
     * Process a single offset/limit slice of a CSV file for timeout-proof sequential AJAX imports.
     * Assumes existing clients have already been normalized via normalizeExistingCustomers() before
     * chunk 0, while still matching legacy variants safely via Customer::phoneMatchVariants().
     *
     * @return array{
     *     offset: int,
     *     next_offset: int,
     *     limit: int,
     *     processed_in_chunk: int,
     *     has_more: bool,
     *     normalized: int,
     *     updated: int,
     *     created: int,
     *     skipped: int,
     *     skipped_unchanged: int,
     *     skipped_invalid: int,
     *     skipped_duplicate_in_csv: int
     * }
     */
    public function importCsvChunk(
        string $filePath,
        int $offset = 0,
        int $limit = self::CHUNK_SIZE,
        string $importSource = self::DEFAULT_IMPORT_SOURCE
    ): array {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("CSV file not found or not readable: {$filePath}");
        }

        $offset = max(0, $offset);
        $limit = max(1, min(self::CHUNK_SIZE, $limit));

        $hasBranchCol = Schema::hasColumn('customers', 'branch');
        $hasStatusCol = Schema::hasColumn('customers', 'status');
        $hasImportSourceCol = Schema::hasColumn('customers', 'import_source');

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$filePath}");
        }

        $chunk = [];
        $hasMore = false;

        try {
            $rawHeader = fgetcsv($handle);
            if (!is_array($rawHeader) || empty($rawHeader)) {
                throw new RuntimeException("CSV file is empty or missing header row: {$filePath}");
            }

            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]);
            $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rawHeader);

            if (!in_array('phone', $headers, true)) {
                throw new RuntimeException('CSV header row is missing required "phone" column.');
            }

            $headerCount = count($headers);
            $dataRowIndex = 0;

            while (($rawRow = fgetcsv($handle)) !== false) {
                if ($rawRow === [null] || (count($rawRow) === 1 && trim((string) $rawRow[0]) === '')) {
                    continue;
                }

                if ($dataRowIndex < $offset) {
                    $dataRowIndex++;
                    continue;
                }

                if (count($chunk) >= $limit) {
                    $hasMore = true;
                    break;
                }

                if (count($rawRow) < $headerCount) {
                    $rawRow = array_pad($rawRow, $headerCount, '');
                } elseif (count($rawRow) > $headerCount) {
                    $rawRow = array_slice($rawRow, 0, $headerCount);
                }

                $chunk[] = array_combine($headers, $rawRow);
                $dataRowIndex++;
            }
        } finally {
            fclose($handle);
        }

        $processedInChunk = count($chunk);
        $updated = 0;
        $created = 0;
        $skippedUnchanged = 0;
        $skippedInvalid = 0;
        $skippedDuplicateInCsv = 0;
        $phoneUpgradedDuringImport = 0;

        if ($processedInChunk > 0) {
            // Collect all exact + legacy phone variants ONLY for the rows in this chunk
            $lookupVariants = [];
            foreach ($chunk as $row) {
                $rawPhone = trim((string) ($row['phone'] ?? ''));
                $digitsOnly = preg_replace('/\D+/', '', $rawPhone);
                if ($digitsOnly === '') {
                    continue;
                }
                foreach (Customer::phoneMatchVariants('+' . ltrim($digitsOnly, '+')) as $variant) {
                    $lookupVariants[$variant] = true;
                }
            }

            $selectCols = ['id', 'name', 'phone', 'notes'];
            if ($hasBranchCol) {
                $selectCols[] = 'branch';
            }

            $existingByE164 = [];
            foreach (array_chunk(array_keys($lookupVariants), 300) as $variantSlice) {
                $rows = DB::table('customers')
                    ->whereIn('phone', $variantSlice)
                    ->orderBy('id')
                    ->get($selectCols);

                foreach ($rows as $row) {
                    $e164 = Customer::normalizePhoneE164($row->phone);
                    if ($e164 === '') {
                        continue;
                    }
                    if (!isset($existingByE164[$e164]) || (string) $row->phone === $e164) {
                        $existingByE164[$e164] = [
                            'id' => (int) $row->id,
                            'phone' => (string) $row->phone,
                            'name' => $row->name !== null ? (string) $row->name : null,
                            'branch' => $hasBranchCol ? Customer::normalizeBranch($row->branch ?? null) : null,
                            'notes' => $row->notes !== null ? (string) $row->notes : null,
                        ];
                    }
                }
            }

            $seenCsvPhones = [];
            $this->processChunk(
                $chunk,
                $existingByE164,
                $seenCsvPhones,
                false,
                false,
                $importSource,
                $hasBranchCol,
                $hasStatusCol,
                $hasImportSourceCol,
                $updated,
                $created,
                $skippedUnchanged,
                $skippedInvalid,
                $skippedDuplicateInCsv,
                $phoneUpgradedDuringImport
            );
        }

        return [
            'offset' => $offset,
            'next_offset' => $offset + $processedInChunk,
            'limit' => $limit,
            'processed_in_chunk' => $processedInChunk,
            'has_more' => $hasMore,
            'normalized' => $phoneUpgradedDuringImport,
            'updated' => $updated,
            'created' => $created,
            'skipped' => $skippedUnchanged + $skippedInvalid + $skippedDuplicateInCsv,
            'skipped_unchanged' => $skippedUnchanged,
            'skipped_invalid' => $skippedInvalid,
            'skipped_duplicate_in_csv' => $skippedDuplicateInCsv,
        ];
    }

    /**
     * Process a single 500-row CSV chunk: compute non-destructive updates for matched clients
     * and bulk-insert net-new clients in one transaction per chunk.
     *
     * @param  list<array<string, string>>  $chunk
     * @param  array<string, array<string, mixed>>  $existingByE164
     * @param  array<string, true>  $seenCsvPhones
     */
    private function processChunk(
        array $chunk,
        array &$existingByE164,
        array &$seenCsvPhones,
        bool $dryRun,
        bool $alreadyNormalizedExisting,
        string $importSource,
        bool $hasBranchCol,
        bool $hasStatusCol,
        bool $hasImportSourceCol,
        int &$updated,
        int &$created,
        int &$skippedUnchanged,
        int &$skippedInvalid,
        int &$skippedDuplicateInCsv,
        int &$phoneUpgradedDuringImport
    ): void {
        $now = now();
        $pendingUpdates = [];
        $pendingInserts = [];

        foreach ($chunk as $row) {
            $rawPhone = trim((string) ($row['phone'] ?? ''));
            $digitsOnly = preg_replace('/\D+/', '', $rawPhone);
            $e164 = $digitsOnly !== '' ? Customer::normalizePhoneE164('+' . ltrim($digitsOnly, '+')) : '';

            if ($e164 === '') {
                $skippedInvalid++;
                continue;
            }

            if (isset($seenCsvPhones[$e164])) {
                $skippedDuplicateInCsv++;
                continue;
            }
            $seenCsvPhones[$e164] = true;

            $csvName = self::cleanCsvName($row['name'] ?? null);
            $csvBranch = Customer::normalizeBranch($row['branch'] ?? null);
            $legacyNote = self::buildLegacyHistoryNote($row);

            if (isset($existingByE164[$e164])) {
                $existing = $existingByE164[$e164];
                $changes = [];

                // 1. Standardize phone to E.164 if still unnormalized (e.g. when --skip-normalize was used)
                if (!$alreadyNormalizedExisting && $existing['phone'] !== $e164) {
                    $changes['phone'] = $e164;
                    $phoneUpgradedDuringImport++;
                }

                // 2. Replace blank or junk placeholder name only when CSV provides a real name
                if (self::isPlaceholderName($existing['name']) && $csvName !== '') {
                    $changes['name'] = $csvName;
                }

                // 3. Assign branch only when existing client is Unassigned (null) and CSV has Old Airport / Al Wakrah
                if ($hasBranchCol && $existing['branch'] === null && $csvBranch !== null) {
                    $changes['branch'] = $csvBranch;
                }

                // 4. Append legacy history into notes without overwriting human notes or duplicating
                $mergedNotes = self::mergeNotes($existing['notes'], $legacyNote);
                if ($mergedNotes !== ($existing['notes'] !== '' ? $existing['notes'] : null)) {
                    $changes['notes'] = $mergedNotes;
                }

                if (!empty($changes)) {
                    $changes['updated_at'] = $now;
                    $pendingUpdates[] = [
                        'id' => $existing['id'],
                        'changes' => $changes,
                    ];

                    // Keep in-memory cache consistent
                    $existingByE164[$e164]['phone'] = $changes['phone'] ?? $existing['phone'];
                    $existingByE164[$e164]['name'] = $changes['name'] ?? $existing['name'];
                    $existingByE164[$e164]['branch'] = $changes['branch'] ?? $existing['branch'];
                    $existingByE164[$e164]['notes'] = $changes['notes'] ?? $existing['notes'];

                    $updated++;
                } else {
                    $skippedUnchanged++;
                }

                continue;
            }

            // Net-new client insert
            $insertRow = [
                'name' => $csvName !== '' ? $csvName : 'Unknown',
                'phone' => $e164,
                'email' => null,
                'notes' => $legacyNote,
                'loyalty_points' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasBranchCol) {
                $insertRow['branch'] = $csvBranch;
            }
            if ($hasStatusCol) {
                $insertRow['status'] = 'active';
            }
            if ($hasImportSourceCol) {
                $insertRow['import_source'] = $importSource;
            }

            $pendingInserts[] = $insertRow;
            $existingByE164[$e164] = [
                'id' => 0,
                'phone' => $e164,
                'name' => $insertRow['name'],
                'branch' => $csvBranch,
                'notes' => $legacyNote,
            ];
            $created++;
        }

        if ($dryRun || (empty($pendingUpdates) && empty($pendingInserts))) {
            return;
        }

        DB::transaction(function () use ($pendingUpdates, $pendingInserts) {
            foreach ($pendingUpdates as $upd) {
                DB::table('customers')
                    ->where('id', $upd['id'])
                    ->update($upd['changes']);
            }

            if (!empty($pendingInserts)) {
                DB::table('customers')->insert($pendingInserts);
            }
        });
    }

    /**
     * Pick the winning customer record among duplicate rows sharing the same E.164 phone.
     *
     * @param  list<object>  $rows
     */
    private function pickCollisionWinner(array $rows, string $e164): object
    {
        $ids = array_map(fn ($r) => (int) $r->id, $rows);

        $apptCounts = Schema::hasTable('appointments')
            ? DB::table('appointments')
                ->whereIn('customer_id', $ids)
                ->selectRaw('customer_id, COUNT(*) as cnt')
                ->groupBy('customer_id')
                ->pluck('cnt', 'customer_id')
                ->all()
            : [];

        $saleCounts = Schema::hasTable('sales')
            ? DB::table('sales')
                ->whereIn('customer_id', $ids)
                ->selectRaw('customer_id, COUNT(*) as cnt')
                ->groupBy('customer_id')
                ->pluck('cnt', 'customer_id')
                ->all()
            : [];

        usort($rows, function ($a, $b) use ($e164, $apptCounts, $saleCounts) {
            $aActivity = (int) ($apptCounts[$a->id] ?? 0) + (int) ($saleCounts[$a->id] ?? 0);
            $bActivity = (int) ($apptCounts[$b->id] ?? 0) + (int) ($saleCounts[$b->id] ?? 0);
            if ($aActivity !== $bActivity) {
                return $bActivity <=> $aActivity;
            }

            $aExact = ((string) $a->phone === $e164) ? 1 : 0;
            $bExact = ((string) $b->phone === $e164) ? 1 : 0;
            if ($aExact !== $bExact) {
                return $bExact <=> $aExact;
            }

            return (int) $a->id <=> (int) $b->id;
        });

        return $rows[0];
    }

    /**
     * Safely merge a duplicate customer row ($loserId) into $winnerId without losing
     * any appointments, sales, packages, freebie credits, leads, loyalty points, or notes.
     */
    private function mergeDuplicateCustomer(int $winnerId, int $loserId, string $e164): void
    {
        DB::transaction(function () use ($winnerId, $loserId, $e164) {
            $winner = DB::table('customers')->where('id', $winnerId)->first();
            $loser = DB::table('customers')->where('id', $loserId)->first();

            if (!$winner || !$loser) {
                return;
            }

            // Reassign all child records to the winning customer
            foreach (self::CUSTOMER_FK_TABLES as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'customer_id')) {
                    DB::table($table)
                        ->where('customer_id', $loserId)
                        ->update(['customer_id' => $winnerId]);
                }
            }

            $updates = [
                'loyalty_points' => (int) ($winner->loyalty_points ?? 0) + (int) ($loser->loyalty_points ?? 0),
                'updated_at' => now(),
            ];

            if (self::isPlaceholderName($winner->name) && !self::isPlaceholderName($loser->name)) {
                $updates['name'] = trim((string) $loser->name);
            }

            if (empty($winner->email) && !empty($loser->email)) {
                $updates['email'] = $loser->email;
            }

            if (empty($winner->allergies) && !empty($loser->allergies)) {
                $updates['allergies'] = $loser->allergies;
            }

            if (Schema::hasColumn('customers', 'branch')) {
                $winnerBranch = Customer::normalizeBranch($winner->branch ?? null);
                $loserBranch = Customer::normalizeBranch($loser->branch ?? null);
                if ($winnerBranch === null && $loserBranch !== null) {
                    $updates['branch'] = $loserBranch;
                }
            }

            $loserNotes = trim((string) ($loser->notes ?? ''));
            $winnerNotes = trim((string) ($winner->notes ?? ''));
            if ($loserNotes !== '' && ($winnerNotes === '' || !str_contains($winnerNotes, $loserNotes))) {
                $updates['notes'] = $winnerNotes !== '' ? $winnerNotes . "\n" . $loserNotes : $loserNotes;
            }

            // Delete duplicate first so unique index on `customers.phone` never conflicts when setting $e164
            DB::table('customers')->where('id', $loserId)->delete();

            $updates['phone'] = $e164;
            DB::table('customers')->where('id', $winnerId)->update($updates);
        });
    }
}
