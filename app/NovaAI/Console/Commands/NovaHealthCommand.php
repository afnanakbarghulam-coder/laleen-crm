<?php

namespace App\NovaAI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 8 production-readiness diagnostic: a read-only, secret-free health
 * check for Nova's own configuration and infrastructure, entirely separate
 * from the CRM's own health/status tooling.
 *
 * NOVA READ-ONLY INTEGRATION RULE: every check here only reads
 * configuration or asks the framework/DB "does this exist / can I connect",
 * never writes to the CRM connection, never calls Gemini (no network
 * request of any kind - checking whether a key is configured is not the
 * same as validating it against the real API, which would cost a real
 * quota call every time an operator ran this command), and never prints a
 * secret value - only SET/NOT SET.
 */
class NovaHealthCommand extends Command
{
    protected $signature = 'nova:health';

    protected $description = 'Read-only diagnostic check of Nova\'s configuration, memory database, and CRM connectivity - never prints secrets, never calls Gemini, never mutates anything';

    public function handle(): int
    {
        $checks = [
            $this->checkGeminiKeyConfigured(),
            $this->checkGeminiModelConfigured(),
            $this->checkSystemPromptReadable(),
            $this->checkCrmConnectionReadable(),
            $this->checkNovaMemoryDatabaseWritable(),
            $this->checkNovaMemoryTablesExist(),
        ];

        $this->table(['Check', 'Status', 'Detail'], array_map(
            fn (array $c) => [$c['label'], $c['ok'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>', $c['detail']],
            $checks
        ));

        $allOk = collect($checks)->every(fn (array $c) => $c['ok']);

        if ($allOk) {
            $this->info('Nova health check: all checks passed.');

            return self::SUCCESS;
        }

        $this->error('Nova health check: one or more checks failed - see table above.');

        return self::FAILURE;
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function checkGeminiKeyConfigured(): array
    {
        $isSet = !empty(config('services.gemini.key'));

        return ['label' => 'Gemini API key', 'ok' => $isSet, 'detail' => $isSet ? 'SET' : 'NOT SET'];
    }

    private function checkGeminiModelConfigured(): array
    {
        $model = config('services.gemini.model');

        // The model name itself is not a credential - safe to display, and
        // useful for confirming which model a deployment is actually
        // pointed at.
        return [
            'label' => 'Gemini model',
            'ok' => !empty($model),
            'detail' => $model ? "configured ({$model})" : 'NOT SET',
        ];
    }

    private function checkSystemPromptReadable(): array
    {
        $path = resource_path('prompts/nova-system.md');

        try {
            $exists = File::exists($path) && File::isReadable($path) && trim(File::get($path)) !== '';
        } catch (\Throwable $e) {
            $exists = false;
        }

        return [
            'label' => 'System prompt file',
            'ok' => $exists,
            'detail' => $exists ? 'readable and non-empty' : 'missing/unreadable/empty (falls back to a minimal built-in persona)',
        ];
    }

    /** Read-only: a trivial SELECT against the CRM's own default connection - never writes, never dumps a row. */
    private function checkCrmConnectionReadable(): array
    {
        try {
            DB::connection()->getPdo();
            $usersTableExists = Schema::hasTable('users');

            return [
                'label' => 'CRM connection',
                'ok' => $usersTableExists,
                'detail' => $usersTableExists ? 'connected, schema present' : 'connected, but expected tables are missing',
            ];
        } catch (\Throwable $e) {
            return ['label' => 'CRM connection', 'ok' => false, 'detail' => 'connection failed'];
        }
    }

    /** Confirms Nova's isolated SQLite file exists and is writable by the current process - never writes a row, only checks filesystem permissions. */
    private function checkNovaMemoryDatabaseWritable(): array
    {
        $path = config('database.connections.nova_memory.database');

        if ($path === ':memory:') {
            return ['label' => 'Nova memory database file', 'ok' => true, 'detail' => 'in-memory (test environment)'];
        }

        try {
            $dirWritable = File::isDirectory(dirname($path)) && File::isWritable(dirname($path));
            $fileWritable = File::exists($path) ? File::isWritable($path) : $dirWritable;

            return [
                'label' => 'Nova memory database file',
                'ok' => $fileWritable,
                'detail' => $fileWritable ? 'writable' : "not writable at {$path}",
            ];
        } catch (\Throwable $e) {
            return ['label' => 'Nova memory database file', 'ok' => false, 'detail' => 'could not check path'];
        }
    }

    /** Schema-existence check only - never selects/dumps a row of business-fact/decision/conversation content. */
    private function checkNovaMemoryTablesExist(): array
    {
        $expected = ['nova_conversations', 'nova_messages', 'nova_business_facts', 'nova_decisions', 'nova_experiments'];

        try {
            $missing = array_values(array_filter(
                $expected,
                fn (string $table) => !Schema::connection('nova_memory')->hasTable($table)
            ));

            return [
                'label' => 'Nova memory tables',
                'ok' => empty($missing),
                'detail' => empty($missing) ? 'all present' : 'missing: ' . implode(', ', $missing) . ' - run php artisan migrate',
            ];
        } catch (\Throwable $e) {
            return ['label' => 'Nova memory tables', 'ok' => false, 'detail' => 'could not reach the nova_memory connection'];
        }
    }
}
