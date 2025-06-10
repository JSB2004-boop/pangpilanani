<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gender;

class GenderSeeder extends Seeder
{
    public function run(): void
    {
        $genders = [
            ['gender' => 'Male'],
            ['gender' => 'Female'],
            ['gender' => 'Other'],
        ];

        foreach ($genders as $gender) {
            Gender::create($gender);
        }
    }
}