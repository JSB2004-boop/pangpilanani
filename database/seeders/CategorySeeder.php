<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Skincare',
                'description' => 'Face and body skincare products',
                'is_active' => true
            ],
            [
                'name' => 'Makeup',
                'description' => 'Cosmetics and makeup products',
                'is_active' => true
            ],
            [
                'name' => 'Hair Care',
                'description' => 'Shampoo, conditioner, and hair treatments',
                'is_active' => true
            ],
            [
                'name' => 'Fragrance',
                'description' => 'Perfumes and body sprays',
                'is_active' => true
            ],
            [
                'name' => 'Tools & Accessories',
                'description' => 'Beauty tools and accessories',
                'is_active' => true
            ]
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}