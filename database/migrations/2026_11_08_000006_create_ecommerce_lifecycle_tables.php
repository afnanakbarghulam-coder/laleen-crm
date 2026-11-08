<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_customers') && !Schema::hasColumn('ecommerce_customers', 'region')) {
            Schema::table('ecommerce_customers', function (Blueprint $table) {
                $table->string('region', 10)->nullable()->index();
            });
        }

        if (!Schema::hasTable('ecommerce_lifecycle_flows')) {
            Schema::create('ecommerce_lifecycle_flows', function (Blueprint $table) {
                $table->id();
                $table->string('region', 10)->default('PK')->index();
                $table->string('flow_key', 60)->index();
                $table->string('name');
                $table->string('channel', 30)->default('email_and_whatsapp');
                $table->integer('trigger_day')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->integer('sent_count')->default(0);
                $table->integer('converted_count')->default(0);
                $table->timestamp('last_ran_at')->nullable();
                $table->timestamps();

                $table->unique(['region', 'flow_key']);
            });
        }

        if (!Schema::hasTable('ecommerce_lifecycle_logs')) {
            Schema::create('ecommerce_lifecycle_logs', function (Blueprint $table) {
                $table->id();
                $table->string('region', 10)->default('PK')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_phone', 40)->nullable();
                $table->string('flow_key', 60)->index();
                $table->string('channel', 30)->default('email')->index();
                $table->string('subject')->nullable();
                $table->longText('message_body')->nullable();
                $table->string('status', 30)->default('sent')->index();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_lifecycle_logs');
        Schema::dropIfExists('ecommerce_lifecycle_flows');
    }
};
