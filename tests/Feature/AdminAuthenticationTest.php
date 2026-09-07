<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'ADMIN@example.com',
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.menu-submissions.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_invalid_admin_login_does_not_authenticate_user(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_member_cannot_log_in_to_admin_area(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'correct-password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'member@example.com',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.store'), [
                'email' => 'admin@example.com',
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }
}