<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\MenuSubmission;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MenuSubmissionModerationService
{
    public function approve(
        MenuSubmission $submission,
        User $reviewer,
        ?string $reviewNotes = null,
    ): MenuSubmission {
        return DB::transaction(function () use ($submission, $reviewer, $reviewNotes): MenuSubmission {
            $submission = MenuSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submission->id);

            $this->ensurePending($submission);

            MenuCategory::query()
                ->lockForUpdate()
                ->findOrFail($submission->menu_category_id);

            $menuItem = $this->findOrCreateMenuItem($submission);

            if (! $menuItem->is_active) {
                $menuItem->update(['is_active' => true]);
            }

            $submission->update([
                'status' => MenuSubmission::STATUS_APPROVED,
                'menu_item_id' => $menuItem->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $this->normalizeNotes($reviewNotes),
            ]);

            return $submission->fresh();
        });
    }

    public function reject(
        MenuSubmission $submission,
        User $reviewer,
        ?string $reviewNotes = null,
    ): MenuSubmission {
        return DB::transaction(function () use ($submission, $reviewer, $reviewNotes): MenuSubmission {
            $submission = MenuSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submission->id);

            $this->ensurePending($submission);

            $submission->update([
                'status' => MenuSubmission::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $this->normalizeNotes($reviewNotes),
            ]);

            return $submission->fresh();
        });
    }

    private function ensurePending(MenuSubmission $submission): void
    {
        if (! $submission->isPending()) {
            throw new DomainException('This submission has already been reviewed.');
        }
    }

    private function normalizeNotes(?string $reviewNotes): ?string
    {
        $reviewNotes = trim((string) $reviewNotes);

        return $reviewNotes === '' ? null : $reviewNotes;
    }

    private function findOrCreateMenuItem(MenuSubmission $submission): MenuItem
    {
        $attributes = [
            'menu_category_id' => $submission->menu_category_id,
            'name' => $submission->name,
        ];

        $menuItem = MenuItem::query()
            ->where($attributes)
            ->lockForUpdate()
            ->first();

        if ($menuItem) {
            return $menuItem;
        }

        try {
            return MenuItem::create([
                ...$attributes,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            return MenuItem::query()
                ->where($attributes)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }
}