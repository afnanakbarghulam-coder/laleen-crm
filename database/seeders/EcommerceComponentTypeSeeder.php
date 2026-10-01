<?php

namespace Database\Seeders;

use App\Models\EcommerceComponentType;
use Illuminate\Database\Seeder;

class EcommerceComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Liquid Base', 'Bottle/Jar', 'Pump/Cap', 'Label', 'Outer Box'] as $name) {
            EcommerceComponentType::firstOrCreate(['name' => $name]);
        }
    }
}
