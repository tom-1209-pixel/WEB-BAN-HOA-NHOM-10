<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * THU TU QUAN TRONG: Bang cha truoc, bang con sau.
     * users, categories, suppliers khong phu thuoc bang nao --> chay truoc.
     * products phu thuoc categories --> chay sau.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
