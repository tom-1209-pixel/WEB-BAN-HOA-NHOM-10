<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Vườn hoa Đà Lạt',       'phone' => '02633512345', 'email' => 'dalat@vuonhoa.vn',      'address' => 'Phường 8, TP. Đà Lạt, Lâm Đồng'],
            ['name' => 'HTX Hoa Sa Đéc',         'phone' => '02773861111', 'email' => 'sadec@hoasadec.vn',     'address' => 'Phường 2, TX. Sa Đéc, Đồng Tháp'],
            ['name' => 'Floral Import Co.',       'phone' => '02812345678', 'email' => 'import@floralvn.com',   'address' => 'Quận 7, TP. Hồ Chí Minh'],
            ['name' => 'Công ty TNHH Hoa Việt',  'phone' => '02438765432', 'email' => 'info@hoaviet.com.vn',   'address' => 'Quận Cầu Giấy, Hà Nội'],
            ['name' => 'Vườn lan Bình Dương',     'phone' => '02743998877', 'email' => 'lan@vuonlanbinhduong.vn', 'address' => 'Thuận An, Bình Dương'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
