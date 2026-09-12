<?php

namespace App\NovaAI\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Nova's own isolated conversation-memory migrations
 * (database/migrations/nova-memory/, targeting the 'nova_memory' connection
 * - see config/database.php) with Laravel's migrator, entirely separate
 * from the CRM's own default migration path. This is the "limited
 * infrastructure/config change needed solely to register the isolated Nova
 * database" the NOVA READ-ONLY INTEGRATION RULE (app/NovaAI/README.md)
 * explicitly permits - it never touches any CRM migration, model, or table.
 */
class NovaMemoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Skipped under the test suite: tests migrate nova_memory themselves,
        // explicitly and per-test (see tests/Concerns/MigratesNovaMemory.php),
        // because RefreshDatabase's shared migrate:fresh only caches/restores
        // an in-memory PDO for whichever connection the currently-running
        // test class lists in $connectionsToTransact - most existing tests
        // don't mention 'nova_memory' at all. Registering this path globally
        // would make that shared migrate:fresh create these tables exactly
        // once, in whichever test happens to run first, colliding with the
        // explicit per-test migration on that same first test.
        if (!$this->app->runningUnitTests()) {
            // Laravel's SQLite connector refuses to connect to a database
            // file that doesn't already exist on disk (it never creates one
            // itself) - ensure both storage/app/nova and the file exist
            // before anything tries to connect.
            $path = config('database.connections.nova_memory.database');
            File::ensureDirectoryExists(dirname($path));

            if (!File::exists($path)) {
                File::put($path, '');
            }

            $this->loadMigrationsFrom(database_path('migrations/nova-memory'));
        }
    }
}
