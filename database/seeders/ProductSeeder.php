<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $skincare = Category::where('name', 'Skincare')->first();
        $makeup = Category::where('name', 'Makeup')->first();
        $haircare = Category::where('name', 'Hair Care')->first();
        $fragrance = Category::where('name', 'Fragrance')->first();
        $tools = Category::where('name', 'Tools & Accessories')->first();

        $products = [
            // Skincare
            [
                'name' => 'Vitamin C Serum',
                'description' => 'Brightening vitamin C serum for radiant skin',
                'sku' => 'SKN001',
                'barcode' => '1234567890123',
                'price' => 29.99,
                'cost_price' => 15.00,
                'stock_quantity' => 50,
                'min_stock_level' => 10,
                'category_id' => $skincare->id,
                'brand' => 'ChicGlow',
                'weight' => 30.0,
                'is_active' => true
            ],
            [
                'name' => 'Hyaluronic Acid Moisturizer',
                'description' => 'Deep hydrating moisturizer with hyaluronic acid',
                'sku' => 'SKN002',
                'barcode' => '1234567890124',
                'price' => 24.99,
                'cost_price' => 12.50,
                'stock_quantity' => 30,
                'min_stock_level' => 5,
                'category_id' => $skincare->id,
                'brand' => 'ChicGlow',
                'weight' => 50.0,
                'is_active' => true
            ],
            // Makeup
            [
                'name' => 'Matte Liquid Lipstick',
                'description' => 'Long-lasting matte liquid lipstick',
                'sku' => 'MKP001',
                'barcode' => '1234567890125',
                'price' => 18.99,
                'cost_price' => 8.00,
                'stock_quantity' => 75,
                'min_stock_level' => 15,
                'category_id' => $makeup->id,
                'brand' => 'ChicColor',
                'weight' => 15.0,
                'is_active' => true
            ],
            [
                'name' => 'Foundation - Medium',
                'description' => 'Full coverage liquid foundation',
                'sku' => 'MKP002',
                'barcode' => '1234567890126',
                'price' => 32.99,
                'cost_price' => 16.00,
                'stock_quantity' => 40,
                'min_stock_level' => 8,
                'category_id' => $makeup->id,
                'brand' => 'ChicColor',
                'weight' => 30.0,
                'is_active' => true
            ],
            // Hair Care
            [
                'name' => 'Argan Oil Shampoo',
                'description' => 'Nourishing shampoo with argan oil',
                'sku' => 'HAR001',
                'barcode' => '1234567890127',
                'price' => 16.99,
                'cost_price' => 8.50,
                'stock_quantity' => 60,
                'min_stock_level' => 12,
                'category_id' => $haircare->id,
                'brand' => 'ChicHair',
                'weight' => 250.0,
                'is_active' => true
            ],
            // Fragrance
            [
                'name' => 'Rose Garden Perfume',
                'description' => 'Elegant floral fragrance',
                'sku' => 'FRG001',
                'barcode' => '1234567890128',
                'price' => 45.99,
                'cost_price' => 22.00,
                'stock_quantity' => 25,
                'min_stock_level' => 5,
                'category_id' => $fragrance->id,
                'brand' => 'ChicScent',
                'weight' => 50.0,
                'is_active' => true
            ],
            // Tools
            [
                'name' => 'Makeup Brush Set',
                'description' => 'Professional 12-piece makeup brush set',
                'sku' => 'TLS001',
                'barcode' => '1234567890129',
                'price' => 39.99,
                'cost_price' => 18.00,
                'stock_quantity' => 20,
                'min_stock_level' => 3,
                'category_id' => $tools->id,
                'brand' => 'ChicTools',
                'weight' => 200.0,
                'is_active' => true
            ]
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}