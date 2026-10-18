<?php

namespace Database\Factories;

use App\Models\EcommerceCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EcommerceCustomer>
 */
class EcommerceCustomerFactory extends Factory
{
    protected $model = EcommerceCustomer::class;

    public function definition(): array
    {
        $isQatar = $this->faker->boolean(45);

        // Normalized digits-only phone (Qatar 97455xxxxxx or Pakistan 92300xxxxxxx)
        $phone = $isQatar
            ? '974' . $this->faker->randomElement(['33', '50', '55', '66', '77']) . $this->faker->unique()->numerify('######')
            : '923' . $this->faker->randomElement(['00', '01', '12', '21', '33', '45']) . $this->faker->unique()->numerify('#######');

        return [
            'name' => $this->faker->name(),
            'phone' => $phone,
            'email' => $this->faker->unique()->safeEmail(),
            'total_spend' => 0,
            'total_orders' => 0,
            'last_purchase_at' => null,
        ];
    }
}
