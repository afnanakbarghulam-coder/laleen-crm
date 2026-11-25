<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INITIAL_ENTRIES = [
        [
            'serial_number' => 1,
            'nature_of_work' => 'Al Wakrah Salon Commercial Permit Renewal',
            'status' => 'under_process',
            'remarks' => 'Civil Defense work under way',
            'why_pending' => 'Because of the fire fitting company maintenance work',
        ],
        [
            'serial_number' => 2,
            'nature_of_work' => 'Al Wakrah Salon Location Update',
            'status' => 'hold',
            'remarks' => 'Need to renew CP first',
            'why_pending' => 'Because of the commercial permit expiry',
        ],
        [
            'serial_number' => 3,
            'nature_of_work' => 'Al Wakrah Salon Retail Sale Add Activity',
            'status' => 'hold',
            'remarks' => 'Need to renew CP first',
            'why_pending' => 'Because of the commercial permit expiry',
        ],
        [
            'serial_number' => 4,
            'nature_of_work' => 'Al Wakrah Salon Hydra Facial Approval',
            'status' => 'hold',
            'remarks' => 'Need to check the Ministry',
            'why_pending' => 'Because of the PRO',
        ],
        [
            'serial_number' => 5,
            'nature_of_work' => 'Al Matar Salon Main Branch Location Update',
            'status' => 'under_process',
            'remarks' => 'Document signature under way',
            'why_pending' => 'Building owner gave signature by today 25/11/2026',
        ],
        [
            'serial_number' => 6,
            'nature_of_work' => 'Health Card of the 6 Employees',
            'status' => 'under_process',
            'remarks' => 'Document submitted',
            'why_pending' => 'Pending from Health Center approval',
        ],
        [
            'serial_number' => 7,
            'nature_of_work' => 'Giveaway Promotion Inquiry',
            'status' => 'under_process',
            'remarks' => 'Taking information',
            'why_pending' => 'Pending by PRO side, we are taking final information',
        ],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('legal_tasks')) {
            if ($driver === 'sqlite') {
                // SQLite enforces CHECK constraints on enum columns; rebuild safely preserving all existing rows.
                $existingRows = DB::table('legal_tasks')->orderBy('id')->get();

                Schema::dropIfExists('legal_tasks_upgraded');
                Schema::create('legal_tasks_upgraded', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedInteger('serial_number')->default(0);
                    $table->string('nature_of_work')->nullable();
                    $table->string('title')->nullable();
                    $table->text('description')->nullable();
                    $table->date('deadline')->nullable();
                    $table->string('status', 32)->default('under_process');
                    $table->text('remarks')->nullable();
                    $table->text('why_pending')->nullable();
                    $table->string('person_responsible')->nullable();
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamps();
                });

                $sn = 1;
                foreach ($existingRows as $row) {
                    $work = $row->nature_of_work ?? $row->title ?? 'Legal Task';
                    DB::table('legal_tasks_upgraded')->insert([
                        'id' => $row->id,
                        'serial_number' => $row->serial_number ?? $sn++,
                        'nature_of_work' => $work,
                        'title' => $row->title ?? $work,
                        'description' => $row->description ?? null,
                        'deadline' => $row->deadline ?? null,
                        'status' => $row->status ?? 'pending',
                        'remarks' => $row->remarks ?? ($row->description ?? null),
                        'why_pending' => $row->why_pending ?? null,
                        'person_responsible' => $row->person_responsible ?? null,
                        'created_by' => $row->created_by ?? null,
                        'updated_by' => $row->updated_by ?? ($row->created_by ?? null),
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                }

                Schema::drop('legal_tasks');
                Schema::rename('legal_tasks_upgraded', 'legal_tasks');
            } else {
                // MySQL / MariaDB / PostgreSQL non-destructive alter
                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    DB::statement("ALTER TABLE legal_tasks MODIFY COLUMN title VARCHAR(255) NULL");
                    DB::statement("ALTER TABLE legal_tasks MODIFY COLUMN deadline DATE NULL");
                    DB::statement("ALTER TABLE legal_tasks MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'under_process'");
                }

                Schema::table('legal_tasks', function (Blueprint $table) {
                    if (!Schema::hasColumn('legal_tasks', 'serial_number')) {
                        $table->unsignedInteger('serial_number')->default(0)->after('id');
                    }
                    if (!Schema::hasColumn('legal_tasks', 'nature_of_work')) {
                        $table->string('nature_of_work')->nullable()->after('serial_number');
                    }
                    if (!Schema::hasColumn('legal_tasks', 'remarks')) {
                        $table->text('remarks')->nullable()->after('status');
                    }
                    if (!Schema::hasColumn('legal_tasks', 'why_pending')) {
                        $table->text('why_pending')->nullable()->after('remarks');
                    }
                    if (!Schema::hasColumn('legal_tasks', 'updated_by')) {
                        $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                    }
                });

                DB::table('legal_tasks')
                    ->whereNull('nature_of_work')
                    ->whereNotNull('title')
                    ->update(['nature_of_work' => DB::raw('title')]);
            }
        } else {
            Schema::create('legal_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('serial_number')->default(0);
                $table->string('nature_of_work');
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->date('deadline')->nullable();
                $table->string('status', 32)->default('under_process');
                $table->text('remarks')->nullable();
                $table->text('why_pending')->nullable();
                $table->string('person_responsible')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // Idempotent pre-load of the 7 current Laleen Salon Work entries
        $now = now();
        $maxSn = (int) DB::table('legal_tasks')->max('serial_number');
        foreach (self::INITIAL_ENTRIES as $entry) {
            $exists = DB::table('legal_tasks')
                ->where('nature_of_work', $entry['nature_of_work'])
                ->exists();

            if (!$exists) {
                $sn = $maxSn === 0 ? $entry['serial_number'] : ++$maxSn;
                DB::table('legal_tasks')->insert([
                    'serial_number' => $sn,
                    'nature_of_work' => $entry['nature_of_work'],
                    'title' => $entry['nature_of_work'],
                    'status' => $entry['status'],
                    'remarks' => $entry['remarks'],
                    'why_pending' => $entry['why_pending'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Create private Branch & Company Legal Documents Vault table
        if (!Schema::hasTable('legal_documents')) {
            Schema::create('legal_documents', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 32)->index(); // old_airport | al_wakrah | company
                $table->string('category', 64)->index();
                $table->string('title');
                $table->string('document_number', 120)->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable()->index();
                $table->string('file_path');
                $table->string('original_filename');
                $table->string('mime_type', 120)->default('application/octet-stream');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
