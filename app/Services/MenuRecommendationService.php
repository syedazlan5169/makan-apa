<?php

namespace App\Services;

use App\Models\MealRejection;
use App\Models\MealChoice;
use App\Models\MenuItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MenuRecommendationService
{
    public function pick(
        string $personName,
        ?int $categoryId = null,
        array $excludeItemIds = []
    ): ?MenuItem {
        $query = MenuItem::query()
            ->with('category')
            ->where('is_active', true);

        if ($categoryId) {
            $query->where('menu_category_id', $categoryId);
        }

        if (! empty($excludeItemIds)) {
            $query->whereNotIn('id', $excludeItemIds);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return null;
        }

        $recentChoices = MealChoice::query()
            ->where('person_name', $personName)
            ->whereIn('menu_item_id', $items->pluck('id'))
            ->latest('chosen_at')
            ->get()
            ->groupBy('menu_item_id')
            ->map(fn (Collection $choices) => $choices->first());

        $rejectionCounts = MealRejection::query()
            ->where('person_name', $personName)
            ->whereIn('menu_item_id', $items->pluck('id'))
            ->where('rejected_at', '>=', now()->subDays(30))
            ->selectRaw('menu_item_id, COUNT(*) as rejection_count')
            ->groupBy('menu_item_id')
            ->pluck('rejection_count', 'menu_item_id');

        $weightedItems = $items->map(
            function (MenuItem $item) use (
                $recentChoices,
                $rejectionCounts
            ) {
                $lastChoice = $recentChoices->get($item->id);

                $baseWeight = $this->calculateWeight(
                    $lastChoice?->chosen_at
                );

                $rejectionCount = (int) (
                    $rejectionCounts->get($item->id) ?? 0
                );

                $finalWeight = $this->applyRejectionPenalty(
                    $baseWeight,
                    $rejectionCount
                );

                return [
                    'item' => $item,
                    'weight' => $finalWeight,
                ];
            }
        );

        return $this->weightedRandom($weightedItems);
    }

    private function calculateWeight(?Carbon $lastEatenAt): int
    {
        if (! $lastEatenAt) {
            return 100;
        }

        $daysAgo = $lastEatenAt->diffInDays(now());

        return match (true) {
            $daysAgo < 1  => 5,
            $daysAgo < 3  => 15,
            $daysAgo < 7  => 35,
            $daysAgo < 14 => 60,
            $daysAgo < 30 => 80,
            default       => 100,
        };
    }

    private function applyRejectionPenalty(
        int $weight,
        int $rejectionCount
    ): int {
        $multiplier = match (true) {
            $rejectionCount === 0 => 1.00,
            $rejectionCount === 1 => 0.80,
            $rejectionCount === 2 => 0.60,
            $rejectionCount <= 4  => 0.40,
            default               => 0.20,
        };

        return max(
            1,
            (int) round($weight * $multiplier)
        );
    }

    private function weightedRandom(Collection $weightedItems): ?MenuItem
    {
        $totalWeight = $weightedItems->sum('weight');

        if ($totalWeight <= 0) {
            return $weightedItems->first()['item'] ?? null;
        }

        $random = random_int(1, $totalWeight);

        $current = 0;

        foreach ($weightedItems as $entry) {
            $current += $entry['weight'];

            if ($random <= $current) {
                return $entry['item'];
            }
        }

        return $weightedItems->last()['item'] ?? null;
    }
}