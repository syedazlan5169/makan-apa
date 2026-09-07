<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuSubmissionRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MenuSubmissionController extends Controller
{
    public function create(): View
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('menu-submissions.create', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreMenuSubmissionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $outcome = DB::transaction(function () use ($validated, $request): string {
            MenuCategory::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($validated['menu_category_id']);

            $existingMenuItem = MenuItem::query()
                ->where('menu_category_id', $validated['menu_category_id'])
                ->where('name', $validated['name'])
                ->exists();

            if ($existingMenuItem) {
                return 'existing-menu-item';
            }

            $pendingSubmissionExists = MenuSubmission::query()
                ->where('menu_category_id', $validated['menu_category_id'])
                ->where('name', $validated['name'])
                ->where('status', MenuSubmission::STATUS_PENDING)
                ->exists();

            if ($pendingSubmissionExists) {
                return 'pending-submission';
            }

            MenuSubmission::create([
                'menu_category_id' => $validated['menu_category_id'],
                'name' => $validated['name'],
                'user_id' => $request->user()?->id,
                'status' => MenuSubmission::STATUS_PENDING,
            ]);

            return 'created';
        });

        if ($outcome === 'existing-menu-item') {
            return back()
                ->withInput()
                ->withErrors(['name' => 'This menu item already exists in the selected category.']);
        }

        return redirect()
            ->route('menu-submissions.create')
            ->with('success', 'Thanks for the suggestion. It is awaiting admin approval.');
    }
}