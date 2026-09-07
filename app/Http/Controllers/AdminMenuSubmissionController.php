<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewMenuSubmissionRequest;
use App\Models\MenuSubmission;
use App\Services\MenuSubmissionModerationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminMenuSubmissionController extends Controller
{
    public function index(): View
    {
        $submissions = MenuSubmission::query()
            ->with(['category', 'submitter'])
            ->where('status', MenuSubmission::STATUS_PENDING)
            ->oldest()
            ->paginate(20);

        return view('admin.menu-submissions.index', [
            'submissions' => $submissions,
        ]);
    }

    public function approve(
        ReviewMenuSubmissionRequest $request,
        MenuSubmission $menuSubmission,
        MenuSubmissionModerationService $moderationService,
    ): RedirectResponse {
        return $this->review(
            $request,
            $menuSubmission,
            $moderationService,
            'approve',
        );
    }

    public function reject(
        ReviewMenuSubmissionRequest $request,
        MenuSubmission $menuSubmission,
        MenuSubmissionModerationService $moderationService,
    ): RedirectResponse {
        return $this->review(
            $request,
            $menuSubmission,
            $moderationService,
            'reject',
        );
    }

    private function review(
        ReviewMenuSubmissionRequest $request,
        MenuSubmission $submission,
        MenuSubmissionModerationService $moderationService,
        string $decision,
    ): RedirectResponse {
        try {
            $reviewNotes = $request->validated('review_notes');

            if ($decision === 'approve') {
                $moderationService->approve($submission, $request->user(), $reviewNotes);

                return redirect()
                    ->route('admin.menu-submissions.index')
                    ->with('success', 'Menu submission approved.');
            }

            $moderationService->reject($submission, $request->user(), $reviewNotes);

            return redirect()
                ->route('admin.menu-submissions.index')
                ->with('success', 'Menu submission rejected.');
        } catch (DomainException $exception) {
            return back()->withErrors(['submission' => $exception->getMessage()]);
        }
    }
}