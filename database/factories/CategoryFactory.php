<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Danh sách danh mục thực tế.
     * Dùng unique() nên mỗi tên chỉ xuất hiện 1 lần.
     */
    public function definition(): array
    {
        $categories = [
            'Điện thoại', 'Laptop', 'Tablet', 'Phụ kiện',
            'Tai nghe', 'Loa', 'Đồng hồ thông minh', 'Cáp sạc',
            'Ốp lưng', 'Chuột', 'Bàn phím', 'Màn hình',
        ];

        return [
            'name'        => fake()->unique()->randomElement($categories),
            'description' => fake()->sentence(8),
        ];
    }
}
