<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $categories = [
            ['name' => 'Cutting', 'color' => '#3f8cff', 'sort_order' => 1],
            ['name' => 'Coloring', 'color' => '#e67e22', 'sort_order' => 2],
            ['name' => 'Styling', 'color' => '#9b59b6', 'sort_order' => 3],
            ['name' => 'Nail Services', 'color' => '#e6493f', 'sort_order' => 4],
            ['name' => 'Spa & Wellness', 'color' => '#2bb673', 'sort_order' => 5],
        ];

        $ids = [];
        foreach ($categories as $cat) {
            $ids[$cat['name']] = DB::table('service_categories')->insertGetId(array_merge($cat, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $assignments = [
            'Haircut' => 'Cutting',
            'Hair Color' => 'Coloring',
            'Manicure' => 'Nail Services',
            'Pedicure' => 'Nail Services',
            'Facial' => 'Spa & Wellness',
            'Massage' => 'Spa & Wellness',
        ];

        foreach ($assignments as $serviceName => $categoryName) {
            DB::table('services')->where('name', $serviceName)->update(['category_id' => $ids[$categoryName]]);
        }
    }

    public function down(): void
    {
        DB::table('services')->update(['category_id' => null]);
        DB::table('service_categories')->whereIn('name', ['Cutting', 'Coloring', 'Styling', 'Nail Services', 'Spa & Wellness'])->delete();
    }
};
