<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Likes, watchlist and ratings are saved per user in the database (runs on the built-in catalog, no TMDB). */
class FilmInteractionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_toggles_instantly_and_persists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/films/arcane/like')
            ->assertOk()->assertJson(['liked' => true, 'message' => 'Added to your likes']);
        $this->assertDatabaseHas('likes', ['user_id' => $user->id, 'film_slug' => 'arcane', 'title' => 'Arcane']);

        $this->actingAs($user)->postJson('/films/arcane/like')->assertJson(['liked' => false]);
        $this->assertDatabaseCount('likes', 0);
    }

    public function test_watchlist_survives_signing_out_and_back_in(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->actingAs($user)->postJson('/films/pearl/watch')->assertJson(['watchlisted' => true]);
        $this->post('/logout');
        $this->post('/login', ['name' => $user->name, 'password' => 'password123']);

        $this->get('/films/pearl')->assertSee('On watchlist');
        $this->get('/profile')->assertSee('Pearl')->assertSee('See all (1)');
    }

    public function test_rating_sets_changes_and_clears(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/films/arcane/rate', ['rating' => 4])->assertJson(['rating' => 4]);
        $this->actingAs($user)->postJson('/films/arcane/rate', ['rating' => 2])->assertJson(['rating' => 2]);
        $this->assertDatabaseHas('ratings', ['user_id' => $user->id, 'film_slug' => 'arcane', 'rating' => 2]);
        $this->assertDatabaseCount('ratings', 1);

        $this->actingAs($user)->postJson('/films/arcane/rate', ['rating' => 2])->assertJson(['rating' => 0]);
        $this->assertDatabaseCount('ratings', 0);

        $this->actingAs($user)->postJson('/films/arcane/rate', ['rating' => 9])->assertUnprocessable();
    }

    public function test_profile_lists_newest_first_and_is_private_to_each_user(): void
    {
        [$me, $other] = User::factory()->count(2)->create();

        foreach (['pearl', 'arcane', 'white-chicks'] as $slug) {
            $this->actingAs($me)->postJson("/films/{$slug}/like");
        }
        $this->actingAs($other)->postJson('/films/american-psycho/like');

        $this->actingAs($me)->get('/profile')
            ->assertSeeInOrder(['White Chicks', 'Arcane', 'Pearl'])
            ->assertDontSee('American Psycho')
            ->assertSee('See all (3)');

        $this->actingAs($me)->get('/profile/likes')->assertOk()->assertSee('3 films');
        $this->actingAs($me)->get('/profile/watchlist')->assertOk()->assertSee('Your watchlist is empty');
    }

    public function test_form_posts_without_javascript_redirect_back(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/films/arcane')->post('/films/arcane/like')
            ->assertRedirect('/films/arcane')->assertSessionHas('status', 'Added to your likes');
    }

    public function test_guests_cannot_like(): void
    {
        $this->postJson('/films/arcane/like')->assertUnauthorized();
    }
}
