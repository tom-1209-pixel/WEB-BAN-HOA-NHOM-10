<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $avgImportPrice = fake()->randomFloat(2, 100000, 50000000);
        $profitRate     = fake()->randomFloat(2, 10, 50);
        $sellingPrice   = round($avgImportPrice * (1 + $profitRate / 100), 2);

        return [
            'code'                => strtoupper(fake()->unique()->bothify('SP-####')),
            'name'                => fake()->words(3, true),
            'category_id'         => Category::factory(),
            'description'         => fake()->paragraph(2),
            'unit'                => fake()->randomElement(['cái', 'hộp', 'bộ', 'chiếc']),
            'quantity'            => fake()->numberBetween(5, 200),
            'low_stock_threshold' => fake()->randomElement([5, 10, 15, 20]),
            'image'               => null,
            'profit_rate'         => $profitRate,
            'avg_import_price'    => $avgImportPrice,
            'selling_price'       => $sellingPrice,
            'status'              => 'visible',
        ];
    }

    /**
     * State: Sản phẩm sắp hết hàng (quantity <= low_stock_threshold).
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity'            => fake()->numberBetween(1, 5),
            'low_stock_threshold' => 10,
        ]);
    }

    /**
     * State: Sản phẩm đã ẩn (ngừng kinh doanh).
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'hidden',
        ]);
    }
}
