<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monthly_offers')) {
            return;
        }

        Schema::table('monthly_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_offers', 'dream_outcome')) {
                $table->text('dream_outcome')->nullable()->after('staff_notes');
            }
            if (!Schema::hasColumn('monthly_offers', 'problem_solved')) {
                $table->text('problem_solved')->nullable()->after('dream_outcome');
            }
            if (!Schema::hasColumn('monthly_offers', 'core_value_additions')) {
                $table->text('core_value_additions')->nullable()->after('problem_solved');
            }
            if (!Schema::hasColumn('monthly_offers', 'value_stack_bonuses')) {
                $table->text('value_stack_bonuses')->nullable()->after('core_value_additions');
            }
            if (!Schema::hasColumn('monthly_offers', 'conditional_guarantee')) {
                $table->text('conditional_guarantee')->nullable()->after('value_stack_bonuses');
            }
            if (!Schema::hasColumn('monthly_offers', 'transparent_pricing')) {
                $table->text('transparent_pricing')->nullable()->after('conditional_guarantee');
            }
            if (!Schema::hasColumn('monthly_offers', 'capacity_scarcity')) {
                $table->text('capacity_scarcity')->nullable()->after('transparent_pricing');
            }
            if (!Schema::hasColumn('monthly_offers', 'whatsapp_script')) {
                $table->longText('whatsapp_script')->nullable()->after('capacity_scarcity');
            }
            if (!Schema::hasColumn('monthly_offers', 'video_shooting_script')) {
                $table->longText('video_shooting_script')->nullable()->after('whatsapp_script');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('monthly_offers')) {
            return;
        }

        $cols = [
            'dream_outcome',
            'problem_solved',
            'core_value_additions',
            'value_stack_bonuses',
            'conditional_guarantee',
            'transparent_pricing',
            'capacity_scarcity',
            'whatsapp_script',
            'video_shooting_script',
        ];

        $existing = array_values(array_filter($cols, fn (string $c) => Schema::hasColumn('monthly_offers', $c)));
        if (!empty($existing)) {
            Schema::table('monthly_offers', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
