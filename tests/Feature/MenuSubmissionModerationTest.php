<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuSubmission;
use App\Models\User;
use App\Services\MenuRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSubmissionModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_visitor_can_submit_a_valid_menu_suggestion(): void
    {
        $category = $this->createCategory();

        $response = $this->post(route('menu-submissions.store'), [
            'menu_category_id' => $category->id,
            'name' => '  Nasi   Goreng Cili Padi  ',
        ]);

        $response->assertRedirect(route('menu-submissions.create'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('menu_submissions', [
            'menu_category_id' => $category->id,
            'name' => 'Nasi Goreng Cili Padi',
            'user_id' => null,
            'status' => MenuSubmission::STATUS_PENDING,
        ]);
    }

    public function test_public_submission_ignores_malicious_moderation_fields(): void
    {
        $category = $this->createCategory();
        $user = User::factory()->admin()->create();

        $this->post(route('menu-submissions.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Nasi Ulam',
            'user_id' => $user->id,
            'status' => MenuSubmission::STATUS_APPROVED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now()->toDateTimeString(),
            'menu_item_id' => 999,
            'role' => 'admin',
        ])->assertRedirect(route('menu-submissions.create'));

        $this->assertDatabaseHas('menu_submissions', [
            'menu_category_id' => $category->id,
            'name' => 'Nasi Ulam',
            'user_id' => null,
            'status' => MenuSubmission::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'menu_item_id' => null,
        ]);
        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_invalid_menu_submission_fails_validation(): void
    {
        $response = $this->post(route('menu-submissions.store'), [
            'menu_category_id' => 999,
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['menu_category_id', 'name']);
        $this->assertDatabaseCount('menu_submissions', 0);
    }

    public function test_existing_menu_item_is_rejected_as_a_duplicate(): void
    {
        $category = $this->createCategory();
        $this->createMenuItem($category, 'Nasi Lemak');

        $response = $this->from(route('menu-submissions.create'))->post(route('menu-submissions.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Nasi Lemak',
        ]);

        $response->assertRedirect(route('menu-submissions.create'));
        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('menu_submissions', 0);
    }

    public function test_whitespace_variant_is_rejected_as_an_existing_menu_item(): void
    {
        $category = $this->createCategory();
        $this->createMenuItem($category, 'Nasi Goreng');

        $this->from(route('menu-submissions.create'))
            ->post(route('menu-submissions.store'), [
                'menu_category_id' => $category->id,
                'name' => 'Nasi   Goreng',
            ])
            ->assertRedirect(route('menu-submissions.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('menu_submissions', 0);
    }

    public function test_equivalent_pending_submission_is_not_duplicated(): void
    {
        $category = $this->createCategory();
        MenuSubmission::create([
            'menu_category_id' => $category->id,
            'name' => 'Mee Bandung',
            'status' => MenuSubmission::STATUS_PENDING,
        ]);

        $response = $this->post(route('menu-submissions.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Mee Bandung',
        ]);

        $response->assertRedirect(route('menu-submissions.create'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('menu_submissions', 1);
    }

    public function test_whitespace_variant_is_not_duplicated_when_pending(): void
    {
        $category = $this->createCategory();
        MenuSubmission::create([
            'menu_category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'status' => MenuSubmission::STATUS_PENDING,
        ]);

        $this->post(route('menu-submissions.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Nasi   Goreng',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('menu_submissions', 1);
    }

    public function test_mysql_is_configured_with_a_case_insensitive_collation(): void
    {
        $this->assertStringEndsWith(
            '_ci',
            (string) config('database.connections.mysql.collation'),
        );
    }

    public function test_rejected_suggestion_may_be_submitted_again(): void
    {
        $category = $this->createCategory();
        MenuSubmission::create([
            'menu_category_id' => $category->id,
            'name' => 'Mee Bandung',
            'status' => MenuSubmission::STATUS_REJECTED,
        ]);

        $this->post(route('menu-submissions.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Mee Bandung',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('menu_submissions', 2);
        $this->assertSame(
            1,
            MenuSubmission::query()
                ->where('status', MenuSubmission::STATUS_PENDING)
                ->count(),
        );
    }

    public function test_public_submission_is_rate_limited_after_five_attempts(): void
    {
        $category = $this->createCategory();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('menu-submissions.store'), [
                'menu_category_id' => $category->id,
                'name' => "Nasi Kerabu {$attempt}",
            ])->assertRedirect(route('menu-submissions.create'));
        }

        $this->post(route('menu-submissions.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Nasi Kerabu Limited',
        ])->assertTooManyRequests();
    }

    public function test_guest_cannot_access_admin_moderation(): void
    {
        $this->get(route('admin.menu-submissions.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_cannot_access_admin_moderation(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.menu-submissions.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_pending_moderation_page(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->createPendingSubmission();

        $this->actingAs($admin)
            ->get(route('admin.menu-submissions.index'))
            ->assertOk()
            ->assertSee($submission->name);
    }

    public function test_admin_can_approve_submission_and_menu_item_becomes_roulette_eligible(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->createPendingSubmission('Nasi Kerabu');

        $this->actingAs($admin)
            ->patch(route('admin.menu-submissions.approve', $submission), [
                'review_notes' => 'Verified on the menu.',
            ])
            ->assertRedirect(route('admin.menu-submissions.index'));

        $this->assertDatabaseHas('menu_items', [
            'menu_category_id' => $submission->menu_category_id,
            'name' => 'Nasi Kerabu',
            'is_active' => true,
        ]);

        $submission->refresh();
        $this->assertSame(MenuSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame($admin->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertNotNull($submission->menu_item_id);

        $choice = app(MenuRecommendationService::class)->pick(
            personName: 'Alice',
            categoryId: $submission->menu_category_id,
        );

        $this->assertSame($submission->menu_item_id, $choice?->id);
    }

    public function test_admin_can_reject_submission_without_creating_a_menu_item(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->createPendingSubmission();

        $this->actingAs($admin)
            ->patch(route('admin.menu-submissions.reject', $submission), [
                'review_notes' => 'Not offered by the restaurant.',
            ])
            ->assertRedirect(route('admin.menu-submissions.index'));

        $submission->refresh();
        $this->assertSame(MenuSubmission::STATUS_REJECTED, $submission->status);
        $this->assertSame($admin->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertNull($submission->menu_item_id);
        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_admin_approval_reuses_an_existing_matching_menu_item(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->createPendingSubmission();
        $menuItem = $this->createMenuItem(
            $submission->category,
            $submission->name,
        );

        $this->actingAs($admin)
            ->patch(route('admin.menu-submissions.approve', $submission))
            ->assertRedirect(route('admin.menu-submissions.index'));

        $submission->refresh();
        $this->assertSame($menuItem->id, $submission->menu_item_id);
        $this->assertSame(MenuSubmission::STATUS_APPROVED, $submission->status);
        $this->assertDatabaseCount('menu_items', 1);
    }

    public function test_reviewed_submission_cannot_be_processed_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->createPendingSubmission();

        $this->actingAs($admin)
            ->patch(route('admin.menu-submissions.approve', $submission))
            ->assertRedirect(route('admin.menu-submissions.index'));

        $this->actingAs($admin)
            ->patch(route('admin.menu-submissions.reject', $submission))
            ->assertSessionHasErrors('submission');

        $submission->refresh();
        $this->assertSame(MenuSubmission::STATUS_APPROVED, $submission->status);
        $this->assertDatabaseCount('menu_items', 1);
    }

    private function createCategory(): MenuCategory
    {
        return MenuCategory::create([
            'name' => 'Nasi',
            'is_active' => true,
        ]);
    }

    private function createMenuItem(MenuCategory $category, string $name): MenuItem
    {
        return MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createPendingSubmission(string $name = 'Nasi Kerabu'): MenuSubmission
    {
        $category = $this->createCategory();

        return MenuSubmission::create([
            'menu_category_id' => $category->id,
            'name' => $name,
            'status' => MenuSubmission::STATUS_PENDING,
        ]);
    }
}