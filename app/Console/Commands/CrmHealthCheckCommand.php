<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CrmHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a comprehensive full-CRM security, stability, database, storage, and config diagnostic check.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rows = [];
        $hasCriticalFailure = false;

        // 1. Environment & Debug Safety
        $appEnv = (string) config('app.env', 'production');
        $appDebug = (bool) config('app.debug', false);
        $logChannel = (string) config('logging.default', 'stack');

        if ($appEnv === 'production' && $appDebug === true) {
            $rows[] = ['1. Environment & Debug Safety', 'FAIL', "APP_ENV=production with APP_DEBUG=true (unsafe for production) | LOG_CHANNEL={$logChannel}"];
            $hasCriticalFailure = true;
        } else {
            $debugStr = $appDebug ? 'true' : 'false';
            $rows[] = ['1. Environment & Debug Safety', 'PASS', "APP_ENV={$appEnv}, APP_DEBUG={$debugStr}, LOG_CHANNEL={$logChannel}"];
        }

        // 2. Config Cache Compatibility (Zero raw env() calls in app/)
        $rawEnvHits = self::findRawEnvCallsInDirectory(app_path());
        if (empty($rawEnvHits)) {
            $rows[] = ['2. Config Cache Compatibility', 'PASS', '0 raw env() calls detected in app/ (100% config:cache safe)'];
        } else {
            $rows[] = ['2. Config Cache Compatibility', 'FAIL', count($rawEnvHits) . ' raw env() call(s) found: ' . implode(', ', array_slice($rawEnvHits, 0, 5))];
            $hasCriticalFailure = true;
        }

        // 3. Database & Core Tables Check (Salon + Ecommerce)
        $requiredTables = [
            'users',
            'staff',
            'customers',
            'leads',
            'appointments',
            'services',
            'sales',
            'expenses',
            'ecommerce_products',
            'ecommerce_sales',
            'ecommerce_customers',
            'ecommerce_expenses',
            'ecommerce_seo_articles',
            'ecommerce_lifecycle_flows',
            'ecommerce_lifecycle_logs',
            'ecommerce_whatsapp_threads',
        ];

        try {
            DB::connection()->getPdo();
            $missingTables = [];
            foreach ($requiredTables as $table) {
                if (!Schema::hasTable($table)) {
                    $missingTables[] = $table;
                }
            }

            if (empty($missingTables)) {
                $rows[] = ['3. Database & Core Tables', 'PASS', 'Connected & verified all ' . count($requiredTables) . ' core Salon + Ecommerce tables'];
            } else {
                $rows[] = ['3. Database & Core Tables', 'FAIL', 'Missing tables: ' . implode(', ', $missingTables)];
                $hasCriticalFailure = true;
            }
        } catch (\Throwable $e) {
            $rows[] = ['3. Database & Core Tables', 'FAIL', 'Database connection failed: ' . $e->getMessage()];
            $hasCriticalFailure = true;
        }

        // 4. Storage & Permissions Check
        $storageDirs = [
            'storage/logs' => storage_path('logs'),
            'storage/framework' => storage_path('framework'),
            'storage/app/public' => storage_path('app/public'),
        ];

        $unwritableDirs = [];
        foreach ($storageDirs as $label => $dirPath) {
            if (!is_dir($dirPath)) {
                @mkdir($dirPath, 0755, true);
            }
            if (!is_dir($dirPath) || !is_writable($dirPath)) {
                $unwritableDirs[] = $label;
            }
        }

        $publicStorageExists = file_exists(public_path('storage')) || is_dir(public_path('storage'));
        if (empty($unwritableDirs)) {
            $symlinkNote = $publicStorageExists ? 'public/storage present' : 'public/storage link optional (run storage:link if needed)';
            $rows[] = ['4. Storage & Permissions', 'PASS', 'storage/logs, storage/framework, storage/app/public writable (' . $symlinkNote . ')'];
        } else {
            $rows[] = ['4. Storage & Permissions', 'FAIL', 'Unwritable directories: ' . implode(', ', $unwritableDirs)];
            $hasCriticalFailure = true;
        }

        // 5. External Service Config Check
        $geminiConfig = config('services.gemini');
        $shopifyConfig = config('services.shopify');
        $whatsappConfig = config('services.whatsapp');

        $hasServiceStructure = is_array($geminiConfig)
            && is_array($shopifyConfig)
            && array_key_exists('domain', $shopifyConfig)
            && array_key_exists('access_token', $shopifyConfig)
            && array_key_exists('webhook_secret', $shopifyConfig)
            && is_array($whatsappConfig)
            && array_key_exists('api_url', $whatsappConfig)
            && array_key_exists('api_token', $whatsappConfig)
            && array_key_exists('webhook_token', $whatsappConfig);

        if ($hasServiceStructure) {
            $geminiReady = !empty($geminiConfig['key'] ?? $geminiConfig['api_key'] ?? null) ? 'configured' : 'fallback-mode';
            $shopifyReady = !empty($shopifyConfig['domain'] ?? null) && !empty($shopifyConfig['access_token'] ?? null) ? 'configured' : 'fallback-mode';
            $whatsappReady = !empty($whatsappConfig['api_token'] ?? $whatsappConfig['token'] ?? null) ? 'configured' : 'fallback-mode';
            $rows[] = [
                '5. External Service Config',
                'PASS',
                "services.php keys registered (Gemini: {$geminiReady}, Shopify: {$shopifyReady}, WhatsApp: {$whatsappReady})",
            ];
        } else {
            $rows[] = ['5. External Service Config', 'FAIL', 'Missing required keys in config/services.php for gemini, shopify, or whatsapp'];
            $hasCriticalFailure = true;
        }

        $this->info('Laleen CRM Full-System Health & Security Diagnostic Report');
        $this->table(['Check Category', 'Status', 'Details'], $rows);

        if ($hasCriticalFailure) {
            $this->error('One or more critical CRM health checks failed.');
            return self::FAILURE;
        }

        $this->info('All CRM health, security, database, storage, and config checks passed.');
        return self::SUCCESS;
    }

    /**
     * Recursively scan a PHP directory using PHP's tokenizer to detect raw env(...) calls outside of config/*.php.
     *
     * @return array<int, string>
     */
    public static function findRawEnvCallsInDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $hits = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $code = (string) @file_get_contents($file->getPathname());
            if ($code === '' || stripos($code, 'env') === false) {
                continue;
            }

            $tokens = token_get_all($code);
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'env') {
                    continue;
                }

                // Check previous non-whitespace token to ensure it's not a method call (->env), static call (::env), or function declaration
                $prevIdx = $i - 1;
                while ($prevIdx >= 0 && is_array($tokens[$prevIdx]) && $tokens[$prevIdx][0] === T_WHITESPACE) {
                    $prevIdx--;
                }
                if ($prevIdx >= 0 && is_array($tokens[$prevIdx])) {
                    $prevType = $tokens[$prevIdx][0];
                    if (in_array($prevType, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                        continue;
                    }
                }

                // Check next non-whitespace token is '('
                $nextIdx = $i + 1;
                while ($nextIdx < $count && is_array($tokens[$nextIdx]) && $tokens[$nextIdx][0] === T_WHITESPACE) {
                    $nextIdx++;
                }
                if ($nextIdx < $count && $tokens[$nextIdx] === '(') {
                    $relativePath = str_replace(['\\', base_path() . '/'], ['/', ''], $file->getPathname());
                    $hits[] = "{$relativePath}:{$token[2]}";
                }
            }
        }

        return $hits;
    }
}
