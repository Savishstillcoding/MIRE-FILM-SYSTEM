<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Secured routing (URL): signed-out visitors can't open any members-only page or use any member action,
 * and a signed-in user stays signed in across refreshes. Rules live in routes/web.php.
 */
class RouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Every members-only page. */
    private const PAGES = ['/films', '/films?q=matrix', '/films/arcane', '/lists', '/profile', '/profile/likes', '/profile/watchlist', '/settings'];

    public function test_signed_out_visitors_cannot_open_any_members_only_page(): void
    {
        foreach (self::PAGES as $url) {
            $this->get($url)->assertRedirect('/login');               // the 'auth' middleware bounces them…
            $this->get('/login')->assertRedirect(route('home'));       // …to the landing page…
            $this->get('/')->assertOk()
                ->assertSee('Sign in required')                        // …with the sign-in box open and the reason shown
                ->assertSee('data-autoopen', false)
                ->assertDontSee('Welcome back,');                      // and none of the member home content
        }
        $this->assertGuest();
    }

    public function test_signed_out_visitors_cannot_use_member_actions(): void
    {
        $actions = [
            ['post', '/films/arcane/like'], ['post', '/films/arcane/watch'], ['post', '/films/arcane/rate'],
            ['post', '/films/arcane/reviews'], ['post', '/reviews/1/like'], ['delete', '/reviews/1'],
            ['put', '/settings'], ['delete', '/account'], ['post', '/logout'],
        ];
        foreach ($actions as [$method, $url]) {
            $this->{$method}($url)->assertRedirect('/login');
            $this->{$method.'Json'}($url)->assertUnauthorized();      // the instant buttons get "401" instead
        }
        $this->assertDatabaseCount('likes', 0);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_after_signing_in_you_land_on_the_page_you_tried_to_open(): void
    {
        User::factory()->create(['name' => 'member', 'password' => 'password123']);

        $this->get('/profile/watchlist')->assertRedirect('/login');
        $this->postJson('/login', ['name' => 'member', 'password' => 'password123'])
            ->assertOk()->assertJson(['redirect' => url('/profile/watchlist')]);
    }

    public function test_refreshing_keeps_you_signed_in(): void
    {
        $user = User::factory()->create(['name' => 'member', 'password' => 'password123']);
        $this->post('/login', ['name' => 'member', 'password' => 'password123']);

        foreach (range(1, 5) as $refresh) {
            foreach (['/', '/films', '/lists', '/profile'] as $url) {
                $this->get($url)->assertOk();
            }
        }
        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_keeps_you_signed_in_after_the_browser_session(): void
    {
        User::factory()->create(['name' => 'member', 'password' => 'password123']);

        $response = $this->post('/login', ['name' => 'member', 'password' => 'password123', 'remember' => '1']);

        $this->assertNotNull(collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_')), 'remember-me cookie should be set');
    }

    public function test_after_signing_out_members_only_pages_are_locked_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        $this->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
        foreach (self::PAGES as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_members_only_pages_tell_the_browser_not_to_keep_a_copy(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::PAGES as $url) {
            $cache = $this->get($url)->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cache, "$url should not be cached");
        }
    }

    public function test_signed_in_users_do_not_see_the_guest_sign_in_or_sign_up(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect(route('home'));
        $this->post('/login', ['name' => 'x', 'password' => 'y'])->assertRedirect(route('home'));
        $this->post('/register', ['email' => 'a@b.co', 'name' => 'xyz', 'password' => 'password123'])->assertRedirect(route('home'));
        $this->get('/')->assertDontSee('id="signin"', false);
    }
}
