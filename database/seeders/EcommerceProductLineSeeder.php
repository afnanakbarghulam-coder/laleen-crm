<?php

namespace Database\Seeders;

use App\Models\EcommerceProductLine;
use Illuminate\Database\Seeder;

class EcommerceProductLineSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Hair Oil', 'Shampoo', 'Hair Mask', 'Serum'] as $name) {
            EcommerceProductLine::firstOrCreate(['name' => $name]);
        }
    }
}
