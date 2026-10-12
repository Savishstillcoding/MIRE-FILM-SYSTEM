<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_fields_show_the_side_warning_and_mark_both_fields(): void
    {
        $this->from('/')->post('/login', ['name' => '', 'password' => ''])->assertRedirect('/');

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="signin-warning" role="alert"\s*>/', $html); // not hidden
        $this->assertMatchesRegularExpression('/id="signin-error" role="alert"\s+hidden/', $html);
        $this->assertSame(2, substr_count($html, 'form-control form-control-lg is-invalid'));
        $this->assertStringContainsString('data-autoopen', $html);
        $this->assertGuest();
    }

    public function test_server_messages_say_username_and_password(): void
    {
        $this->from('/')->post('/login', ['name' => '', 'password' => ''])
            ->assertSessionHasErrorsIn('login', ['name' => 'Enter your username.', 'password' => 'Enter your password.']);
    }

    public function test_one_empty_field_only_marks_that_field(): void
    {
        $this->from('/')->post('/login', ['name' => 'someone', 'password' => '']);

        $this->get('/')->assertSee('value="someone"', false)
            ->assertSee('id="login-password" name="password" type="password" class="form-control form-control-lg is-invalid"', false)
            ->assertDontSee('id="login-name" name="name" type="text" class="form-control form-control-lg is-invalid"', false);
    }

    public function test_wrong_password_shows_the_incorrect_message_not_the_empty_warning(): void
    {
        User::factory()->create(['name' => 'realuser', 'password' => 'password123']);

        $this->from('/')->post('/login', ['name' => 'realuser', 'password' => 'nope']);

        $html = $this->get('/')->assertSee('Incorrect details')->assertSee('Username or password is incorrect.')->getContent();
        $this->assertMatchesRegularExpression('/id="signin-error" role="alert"\s*>/', $html);     // shown beside the box
        $this->assertMatchesRegularExpression('/id="signin-warning" role="alert"\s+hidden/', $html); // empty-fields card stays hidden
        $this->assertStringNotContainsString('alert alert-danger', $html);                         // no old in-box alert
        $this->assertGuest();
    }

    public function test_background_sign_in_with_wrong_details_answers_without_a_redirect(): void
    {
        User::factory()->create(['name' => 'realuser', 'password' => 'password123']);

        $this->postJson('/login', ['name' => 'realuser', 'password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('errors.credentials.0', 'Username or password is incorrect.');
        $this->assertGuest();
    }

    public function test_background_sign_in_with_empty_fields_lists_them(): void
    {
        $this->postJson('/login', ['name' => '', 'password' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Enter your username.', 'password' => 'Enter your password.']);
    }

    public function test_background_sign_in_success_tells_the_page_where_to_go(): void
    {
        $user = User::factory()->create(['name' => 'realuser', 'password' => 'password123']);

        $this->postJson('/login', ['name' => 'realuser', 'password' => 'password123'])
            ->assertOk()->assertJson(['redirect' => route('home')]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_filled_in_correctly_signs_in(): void
    {
        $user = User::factory()->create(['name' => 'realuser', 'password' => 'password123']);

        $this->post('/login', ['name' => 'realuser', 'password' => 'password123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }
}
