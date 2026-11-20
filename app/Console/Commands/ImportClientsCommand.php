<?php

namespace App\Console\Commands;

use App\Services\ClientImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportClientsCommand extends Command
{
    protected $signature = 'crm:import-clients
        {file? : Path to the cleaned client CSV file (defaults to storage/app/imports/laleen_master_clients_crm_import.csv)}
        {--dry-run : Preview normalization, updates, and inserts with zero database writes}
        {--normalize-existing : Normalize existing CRM client phone numbers to E.164 before importing (enabled by default)}
        {--skip-normalize : Skip pre-import normalization of existing CRM client phone numbers}
        {--source=legacy_excel_2026_11 : Import batch/source tag stored on net-new clients}';

    protected $description = 'Normalize existing CRM clients to E.164 (+974/+<cc>) and non-destructively import cleaned clients from CSV in 500-row chunks.';

    public function handle(ClientImportService $service): int
    {
        $rawPath = trim((string) ($this->argument('file') ?: 'storage/app/imports/laleen_master_clients_crm_import.csv'));
        $filePath = $this->resolveFilePath($rawPath);

        if (!is_file($filePath) || !is_readable($filePath)) {
            $this->error("CSV file not found or not readable: {$filePath}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $normalizeExisting = !$this->option('skip-normalize') || (bool) $this->option('normalize-existing');
        $importSource = trim((string) $this->option('source')) ?: ClientImportService::DEFAULT_IMPORT_SOURCE;

        $this->info(($dryRun ? '[DRY-RUN] ' : '[LIVE] ') . "Starting Laleen CRM client import from: {$filePath}");
        $this->line('Phone standard: Unified E.164 (+974XXXXXXXX for Qatar, +<cc><number> for international)');
        $this->line('Pre-import existing normalization: ' . ($normalizeExisting ? 'ENABLED' : 'SKIPPED'));

        try {
            $summary = $service->importFromCsv(
                filePath: $filePath,
                dryRun: $dryRun,
                normalizeExisting: $normalizeExisting,
                importSource: $importSource,
            );
        } catch (Throwable $e) {
            $this->error('Client import failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Mode', $summary['dry_run'] ? 'DRY-RUN (0 DB writes)' : 'LIVE (Committed)'],
                ['CSV Rows Read', number_format($summary['total_csv_rows'])],
                ['Existing Phones Normalized to E.164', number_format($summary['normalized'])],
                ['Existing Phones Already E.164', number_format($summary['already_normalized'])],
                ['Existing Phone Collisions Merged', number_format($summary['collisions_merged'])],
                ['Existing Clients Updated (Non-Destructive)', number_format($summary['updated'])],
                ['Net-New Clients Created', number_format($summary['created'])],
                ['Skipped (Total)', number_format($summary['skipped'])],
                ['  - Unchanged Existing Matches', number_format($summary['skipped_unchanged'])],
                ['  - Invalid / Blank Phone Rows', number_format($summary['skipped_invalid'])],
                ['  - Duplicate Phones Within CSV', number_format($summary['skipped_duplicate_in_csv'])],
            ]
        );

        if (!empty($summary['collisions'])) {
            $this->warn('Phone collisions detected (' . count($summary['collisions']) . '):');
            $this->table(
                ['Target E.164', 'Kept ID', 'Kept Phone', 'Merged Duplicate ID', 'Merged Duplicate Phone'],
                array_map(fn (array $c) => [
                    $c['e164'],
                    $c['winner_id'],
                    $c['winner_phone'],
                    $c['merged_duplicate_id'],
                    $c['merged_duplicate_phone'],
                ], array_slice($summary['collisions'], 0, 25))
            );
        }

        if ($dryRun) {
            $this->info('Dry-run complete. Zero database changes were written.');
        } else {
            $this->info('Client import complete.');
        }

        return self::SUCCESS;
    }

    private function resolveFilePath(string $path): string
    {
        if (is_file($path)) {
            return realpath($path) ?: $path;
        }

        $fromBase = base_path($path);
        if (is_file($fromBase)) {
            return realpath($fromBase) ?: $fromBase;
        }

        return $path;
    }
}
