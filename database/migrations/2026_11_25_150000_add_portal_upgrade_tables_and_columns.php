<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff')) {
            if (!Schema::hasColumn('staff', 'annual_leave_days')) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->unsignedSmallInteger('annual_leave_days')->default(21)->after('is_remote');
                });
            }
            if (!Schema::hasColumn('staff', 'sick_leave_days')) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->unsignedSmallInteger('sick_leave_days')->default(14)->after('annual_leave_days');
                });
            }
        }

        if (!Schema::hasTable('staff_documents')) {
            Schema::create('staff_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->string('category', 40)->index();
                $table->string('title', 255);
                $table->string('document_number', 120)->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable()->index();
                $table->string('file_path', 500);
                $table->string('original_filename', 255);
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('file_size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['staff_id', 'category']);
            });
        }

        if (!Schema::hasTable('staff_leaves')) {
            Schema::create('staff_leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->string('leave_type', 20); // annual | sick
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedSmallInteger('total_days');
                $table->text('reason')->nullable();
                $table->string('medical_certificate_path', 500)->nullable();
                $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('admin_notes', 500)->nullable();
                $table->timestamps();

                $table->index(['staff_id', 'status', 'start_date', 'end_date'], 'staff_leaves_lookup_idx');
            });
        }

        if (!Schema::hasTable('staff_attendances')) {
            Schema::create('staff_attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->date('attendance_date')->index();
                $table->string('status', 20)->default('present'); // present | absent | late | off
                $table->string('notes', 500)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['staff_id', 'attendance_date'], 'staff_attendances_staff_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff_leaves');
        Schema::dropIfExists('staff_documents');

        if (Schema::hasTable('staff')) {
            Schema::table('staff', function (Blueprint $table) {
                if (Schema::hasColumn('staff', 'sick_leave_days')) {
                    $table->dropColumn('sick_leave_days');
                }
                if (Schema::hasColumn('staff', 'annual_leave_days')) {
                    $table->dropColumn('annual_leave_days');
                }
            });
        }
    }
};
