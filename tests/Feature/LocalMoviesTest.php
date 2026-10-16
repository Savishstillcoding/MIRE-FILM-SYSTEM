<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** NEW: published admin movies show on the public site (film page, search, Films, Lists). No TMDB token unless faked. */
class LocalMoviesTest extends TestCase
{
    use RefreshDatabase;

    private function movie(array $attrs = [], array $genres = ['Romance']): Movie
    {
        $movie = Movie::forceCreate($attrs + [
            'title' => 'Hello, Love, Goodbye', 'year' => 2019, 'runtime' => 117, 'age_rating' => 'PG',
            'director' => 'Cathy Garcia-Molina', 'description' => 'Two OFWs in Hong Kong.',
            'poster' => 'posters/hlg.jpg', 'status' => 'published',
        ]);
        $movie->genres()->sync(Genre::whereIn('name', $genres)->pluck('id'));

        return $movie;
    }

    public function test_a_published_movie_has_its_own_film_page(): void
    {
        Http::preventStrayRequests();                                         // ← no TMDB call for admin movies
        $movie = $this->movie();

        $this->actingAs(User::factory()->create())->get("/films/{$movie->slug()}")->assertOk()
            ->assertSee('Hello, Love, Goodbye')->assertSee('Cathy Garcia-Molina')->assertSee('Romance')
            ->assertSee('1h 57m')->assertSee('PG')->assertSee('storage/posters/hlg.jpg', false)
            ->assertDontSee('Watch trailer');
    }

    public function test_renaming_a_movie_keeps_members_likes_and_reviews(): void
    {
        $admin = tap(User::factory()->create(), fn (User $u) => $u->forceFill(['is_admin' => true])->save());
        $fan = User::factory()->create(['name' => 'fan']);
        $movie = $this->movie();
        $this->actingAs($fan)->postJson("/films/{$movie->slug()}/like");
        $this->actingAs($fan)->post("/films/{$movie->slug()}/reviews", ['body' => 'Iyak ako dito']);

        $this->actingAs($admin)->put("/admin/movies/{$movie->id}", [                               // ← fix a typo in the title
            'title' => 'Hello Love Goodbye (2019)', 'year' => 2019, 'description' => 'Two OFWs.',
            'genres' => Genre::where('name', 'Romance')->pluck('id')->all(), 'status' => 'published',
        ])->assertRedirect();

        $this->actingAs($fan)->get('/films/'.$movie->fresh()->slug())->assertOk()
            ->assertSee('Hello Love Goodbye (2019)')->assertSee('Iyak ako dito')->assertSee('Liked');  // ← review + ❤️ still there
        $this->actingAs($fan)->postJson("/films/{$movie->slug()}/like")->assertJson(['liked' => false]);  // ← un-likes (no duplicate row)
        $this->assertSame(0, $fan->likes()->count());
    }

    public function test_drafts_and_deleted_movies_are_not_public(): void
    {
        $draft = $this->movie(['status' => 'draft']);
        $user = User::factory()->create();

        $this->actingAs($user)->get("/films/{$draft->slug()}")->assertNotFound();
        $this->actingAs($user)->postJson("/films/{$draft->slug()}/like")->assertNotFound();
        $this->actingAs($user)->get('/films/local-999')->assertNotFound();
        $this->assertDatabaseCount('likes', 0);
    }

    public function test_search_finds_admin_movies_first(): void
    {
        Http::preventStrayRequests();
        $this->movie();
        $this->movie(['title' => 'Secret Draft Love', 'status' => 'draft']);

        $this->actingAs(User::factory()->create())->get('/films?q=love')->assertOk()
            ->assertSee('Hello, Love, Goodbye')->assertDontSee('Secret Draft Love');
    }

    public function test_films_page_has_new_on_mire_film_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/films')->assertDontSee('New on Miré Film');  // ← hidden while there are none

        $this->movie();
        $this->movie(['title' => 'Unfinished', 'status' => 'draft']);

        $this->actingAs($user)->get('/films')->assertSeeInOrder(['New on Miré Film', 'Hello, Love, Goodbye', 'Trending this week'])
            ->assertDontSee('Unfinished');
    }

    public function test_lists_page_gets_bl_box_and_adds_to_matching_tmdb_genre(): void
    {
        config(['services.tmdb.token' => 'test-token']);
        Http::fake([
            'api.themoviedb.org/3/genre/movie/list*' => Http::response(['genres' => [['id' => 878, 'name' => 'Science Fiction']]]),
            'api.themoviedb.org/3/discover/movie*' => Http::response(['results' => [['id' => 603, 'title' => 'The Matrix', 'release_date' => '1999-03-30']]]),
        ]);
        $this->movie(['title' => 'Gameboys'], ['BL']);
        $this->movie(['title' => 'Space Pinoy'], ['Sci-fi']);

        $this->actingAs(User::factory()->create())->get('/lists')->assertOk()
            ->assertSeeInOrder(['Science Fiction', 'Space Pinoy', 'The Matrix', 'BL', 'Gameboys'])  // ← front of the TMDB box; BL gets its own
            ->assertDontSee('id="genre-horror"', false);                     // ← genres without published movies add nothing
    }

    public function test_liking_saves_the_admin_movie_title_and_poster(): void
    {
        $movie = $this->movie();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/films/{$movie->slug()}/like")->assertJson(['liked' => true]);

        $like = $user->likes()->sole();
        $this->assertSame('Hello, Love, Goodbye', $like->title);
        $this->assertSame('/storage/posters/hlg.jpg', $like->poster);                              // ← no http://host saved: works on any address later
    }

    public function test_deleting_a_movie_removes_its_likes_but_not_other_movies(): void
    {
        $admin = tap(User::factory()->create(), fn (User $u) => $u->forceFill(['is_admin' => true])->save());
        $user = User::factory()->create();
        $one = $this->movie();
        foreach (range(2, 10) as $i) {                                       // ← make sure a movie with id 10 exists
            $ten = $this->movie(['title' => "Movie {$i}"]);
        }
        $this->actingAs($user)->postJson("/films/{$one->slug()}/like");
        $this->actingAs($user)->postJson("/films/{$ten->slug()}/like");      // local-10 must survive deleting local-1

        $this->actingAs($admin)->delete("/admin/movies/{$one->id}")->assertRedirect();

        $this->assertSame([$ten->slug()], $user->likes()->pluck('film_slug')->all());
    }

    public function test_poster_links_have_no_host_so_they_work_on_any_address(): void
    {
        $movie = $this->movie();

        $this->actingAs(User::factory()->create())->get("http://192.168.1.5:8000/films/{$movie->slug()}")
            ->assertSee('src="/storage/posters/hlg.jpg"', false)            // ← the browser adds 192.168.1.5:8000 / mire.test itself
            ->assertDontSee('mire.test/storage', false);
    }
}
