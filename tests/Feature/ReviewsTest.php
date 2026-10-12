<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Member reviews and review likes (built-in catalog; TMDB faked where needed). */
class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_a_review_shows_it_on_the_film_page_with_my_rating(): void
    {
        $user = User::factory()->create(['name' => 'critic']);
        $this->actingAs($user)->postJson('/films/arcane/rate', ['rating' => 5]);

        $this->actingAs($user)->post('/films/arcane/reviews', ['body' => 'Best animation ever.'])
            ->assertRedirect(route('films.show', 'arcane').'#reviews');

        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'film_slug' => 'arcane', 'title' => 'Arcane', 'rating' => 5]);
        $this->actingAs($user)->get('/films/arcane')
            ->assertSee('Best animation ever.')->assertSee('critic')->assertSee('Update review')->assertSee('Delete my review');
    }

    public function test_posting_again_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/films/pearl/reviews', ['body' => 'First take']);
        $this->actingAs($user)->post('/films/pearl/reviews', ['body' => 'Second take']);

        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame('Second take', Review::first()->body);
    }

    public function test_review_needs_text(): void
    {
        $this->actingAs(User::factory()->create())->from('/films/pearl')
            ->post('/films/pearl/reviews', ['body' => ''])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_liking_a_review_is_instant_and_counts(): void
    {
        [$author, $fan, $other] = User::factory()->count(3)->create();
        $review = $author->reviews()->create(['film_slug' => 'pearl', 'title' => 'Pearl', 'body' => 'Wow']);

        $this->actingAs($fan)->postJson("/reviews/{$review->id}/like")->assertJson(['liked' => true, 'likes' => 1]);
        $this->actingAs($other)->postJson("/reviews/{$review->id}/like")->assertJson(['liked' => true, 'likes' => 2]);
        $this->actingAs($fan)->postJson("/reviews/{$review->id}/like")->assertJson(['liked' => false, 'likes' => 1]);
    }

    public function test_popular_this_week_is_most_liked_recent_reviews(): void
    {
        Http::preventStrayRequests();
        [$a, $b, $fan1, $fan2] = User::factory()->count(4)->create();
        $quiet = $a->reviews()->create(['film_slug' => 'pearl', 'title' => 'Pearl', 'body' => 'Quiet review']);
        $loved = $b->reviews()->create(['film_slug' => 'arcane', 'title' => 'Arcane', 'body' => 'Loved review']);
        $old = $b->reviews()->create(['film_slug' => 'white-chicks', 'title' => 'White Chicks', 'body' => 'Old review']);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        foreach ([$fan1, $fan2] as $fan) {
            $loved->likes()->create(['user_id' => $fan->id]);
        }

        $this->actingAs($fan1)->get('/films')
            ->assertSeeInOrder(['Popular reviews this week', 'Loved review', 'Quiet review'])
            ->assertDontSee('Old review')
            ->assertDontSee('From TMDB');
    }

    public function test_falls_back_to_tmdb_reviews_when_members_have_none(): void
    {
        config(['services.tmdb.token' => 'test-token']);
        Http::fake([
            'api.themoviedb.org/3/trending/*' => Http::response(['results' => [
                ['id' => 603, 'media_type' => 'movie', 'title' => 'The Matrix', 'release_date' => '1999-03-30', 'poster_path' => '/m.jpg'],
            ]]),
            'api.themoviedb.org/3/movie/603/reviews*' => Http::response(['results' => [[
                'author' => 'Neo', 'author_details' => ['username' => 'neo', 'rating' => 9.0],
                'content' => 'There is no spoon.', 'created_at' => '2026-10-01T10:00:00.000Z', 'url' => 'https://www.themoviedb.org/review/1',
            ]]]),
            '*' => Http::response(['results' => []]),
        ]);

        $this->actingAs(User::factory()->create())->get('/films')
            ->assertOk()->assertSee('There is no spoon.')->assertSee('From TMDB')->assertSee('neo')
            ->assertSee('Read the full review on TMDB');
    }

    public function test_only_the_author_can_delete(): void
    {
        [$author, $other] = User::factory()->count(2)->create();
        $review = $author->reviews()->create(['film_slug' => 'pearl', 'title' => 'Pearl', 'body' => 'Mine']);

        $this->actingAs($other)->delete("/reviews/{$review->id}")->assertForbidden();
        $this->actingAs($author)->delete("/reviews/{$review->id}")->assertRedirect();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_profile_lists_my_reviews(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/films/pearl/reviews', ['body' => 'Creepy and great']);

        $this->actingAs($user)->get('/profile')->assertSee('Your reviews')->assertSee('Creepy and great')->assertSee('1 review');
    }
}
