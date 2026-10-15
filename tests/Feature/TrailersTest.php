<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** NEW: trailers from TMDB: the Trailers page, "Watch trailer" on film pages, the Home banner. TMDB is faked. */
class TrailersTest extends TestCase
{
    use RefreshDatabase;

    private function film(int $id, string $title, ?string $date = '2020-01-01'): array
    {
        return ['id' => $id, 'title' => $title, 'release_date' => $date, 'poster_path' => "/p{$id}.jpg", 'backdrop_path' => "/b{$id}.jpg", 'vote_average' => 7, 'vote_count' => 100];
    }

    private function trailer(string $key, string $type = 'Trailer', bool $official = true, string $site = 'YouTube'): array
    {
        return ['key' => $key, 'name' => "{$type} {$key}", 'type' => $type, 'official' => $official, 'site' => $site];
    }

    /** Fake TMDB: 3 lists + videos per film (film 4 has no trailer, film 1 is already released). */
    private function fakeTmdb(): void
    {
        config(['services.tmdb.token' => 'test-token']);
        $future = now()->addMonth()->toDateString();
        $videos = [
            1 => [$this->trailer('old1')], 2 => [$this->trailer('soon2')], 3 => [$this->trailer('soon3', 'Teaser')],
            4 => [['key' => 'clip4', 'name' => 'Clip', 'type' => 'Clip', 'site' => 'YouTube']],
            5 => [$this->trailer('trend5')], 6 => [$this->trailer('pop6')],
            603 => [$this->trailer('vimeo', site: 'Vimeo'), $this->trailer('tease', 'Teaser'), $this->trailer('unofficial', official: false), $this->trailer('matrix')],
        ];

        Http::fake(function (Request $request) use ($future, $videos) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                // "Coming soon" query (discover with future dates); 'Already Out' checks the date filter still works.
                str_contains($request->url(), 'primary_release_date.gte') => Http::response(['results' => [$this->film(1, 'Already Out'), $this->film(3, 'Later Film', now()->addMonths(2)->toDateString()), $this->film(2, 'Soon Film', $future), $this->film(4, 'No Trailer', $future)]]),
                str_ends_with($path, '/trending/movie/week') => Http::response(['results' => [$this->film(2, 'Soon Film'), $this->film(5, 'Trend Film')]]),
                str_ends_with($path, '/trending/all/week') => Http::response(['results' => [$this->film(5, 'Trend Film') + ['media_type' => 'movie']]]),  // ← home page pool
                str_ends_with($path, '/movie/popular') => Http::response(['results' => [$this->film(6, 'Pop Film')]]),
                (bool) preg_match('#/movie/(\d+)/videos$#', $path, $m) => Http::response(['results' => $videos[$m[1]] ?? []]),
                str_ends_with($path, '/movie/603') => Http::response($this->film(603, 'The Matrix') + ['videos' => ['results' => $videos[603]]]),
                default => Http::response(['results' => []]),
            };
        });
    }

    public function test_trailers_page_has_coming_soon_trending_and_popular(): void
    {
        $this->fakeTmdb();

        $this->actingAs(User::factory()->create())->get('/trailers')->assertOk()
            ->assertSeeInOrder(['Coming soon', 'Soon Film', 'Later Film', 'Trending', 'Trend Film', 'Popular', 'Pop Film'])
            ->assertSee('data-trailer="soon2"', false)
            ->assertSee('Releases')                                       // coming soon shows the date
            ->assertSee('Teaser')                                         // film 3 only has a teaser
            ->assertDontSee('In theaters')
            ->assertDontSee('Already Out')                                // released already → not "coming soon"
            ->assertDontSee('No Trailer');                                // no YouTube trailer → skipped
    }

    public function test_a_film_appears_in_one_row_only(): void
    {
        $this->fakeTmdb();

        $html = $this->actingAs(User::factory()->create())->get('/trailers')->getContent();
        $this->assertSame(1, substr_count($html, 'data-trailer="soon2"'));  // in upcoming AND trending, shown once
    }

    public function test_film_page_has_watch_trailer_with_the_best_video(): void
    {
        $this->fakeTmdb();

        $this->actingAs(User::factory()->create())->get('/films/movie-603-the-matrix')->assertOk()
            ->assertSee('Watch trailer')
            ->assertSee('data-trailer="matrix"', false)                   // official YouTube trailer wins
            ->assertSee('id="trailer-modal"', false);
    }

    public function test_home_shows_a_featured_trailer(): void
    {
        $this->fakeTmdb();

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('Featured trailer')->assertSee('Play trailer')->assertSee('data-trailer="trend5"', false);
    }

    public function test_without_tmdb_there_are_no_trailers_but_nothing_breaks(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/trailers')->assertOk()->assertSee('No trailers right now');
        $this->actingAs($user)->get('/films/arcane')->assertOk()->assertDontSee('Watch trailer');
        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('Featured trailer');
    }

    public function test_navbar_has_trailers_and_guests_only_get_the_player_after_signing_in(): void
    {
        $this->get('/')->assertSee('TRAILERS')->assertDontSee('id="trailer-modal"', false);
        $this->actingAs(User::factory()->create())->get('/profile')->assertSee(route('trailers'), false);
    }
}
