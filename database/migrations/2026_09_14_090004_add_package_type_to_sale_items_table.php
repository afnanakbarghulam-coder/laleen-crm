<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widens sale_items.type to also accept 'package' (a combo purchased at
     * checkout), alongside the existing 'service'/'product' values. SQLite
     * enforces enum() as a CHECK constraint, which SQLite cannot ALTER in
     * place - so this rebuilds the table (same approach SQLite itself
     * recommends for constraint changes) rather than dropping/losing data.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sale_items MODIFY type ENUM('service', 'product', 'package') NOT NULL");
            return;
        }

        DB::statement('PRAGMA foreign_keys=off');

        DB::statement("
            CREATE TABLE sale_items_new (
                id integer primary key autoincrement not null,
                sale_id integer not null,
                type varchar check (type in ('service', 'product', 'package')) not null,
                product_id integer,
                name varchar not null,
                price numeric not null,
                quantity integer not null default '1',
                total numeric not null,
                created_at datetime,
                updated_at datetime,
                original_price numeric,
                discount_amount numeric not null default '0',
                foreign key(sale_id) references sales(id) on delete cascade,
                foreign key(product_id) references products(id) on delete set null
            )
        ");

        DB::statement('
            INSERT INTO sale_items_new (id, sale_id, type, product_id, name, price, quantity, total, created_at, updated_at, original_price, discount_amount)
            SELECT id, sale_id, type, product_id, name, price, quantity, total, created_at, updated_at, original_price, discount_amount
            FROM sale_items
        ');

        DB::statement('DROP TABLE sale_items');
        DB::statement('ALTER TABLE sale_items_new RENAME TO sale_items');

        DB::statement('PRAGMA foreign_keys=on');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sale_items MODIFY type ENUM('service', 'product') NOT NULL");
            return;
        }

        DB::statement('PRAGMA foreign_keys=off');

        DB::statement("
            CREATE TABLE sale_items_new (
                id integer primary key autoincrement not null,
                sale_id integer not null,
                type varchar check (type in ('service', 'product')) not null,
                product_id integer,
                name varchar not null,
                price numeric not null,
                quantity integer not null default '1',
                total numeric not null,
                created_at datetime,
                updated_at datetime,
                original_price numeric,
                discount_amount numeric not null default '0',
                foreign key(sale_id) references sales(id) on delete cascade,
                foreign key(product_id) references products(id) on delete set null
            )
        ");

        DB::statement("
            INSERT INTO sale_items_new (id, sale_id, type, product_id, name, price, quantity, total, created_at, updated_at, original_price, discount_amount)
            SELECT id, sale_id, type, product_id, name, price, quantity, total, created_at, updated_at, original_price, discount_amount
            FROM sale_items WHERE type != 'package'
        ");

        DB::statement('DROP TABLE sale_items');
        DB::statement('ALTER TABLE sale_items_new RENAME TO sale_items');

        DB::statement('PRAGMA foreign_keys=on');
    }
};
