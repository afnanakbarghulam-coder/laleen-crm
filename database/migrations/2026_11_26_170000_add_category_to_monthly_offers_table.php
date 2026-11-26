<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monthly_offers')) {
            return;
        }

        if (! Schema::hasColumn('monthly_offers', 'category')) {
            Schema::table('monthly_offers', function (Blueprint $table) {
                $table->string('category', 32)->nullable()->after('offer_type')->index();
            });
        }

        // Safely backfill existing rows where category IS NULL using conservative type/keyword matching.
        $rows = DB::table('monthly_offers')
            ->whereNull('category')
            ->get(['id', 'title', 'offer_type']);

        foreach ($rows as $row) {
            $resolved = $this->inferCategory(
                (string) ($row->offer_type ?? ''),
                (string) ($row->title ?? '')
            );

            if ($resolved !== null) {
                DB::table('monthly_offers')
                    ->where('id', $row->id)
                    ->whereNull('category')
                    ->update(['category' => $resolved]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monthly_offers') && Schema::hasColumn('monthly_offers', 'category')) {
            Schema::table('monthly_offers', function (Blueprint $table) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            });
        }
    }

    private function inferCategory(string $offerType, string $title): ?string
    {
        $type = strtolower(trim($offerType));
        $lowerTitle = mb_strtolower(trim($title));

        if (
            in_array($type, ['combo', 'linked_combo'], true)
            || $this->containsAny($lowerTitle, ['combo', 'package'])
        ) {
            return 'combo';
        }

        if ($this->containsAny($lowerTitle, ['makeup', 'bridal', 'nikah', 'henna', 'engagement', 'glam'])) {
            return 'makeup';
        }

        if ($this->containsAny($lowerTitle, ['skin', 'facial', 'cleanup', 'wax', 'threading', 'bleach', 'manicure', 'pedicure'])) {
            return 'skin';
        }

        if ($this->containsAny($lowerTitle, ['hair', 'haircut', 'blowdry', 'color', 'protein', 'botox', 'keratin', 'highlight', 'scalp'])) {
            return 'hair';
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
};
