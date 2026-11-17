<?php

namespace Database\Seeders;

use App\Models\ProductRdItem;
use App\Models\ProductRdSample;
use App\Models\ProductRdSupplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProductRdTrackerSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !Schema::hasTable('product_rd_suppliers')
            || !Schema::hasTable('product_rd_items')
            || !Schema::hasTable('product_rd_samples')
        ) {
            return;
        }

        $fist = ProductRdSupplier::firstOrCreate(
            ['name' => 'Fist Industries'],
            [
                'contact_person'    => null,
                'phone_or_whatsapp' => null,
                'country'           => null,
            ]
        );

        $glen = ProductRdSupplier::firstOrCreate(
            ['name' => 'Glen'],
            [
                'contact_person'    => null,
                'phone_or_whatsapp' => null,
                'country'           => null,
            ]
        );

        $baselineFormulas = [
            [
                'slug'                    => 'hair-mask',
                'name'                    => 'Hair Mask',
                'qatar_enabled'           => true,
                'qatar_bottle_size'       => '500 ml',
                'qatar_bottle_status'     => 'pending',
                'qatar_sticker_status'    => 'blocked_by_bottle',
                'pakistan_enabled'        => true,
                'pakistan_bottle_size'    => '500 ml',
                'pakistan_bottle_status'  => 'pending',
                'pakistan_sticker_status' => 'blocked_by_bottle',
            ],
            [
                'slug'                    => 'hair-oil',
                'name'                    => 'Hair Oil',
                'qatar_enabled'           => true,
                'qatar_bottle_size'       => '150 ml',
                'qatar_bottle_status'     => 'pending',
                'qatar_sticker_status'    => 'blocked_by_bottle',
                'pakistan_enabled'        => true,
                'pakistan_bottle_size'    => '50 ml',
                'pakistan_bottle_status'  => 'finalized',
                'pakistan_sticker_status' => 'ready_for_dieline',
            ],
            [
                'slug'                    => 'shampoo',
                'name'                    => 'Shampoo',
                'qatar_enabled'           => true,
                'qatar_bottle_size'       => '400 ml',
                'qatar_bottle_status'     => 'finalized',
                'qatar_sticker_status'    => 'ready_for_dieline',
                'pakistan_enabled'        => true,
                'pakistan_bottle_size'    => '200 ml',
                'pakistan_bottle_status'  => 'finalized',
                'pakistan_sticker_status' => 'ready_for_dieline',
            ],
        ];

        $followupDate = now()->addDays(3)->toDateString();

        foreach ($baselineFormulas as $formulaData) {
            $item = ProductRdItem::firstOrCreate(
                ['slug' => $formulaData['slug']],
                $formulaData
            );

            foreach ([$fist, $glen] as $supplier) {
                ProductRdSample::firstOrCreate(
                    [
                        'product_rd_item_id'     => $item->id,
                        'product_rd_supplier_id' => $supplier->id,
                    ],
                    [
                        'version'          => 'V1',
                        'status'           => 'waiting_sample',
                        'verdict'          => 'pending',
                        'next_followup_at' => $followupDate,
                        'quoted_currency'  => 'QAR',
                    ]
                );
            }
        }
    }
}
