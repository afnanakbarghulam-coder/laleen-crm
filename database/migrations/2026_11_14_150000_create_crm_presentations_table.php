<?php

use Database\Seeders\CrmPresentationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_presentations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 80)->default('Growth Playbook')->index();
            $table->text('summary')->nullable();
            $table->json('slides');
            $table->boolean('is_pinned')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $playbook = CrmPresentationSeeder::giveawayPlaybookAttributes();
        $now = now();

        DB::table('crm_presentations')->insert([
            'title' => $playbook['title'],
            'slug' => $playbook['slug'],
            'category' => $playbook['category'],
            'summary' => $playbook['summary'],
            'slides' => json_encode($playbook['slides'], JSON_UNESCAPED_UNICODE),
            'is_pinned' => (bool) $playbook['is_pinned'],
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_presentations');
    }
};
