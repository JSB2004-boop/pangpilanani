<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system access and user management'
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Manage products, view reports, and supervise operations'
            ],
            [
                'name' => 'cashier',
                'display_name' => 'Cashier',
                'description' => 'Process transactions and handle customer service'
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}