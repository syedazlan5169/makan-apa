<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\MealChoice;
use App\Models\MealRejection;

class RememberedPersonNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestMenu();
    }

    private function createTestMenu(): void
    {
        $category = MenuCategory::create([
            'name' => 'Nasi',
            'is_active' => true,
        ]);

        MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Nasi Lemak',
            'is_active' => true,
        ]);

        MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Nasi Kuning',
            'is_active' => true,
        ]);

        MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'is_active' => true,
        ]);
    }

    /**
     * Test that first person's name is stored in session.
     */
    public function test_first_persons_name_is_stored_in_session(): void
    {
        $response = $this->post(route('makan.pick'), [
            'person_name' => 'Alice',
            'category_id' => null,
        ]);

        $response->assertOk();
        $this->assertEquals('Alice', session()->get('makan.current_person_name'));
    }

    /**
     * Test that remembered name is passed to view on GET /.
     */
    public function test_remembered_name_is_passed_to_view(): void
    {
        // Set session manually
        $this->session(['makan.current_person_name' => 'Bob']);

        $response = $this->get(route('makan.index'));

        $response->assertOk();
        $response->assertViewHas('rememberedPersonName', 'Bob');
    }

    /**
     * Test that same person's name in request passes validation.
     */
    public function test_same_person_name_passes_validation(): void
    {
        $this->session(['makan.current_person_name' => 'Alice']);

        $response = $this->post(route('makan.pick'), [
            'person_name' => 'Alice',
        ]);

        $response->assertOk();
        $this->assertEquals('Alice', session()->get('makan.current_person_name'));
    }

    /**
     * Test that different person_name in request fails validation.
     */
    public function test_different_person_name_fails_validation(): void
    {
        $this->session(['makan.current_person_name' => 'Alice']);

        $response = $this->post(route('makan.pick'), [
            'person_name' => 'Bob',
        ]);

        $response->assertStatus(302); // Redirect on validation failure
        $response->assertSessionHasErrors('person_name');
        $this->assertEquals('Alice', session()->get('makan.current_person_name'));
    }

    /**
     * Test that switching user clears both session keys.
     */
    public function test_switch_user_clears_both_session_keys(): void
    {
        // Set up initial state
        $this->session([
            'makan.current_person_name' => 'Alice',
            'makan.rejected_item_ids' => [1, 2, 3],
        ]);

        $response = $this->post(route('makan.switch'));

        $response->assertRedirect(route('makan.index'));
        $this->assertNull(session()->get('makan.current_person_name'));
        $this->assertNull(session()->get('makan.rejected_item_ids'));
    }

    /**
     * Test that new person after switch starts with empty rejections.
     */
    public function test_new_person_after_switch_starts_fresh(): void
    {
        // Create meal choices for Alice
        MealChoice::create([
            'person_name' => 'Alice',
            'menu_item_id' => 1,
            'chosen_at' => now(),
        ]);

        // Set up Alice's session with rejections
        $this->session([
            'makan.current_person_name' => 'Alice',
            'makan.rejected_item_ids' => [1],
        ]);

        // Switch user
        $this->post(route('makan.switch'));

        // Bob enters
        $response = $this->post(route('makan.pick'), [
            'person_name' => 'Bob',
        ]);

        $response->assertOk();
        $this->assertEquals('Bob', session()->get('makan.current_person_name'));
        $this->assertEmpty(session()->get('makan.rejected_item_ids') ?? []);

        // Bob's recommendation should include item 1 (not affected by Alice's history in session)
        $response->assertViewHas('menuItem');
    }

    /**
     * Test that accept validates person_name matches session.
     */
    public function test_accept_validates_person_name_match(): void
    {
        $this->session(['makan.current_person_name' => 'Alice']);

        // Attempt to accept with mismatched person_name
        $response = $this->post(route('makan.accept'), [
            'person_name' => 'Bob',
            'menu_item_id' => 1,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('person_name');
    }

    /**
     * Test that accept succeeds with matching person_name.
     */
    public function test_accept_succeeds_with_matching_person_name(): void
    {
        $this->session(['makan.current_person_name' => 'Alice']);

        $response = $this->post(route('makan.accept'), [
            'person_name' => 'Alice',
            'menu_item_id' => 1,
        ]);

        $response->assertRedirect(route('makan.index'));

        // Verify MealChoice was created
        $this->assertDatabaseHas('meal_choices', [
            'person_name' => 'Alice',
            'menu_item_id' => 1,
        ]);

        // Session person name should persist
        $this->assertEquals('Alice', session()->get('makan.current_person_name'));
    }

    /**
     * Test that rejection state is cleared when person changes.
     */
    public function test_rejection_state_cleared_when_person_changes(): void
    {
        // Alice has rejections in session
        $this->session([
            'makan.current_person_name' => 'Alice',
            'makan.rejected_item_ids' => [1, 2],
        ]);

        // Try to manually change to Bob
        $response = $this->post(route('makan.pick'), [
            'person_name' => 'Bob',
        ]);

        // Should fail validation
        $response->assertSessionHasErrors('person_name');

        // Switch explicitly
        $this->post(route('makan.switch'));

        // Now Bob enters
        $this->post(route('makan.pick'), [
            'person_name' => 'Bob',
        ]);

        // Bob's rejections should be empty (not Alice's)
        $this->assertEmpty(session()->get('makan.rejected_item_ids') ?? []);
    }

    /**
     * Test that trimmed person name is stored (whitespace handling).
     */
    public function test_person_name_is_trimmed(): void
    {
        $response = $this->post(route('makan.pick'), [
            'person_name' => '  Alice  ',
        ]);

        $response->assertOk();
        $this->assertEquals('Alice', session()->get('makan.current_person_name'));
    }

    /**
     * Test that session expires, person name is forgotten.
     */
    public function test_session_expiry_clears_remembered_name(): void
    {
        // Set up session
        $this->session(['makan.current_person_name' => 'Alice']);

        // Verify it's there
        $this->assertEquals('Alice', session()->get('makan.current_person_name'));

        // Session cleared (simulate expiry)
        session()->flush();

        // On next GET, no remembered name
        $response = $this->get(route('makan.index'));
        $response->assertViewHas('rememberedPersonName', null);
    }

    /**
     * Test that rejection history is stored per person correctly.
     */
    public function test_rejection_history_remains_separate_per_person(): void
    {
        // Alice rejects item 1
        MealRejection::create([
            'person_name' => 'Alice',
            'menu_item_id' => 1,
            'rejected_at' => now(),
        ]);

        // Bob rejects item 2
        MealRejection::create([
            'person_name' => 'Bob',
            'menu_item_id' => 2,
            'rejected_at' => now(),
        ]);

        // Verify records exist
        $this->assertDatabaseCount('meal_rejections', 2);
        $this->assertDatabaseHas('meal_rejections', [
            'person_name' => 'Alice',
            'menu_item_id' => 1,
        ]);
        $this->assertDatabaseHas('meal_rejections', [
            'person_name' => 'Bob',
            'menu_item_id' => 2,
        ]);
    }

    /**
     * Test that pick with rejected_item_id still adds to session rejections.
     */
    public function test_rejected_item_id_adds_to_session_rejections(): void
    {
        $this->session(['makan.current_person_name' => 'Alice']);

        $response = $this->post(route('makan.pick'), [
            'person_name' => 'Alice',
            'rejected_item_id' => 1,
        ]);

        $response->assertOk();

        // Item should be in rejected list
        $rejectedIds = session()->get('makan.rejected_item_ids', []);
        $this->assertContains(1, $rejectedIds);

        // Rejection should be in database
        $this->assertDatabaseHas('meal_rejections', [
            'person_name' => 'Alice',
            'menu_item_id' => 1,
        ]);
    }
}
