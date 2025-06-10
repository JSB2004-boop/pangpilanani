<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Gender;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $maleGender = Gender::where('gender', 'Male')->first();

        User::create([
            'role_id' => $adminRole->id,
            'employee_id' => 'EMP001',
            'first_name' => 'Admin',
            'middle_name' => null,
            'last_name' => 'User',
            'suffix_name' => null,
            'age' => 30,
            'birth_date' => '1994-01-01',
            'gender_id' => $maleGender->gender_id,
            'address' => 'ChicCheckout Headquarters',
            'contact_number' => '+1234567890',
            'phone' => '+1234567890',
            'email' => 'admin@chiccheckout.com',
            'password' => Hash::make('admin123'),
            'hire_date' => now(),
            'salary' => 50000.00,
            'is_active' => true,
            'is_deleted' => false,
        ]);
    }
}