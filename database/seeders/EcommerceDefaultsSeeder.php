<?php

namespace Database\Seeders;

use App\Models\EcommerceComponentType;
use App\Models\EcommerceProductLine;
use Illuminate\Database\Seeder;

class EcommerceDefaultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productLines = [
            'Hair Oil',
            'Shampoo',
            'Serum',
            'Hair Mask',
        ];

        foreach ($productLines as $name) {
            EcommerceProductLine::firstOrCreate(['name' => $name]);
        }

        $componentTypes = [
            'Liquid Base',
            'Bottle/Jar',
            'Label',
            'Pump/Cap',
            'Outer Box',
        ];

        foreach ($componentTypes as $name) {
            EcommerceComponentType::firstOrCreate(['name' => $name]);
        }
    }
}
