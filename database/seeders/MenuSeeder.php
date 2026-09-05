<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Seed Sawit Tomyam / Ikan Bakar menu.
     *
     * Notes:
     * - Prices are approximate because the source menu uses handwritten prices.
     * - Obvious spelling has been normalized (e.g. Black Pepper, Cendawan, Phuket).
     * - A price of 0.00 means the price was not visible / not printed clearly.
     *
     * This seeder is idempotent for the same category + item names:
     * rerunning it updates prices instead of inserting duplicates.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedCategory('Nasi Goreng', [
                ['Nasi Goreng Biasa', 5.00],
                ['Nasi Goreng Cina', 5.00],
                ['Nasi Goreng Cendawan', 6.00],
                ['Nasi Goreng Kampung', 6.00],
                ['Nasi Goreng Cili Api', 5.00],
                ['Nasi Goreng Tomyam', 6.00],
                ['Nasi Goreng Nanas', 6.00],
                ['Nasi Goreng Seafood', 7.50],
                ['Nasi Goreng Sotong', 7.50],
                ['Nasi Goreng Udang', 7.50],
                ['Nasi Goreng Ikan Masin', 6.00],
                ['Nasi Goreng Pattaya', 6.50],
                ['Nasi Goreng Ayam', 7.00],
                ['Nasi Goreng Ikan Rebus', 8.00],
                ['Nasi Goreng Daging Merah', 8.00],
                ['Nasi Goreng U.S.A.', 8.00],
                ['Nasi Goreng Paprik', 7.50],
                ['Nasi Goreng Ayam Kunyit', 8.00],
                ['Nasi Goreng Thai', 8.00],
                ['Nasi Goreng Belacan', 5.00],
                ['Nasi Goreng Ladna', 7.50],
                ['Nasi Goreng Pakapau Daging', 9.00],
                ['Nasi Goreng Pakapau Ayam', 8.50],
                ['Nasi Goreng Phuket', 9.00],
            ]);

            $this->seedCategory('Telur', [
                ['Telur Mata', 1.00],
                ['Telur Dadar', 1.50],
                ['Telur Bistik', 6.50],
                ['Telur Kicap', 6.00],
                ['Telur Merah', 6.00],
                ['Telur Bungkus', 6.50],
            ]);

            $this->seedCategory('Sup', [
                ['Sup Ayam', 6.00],
                ['Sup Daging', 7.00],
                ['Sup Udang', 7.50],
                ['Sup Sotong', 7.50],
                ['Sup Seafood', 8.50],
                ['Sup Campur', 7.00],
                ['Sup Lala', 6.50],
                ['Sup Puyuh', 7.50],
                ['Sup Cendawan', 7.00],
                ['Sup Sayur', 7.00],
                ['Sup Ekor', 15.00],
                ['Sup Tulang', 12.00],
                ['Sup Ketin', 9.00],
                ['Sup Perut Muda', 7.00],
            ]);

            $this->seedCategory('Tomyam', [
                ['Tomyam Ayam', 6.50],
                ['Tomyam Daging', 7.00],
                ['Tomyam Udang', 7.50],
                ['Tomyam Sotong', 7.50],
                ['Tomyam Seafood', 8.00],
                ['Tomyam Campur', 7.00],
                ['Tomyam Lala', 6.50],
                ['Tomyam Puyuh', 7.50],
                ['Tomyam Cendawan', 7.00],
                ['Tomyam Sayur', 7.00],
                ['Tomyam Poktek', 15.00],
            ]);

            $this->seedCategory('Kerabu', [
                ['Kerabu Ayam', 6.00],
                ['Kerabu Daging', 6.50],
                ['Kerabu Udang', 7.50],
                ['Kerabu Sotong', 7.50],
                ['Kerabu Seafood', 8.00],
                ['Kerabu Perut', 6.50],
                ['Kerabu Ikan Bilis', 6.00],
            ]);

            $this->seedCategory(
                'Ayam & Daging',
                $this->variantItems(
                    ['Ayam', 'Daging'],
                    [
                        ['Pedas', 6.50],
                        ['Merah', 6.50],
                        ['Paprik', 6.50],
                        ['Halia', 6.00],
                        ['Kicap', 6.00],
                        ['Black Pepper', 6.50],
                        ['Masam Manis', 6.50],
                        ['Petai', 7.50],
                        ['Campur', 6.50],
                        ['Pakapau', 6.50],
                        ['Belacan', 6.50],
                    ],
                ),
            );

            $this->seedCategory(
                'Sotong & Udang',
                $this->variantItems(
                    ['Sotong', 'Udang'],
                    [
                        ['Pedas', 7.50],
                        ['Merah', 7.50],
                        ['Paprik', 7.50],
                        ['Halia', 7.50],
                        ['Kicap', 7.50],
                        ['Black Pepper', 7.50],
                        ['Masam Manis', 7.50],
                        ['Petai', 8.50],
                        ['Campur', 7.50],
                        ['Goreng Tepung', 7.50],
                        ['Goreng Butter', 8.00],
                    ],
                ),
            );

            $this->seedCategory(
                'Puyuh & Lala',
                $this->variantItems(
                    ['Puyuh', 'Lala'],
                    [
                        ['Pedas', 7.50],
                        ['Merah', 7.50],
                        ['Paprik', 7.50],
                        ['Halia', 7.50],
                        ['Kicap', 7.50],
                        ['Black Pepper', 7.50],
                        ['Masam Manis', 7.50],
                        ['Petai', 8.00],
                        ['3 Rasa', 7.50],
                        ['Kunyit', 7.50],
                    ],
                ),
            );

            $this->seedCategory('Sayur', [
                ['Kailan Goreng Ikan Masin', 5.50],
                ['Kailan Goreng Biasa', 5.00],
                ['Kailan Goreng Belacan', 5.00],
                ['Kangkung Goreng Ikan Masin', 5.50],
                ['Kangkung Goreng Biasa', 5.00],
                ['Kangkung Goreng Belacan', 5.00],
                ['Cendawan Goreng Ikan Masin', 5.50],
                ['Cendawan Goreng Biasa', 5.00],
                ['Cendawan Goreng Belacan', 5.00],
                ['Sayur Campur', 5.00],
            ]);

            $noodleStyles = [
                ['Goreng Biasa', 5.50],
                ['Ladna', 6.00],
                ['Hailam', 6.00],
                ['Bandung', 6.00],
                ['Sup', 6.00],
                ['Kungfu', 6.00],
                ['Tomyam', 6.50],
                ['Celup', 6.00],
            ];

            $this->seedCategory(
                'Mee / Bihun / Kuey Teow / Maggi',
                $this->variantItems(
                    ['Mee', 'Bihun', 'Kuey Teow', 'Maggi'],
                    $noodleStyles,
                ),
            );

            $fishStyles = [
                ['Stim Limau', 0.00],
                ['Stim Halia', 0.00],
                ['Stim Asam Boi', 0.00],
                ['3 Rasa', 0.00],
                ['Masam Manis', 0.00],
                ['Masak Pedas', 0.00],
                ['Singgang', 0.00],
                ['Kengsom', 0.00],
                ['Bakar', 0.00],
            ];

            $this->seedCategory(
                'Ikan Siakap & Ikan Kembung',
                $this->variantItems(
                    ['Ikan Siakap', 'Ikan Kembung'],
                    $fishStyles,
                ),
            );

            $this->seedCategory('Makanan Ringan', [
                ['Cendawan Goreng Tepung', 6.00],
                ['Kentang Goreng', 5.00],
                ['Nugget Goreng', 5.00],
                ['Kerabu Maggi', 7.50],
                ['Keropok', 3.00],
                ['Keropok Lekor', 5.00],
                ['Kerabu Mangga', 4.00],
                ['Somtam', 7.00],
                ['Kambing Bakar', 15.00],
                ['Chicken Chop', 12.00],
                ['Lokcin', 0.00],
            ]);
        });
    }

    /**
     * @param array<int, array{0: string, 1: float|int}> $items
     */
    private function seedCategory(string $categoryName, array $items): void
    {
        $category = MenuCategory::firstOrCreate([
            'name' => $categoryName,
        ]);

        foreach ($items as [$name, $price]) {
            MenuItem::updateOrCreate(
                [
                    'menu_category_id' => $category->id,
                    'name' => $name,
                ],
                [
                    'price' => $price,
                ],
            );
        }
    }

    /**
     * Expand menu-board sections where the same cooking styles apply to
     * multiple bases, e.g. Ayam & Daging or Mee / Bihun / Kuey Teow / Maggi.
     *
     * @param array<int, string> $bases
     * @param array<int, array{0: string, 1: float|int}> $styles
     * @return array<int, array{0: string, 1: float|int}>
     */
    private function variantItems(array $bases, array $styles): array
    {
        $items = [];

        foreach ($bases as $base) {
            foreach ($styles as [$style, $price]) {
                $items[] = ["{$base} {$style}", $price];
            }
        }

        return $items;
    }
}
