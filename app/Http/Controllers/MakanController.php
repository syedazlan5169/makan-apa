<?php

namespace App\Http\Controllers;

use App\Models\MealRejection;
use App\Services\MenuRecommendationService;
use App\Models\MealChoice;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MakanController extends Controller
{
    public function index(): View
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $recentChoices = MealChoice::query()
            ->with('menuItem.category')
            ->latest('chosen_at')
            ->limit(10)
            ->get();

        $rememberedPersonName = session()->get('makan.current_person_name');

        return view('makan.index', [
            'categories' => $categories,
            'recentChoices' => $recentChoices,
            'rememberedPersonName' => $rememberedPersonName,
        ]);
    }

    public function pick(
        Request $request,
        MenuRecommendationService $recommendationService
    ): View|RedirectResponse {
        $validated = $request->validate([
            'person_name' => [
                'required',
                'string',
                'max:50',
            ],

            'category_id' => [
                'nullable',
                'integer',
                'exists:menu_categories,id',
            ],

            'rejected_item_id' => [
                'nullable',
                'integer',
                'exists:menu_items,id',
            ],
        ]);

        $personName = trim($validated['person_name']);
        $sessionPersonName = $request->session()->get('makan.current_person_name');

        /*
        * Validate identity: if session has a person, request must match.
        * This prevents accidental form tampering or stale form submissions.
        */
        if ($sessionPersonName !== null && $personName !== $sessionPersonName) {
            return back()
                ->withInput()
                ->withErrors([
                    'person_name' => 'Identity mismatch. Please use "Switch user" to change.',
                ]);
        }

        /*
        * If person changed or this is first time, clear roulette rejections
        * to prevent state leakage between people.
        */
        if ($sessionPersonName === null || $personName !== $sessionPersonName) {
            $request->session()->forget('makan.rejected_item_ids');
        }

        /*
        * Store person in session as authoritative identity.
        */
        $request->session()->put('makan.current_person_name', $personName);

        $categoryId = isset($validated['category_id'])
            ? (int) $validated['category_id']
            : null;


        /*
        * If rejected_item_id is absent, this is a brand-new
        * roulette session.
        */
        if (empty($validated['rejected_item_id'])) {
            $request->session()->forget('makan.rejected_item_ids');
        }


        $rejectedItemIds = $request->session()->get(
            'makan.rejected_item_ids',
            []
        );


        /*
        * User clicked "Pick something else".
        */
        if (! empty($validated['rejected_item_id'])) {

            $rejectedItemId = (int) $validated['rejected_item_id'];

            /*
            * Permanent rejection history.
            */
            MealRejection::create([
                'person_name' => $personName,
                'menu_item_id' => $rejectedItemId,
                'rejected_at' => now(),
            ]);


            /*
            * Temporary rejection history for this roulette.
            */
            $rejectedItemIds[] = $rejectedItemId;

            $rejectedItemIds = array_values(
                array_unique($rejectedItemIds)
            );

            $request->session()->put(
                'makan.rejected_item_ids',
                $rejectedItemIds
            );
        }

        $menuItem = $recommendationService->pick(
            personName: $personName,
            categoryId: $categoryId,
            excludeItemIds: $rejectedItemIds,
        );


        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $recentChoices = MealChoice::query()
            ->with('menuItem.category')
            ->latest('chosen_at')
            ->limit(10)
            ->get();

        $rememberedPersonName = $request->session()->get('makan.current_person_name');

        return view('makan.index', [
            'categories' => $categories,
            'recentChoices' => $recentChoices,
            'rememberedPersonName' => $rememberedPersonName,
            'menuItem' => $menuItem,
            'personName' => $personName,
            'selectedCategoryId' => $categoryId,
            'rejectedCount' => count($rejectedItemIds),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person_name' => ['required', 'string', 'max:50'],
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
        ]);

        $personName = trim($validated['person_name']);
        $sessionPersonName = $request->session()->get('makan.current_person_name');

        /*
        * Validate identity: person_name in request must match session.
        */
        if ($personName !== $sessionPersonName) {
            return back()
                ->withInput()
                ->withErrors([
                    'person_name' => 'Identity mismatch. Please use "Switch user" to change.',
                ]);
        }

        $menuItem = MenuItem::findOrFail($validated['menu_item_id']);

        MealChoice::create([
            'person_name' => $personName,
            'menu_item_id' => $menuItem->id,
            'chosen_at' => now(),
        ]);

        $request->session()->forget('makan.rejected_item_ids');

        return redirect()
            ->route('makan.index')
            ->with(
                'success',
                "{$personName} makan {$menuItem->name} hari ni! 🍽️"
            );
    }

    public function switchUser(Request $request): RedirectResponse
    {
        /*
        * Clear both session keys to reset identity and roulette state.
        * User must explicitly choose a new identity on the next request.
        */
        $request->session()->forget('makan.current_person_name');
        $request->session()->forget('makan.rejected_item_ids');

        return redirect()
            ->route('makan.index')
            ->with('success', 'Ready for the next person!');
    }
}