<?php

namespace Tests\Concerns;

/**
 * Nova's isolated 'nova_memory' connection (see config/database.php) is
 * :memory: SQLite under phpunit.xml, same as the CRM's own default
 * connection - but Laravel's RefreshDatabase trait only caches/restores an
 * in-memory PDO across tests for whichever connections a given test class
 * lists in $connectionsToTransact, and only the very first RefreshDatabase
 * test class in the whole run actually triggers the shared migrate:fresh.
 * Since most existing Nova test classes never mention 'nova_memory', that
 * class's nova_memory PDO is never captured, so a *different* test class
 * needing nova_memory later in the run would otherwise find a blank,
 * un-migrated database.
 *
 * The reliable fix is to make nova_memory fully independent of that shared,
 * order-sensitive caching: migrate its own two tables (and its own
 * migration-repository bookkeeping, via --database) fresh, explicitly, at
 * the start of every single test method that needs them. Each test method
 * already gets a brand-new blank nova_memory :memory: database regardless
 * (a fresh application container is booted per test method), so this is
 * simply guaranteeing that fresh database has its schema - and, as a
 * side effect, gives perfect isolation between test methods for free.
 */
trait MigratesNovaMemory
{
    protected function migrateNovaMemory(): void
    {
        $this->artisan('migrate', [
            '--database' => 'nova_memory',
            '--path' => 'database/migrations/nova-memory',
            '--realpath' => false,
        ]);
    }
}
