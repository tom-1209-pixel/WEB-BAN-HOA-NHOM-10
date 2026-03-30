<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Hoa tươi bó',       'description' => 'Bó hoa tươi các loại, phù hợp tặng sinh nhật, kỷ niệm, lễ tình nhân'],
            ['name' => 'Hoa giỏ / hộp',     'description' => 'Hoa cắm giỏ, hộp hoa sang trọng, tiện lợi, sẵn sàng tặng ngay'],
            ['name' => 'Hoa khai trương',    'description' => 'Kệ hoa, lẵng hoa chúc mừng khai trương, tân gia, thăng chức'],
            ['name' => 'Hoa chia buồn',      'description' => 'Vòng hoa, lẵng hoa chia buồn, tang lễ, trang trọng và tinh tế'],
            ['name' => 'Hoa cưới',           'description' => 'Hoa cầm tay cô dâu, hoa trang trí lễ cưới, xe hoa'],
            ['name' => 'Cây cảnh / chậu',   'description' => 'Cây cảnh mini, chậu cây để bàn, sen đá, cây phong thủy'],
            ['name' => 'Phụ kiện tặng kèm', 'description' => 'Gấu bông, socola, thiệp, nến thơm — tặng kèm bó hoa'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
