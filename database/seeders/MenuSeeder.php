<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Nasi Goreng' => [
                ['Nasi Goreng Kampung', 8.50],
                ['Nasi Goreng Pattaya', 9.00],
                ['Nasi Goreng Cina', 8.00],
                ['Nasi Goreng Tomyam', 9.00],
                ['Nasi Goreng Seafood', 10.00],
            ],

            'Mee / Bihun / Kuey Teow' => [
                ['Mee Goreng Mamak', 8.00],
                ['Bihun Goreng', 8.00],
                ['Kuey Teow Goreng', 8.00],
                ['Mee Bandung', 9.00],
                ['Mee Hailam', 9.00],
            ],

            'Set Nasi' => [
                ['Ayam Paprik + Nasi', 9.00],
                ['Daging Paprik + Nasi', 10.00],
                ['Ayam Kunyit + Nasi', 9.00],
                ['Daging Merah + Nasi', 10.00],
                ['Ayam Masak Pedas + Nasi', 9.00],
            ],

            'Western' => [
                ['Chicken Chop', 15.00],
                ['Fish and Chips', 16.00],
                ['Grilled Chicken', 15.00],
                ['Chicken Burger', 10.00],
            ],

            'Soup / Tomyam' => [
                ['Tomyam Ayam', 9.00],
                ['Tomyam Seafood', 12.00],
                ['Sup Ayam', 8.00],
                ['Sup Daging', 9.00],
            ],
        ];

        foreach ($categories as $categoryName => $items) {
            $category = MenuCategory::create([
                'name' => $categoryName,
            ]);

            foreach ($items as [$name, $price]) {
                MenuItem::create([
                    'menu_category_id' => $category->id,
                    'name' => $name,
                    'price' => $price,
                ]);
            }
        }
    }
}