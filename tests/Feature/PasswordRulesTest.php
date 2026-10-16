<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** NEW: Create account needs a strong password (rules in AppServiceProvider → Password::defaults()). */
class PasswordRulesTest extends TestCase
{
    use RefreshDatabase;

    private function signUp(string $password)
    {
        return $this->post('/register', ['email' => 'new@lnu.edu.ph', 'name' => 'newbie', 'password' => $password]);
    }

    public function test_weak_passwords_are_refused_with_the_reason(): void
    {
        $weak = [
            'Ab1!'         => 'at least 8 characters',
            'password1!'   => 'one uppercase and one lowercase letter',
            'PASSWORD1!'   => 'one uppercase and one lowercase letter',
            'Password!!'   => 'at least one number',
            'Password123'  => 'at least one symbol',
        ];

        foreach ($weak as $password => $reason) {
            $this->signUp($password)->assertSessionHasErrorsIn('register', 'password');
            $this->assertStringContainsString($reason, session('errors')->getBag('register')->first('password'), "'{$password}' should fail: {$reason}");
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_a_strong_password_creates_the_account(): void
    {
        $this->signUp('Miré-Film2026')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs(User::where('name', 'newbie')->sole());
    }

    public function test_the_sign_up_box_lists_the_rules(): void
    {
        $this->get('/')->assertSee('data-password-rules', false)
            ->assertSee('data-password-toggle="password"', false)->assertSee('aria-label="Show password"', false)  // ← NEW: 👁 show / hide button
            ->assertSeeInOrder(['At least 8 characters', 'An uppercase and a lowercase letter', 'A number', 'A symbol']);
    }

    public function test_existing_members_with_old_passwords_can_still_sign_in(): void
    {
        User::factory()->create(['name' => 'oldtimer', 'password' => 'password123']);              // ← made before the rules

        $this->postJson('/login', ['name' => 'oldtimer', 'password' => 'password123'])->assertOk();
    }
}
