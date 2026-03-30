<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin cố định
        User::create([
            'username'  => 'admin',
            'email'     => 'admin@example.com',
            'password'  => Hash::make('123456'),
            'full_name' => 'Quản trị viên',
            'phone'     => '0901234567',
            'street'    => '123 Nguyễn Huệ',
            'ward'      => 'Bến Nghé',
            'district'  => 'Quận 1',
            'city'      => 'TP. Hồ Chí Minh',
            'role'      => UserRole::Admin,
            'is_active' => true,
            'api_token' => Str::random(60),
        ]);

        // User cố định để test
        User::create([
            'username'  => 'user1',
            'email'     => 'user1@example.com',
            'password'  => Hash::make('123456'),
            'full_name' => 'Nguyễn Văn A',
            'phone'     => '0909876543',
            'street'    => '456 Lê Lợi',
            'ward'      => 'Phường 1',
            'district'  => 'Quận 3',
            'city'      => 'TP. Hồ Chí Minh',
            'role'      => UserRole::User,
            'is_active' => true,
            'api_token' => Str::random(60),
        ]);

        // Tạo thêm 5 user ngẫu nhiên bằng Factory
        User::factory()->count(5)->create();
    }
}
