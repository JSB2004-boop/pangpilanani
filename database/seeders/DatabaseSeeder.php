<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            GenderSeeder::class,
            CategorySeeder::class,
            AdminUserSeeder::class,
            ProductSeeder::class,
        ]);
    }
}