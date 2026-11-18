<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_freebie_packages')) {
            Schema::create('service_freebie_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('anchor_service_id')->constrained('services')->cascadeOnDelete();
                $table->decimal('price', 10, 2);
                $table->unsignedTinyInteger('free_choices_count')->default(2);
                $table->unsignedSmallInteger('validity_days')->default(7);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'name'], 'svc_freebie_pkgs_active_name_idx');
            });
        }

        if (!Schema::hasTable('service_freebie_package_items')) {
            Schema::create('service_freebie_package_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->constrained('service_freebie_packages')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['package_id', 'service_id'], 'svc_freebie_pkg_items_pkg_svc_uniq');
            });
        }

        if (!Schema::hasTable('client_freebie_credits')) {
            Schema::create('client_freebie_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('package_id')->constrained('service_freebie_packages')->cascadeOnDelete();
                $table->foreignId('anchor_appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->unsignedTinyInteger('slot_number');
                $table->string('status', 32)->default('locked_until_paid');
                $table->foreignId('redeemed_service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->foreignId('redemption_appointment_id')
                    ->nullable()
                    ->constrained('appointments', 'id', 'cfc_redeem_appt_id_fk')
                    ->nullOnDelete();
                $table->foreignId('redemption_appointment_service_id')
                    ->nullable()
                    ->constrained('appointment_services', 'id', 'cfc_redeem_appt_svc_id_fk')
                    ->nullOnDelete();
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamp('redeemed_at')->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();

                $table->unique(['anchor_appointment_id', 'slot_number'], 'client_freebie_credits_anchor_slot_uniq');
                $table->index(['customer_id', 'status', 'expires_at'], 'client_freebie_credits_cust_status_exp_idx');
                $table->index('redemption_appointment_id', 'client_freebie_credits_redeem_appt_idx');
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'freebie_package_id')) {
                    $table->foreignId('freebie_package_id')
                        ->nullable()
                        ->constrained('service_freebie_packages')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('appointments', 'is_freebie_redemption')) {
                    $table->boolean('is_freebie_redemption')->default(false);
                }
            });
        }

        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                if (!Schema::hasColumn('appointment_services', 'freebie_package_id')) {
                    $table->foreignId('freebie_package_id')
                        ->nullable()
                        ->constrained('service_freebie_packages')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('appointment_services', 'freebie_credit_id')) {
                    $table->foreignId('freebie_credit_id')
                        ->nullable()
                        ->constrained('client_freebie_credits')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('appointment_services', 'is_freebie_redemption')) {
                    $table->boolean('is_freebie_redemption')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                if (Schema::hasColumn('appointment_services', 'freebie_credit_id')) {
                    $table->dropConstrainedForeignId('freebie_credit_id');
                }
                if (Schema::hasColumn('appointment_services', 'freebie_package_id')) {
                    $table->dropConstrainedForeignId('freebie_package_id');
                }
                if (Schema::hasColumn('appointment_services', 'is_freebie_redemption')) {
                    $table->dropColumn('is_freebie_redemption');
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (Schema::hasColumn('appointments', 'freebie_package_id')) {
                    $table->dropConstrainedForeignId('freebie_package_id');
                }
                if (Schema::hasColumn('appointments', 'is_freebie_redemption')) {
                    $table->dropColumn('is_freebie_redemption');
                }
            });
        }

        Schema::dropIfExists('client_freebie_credits');
        Schema::dropIfExists('service_freebie_package_items');
        Schema::dropIfExists('service_freebie_packages');
    }
};
