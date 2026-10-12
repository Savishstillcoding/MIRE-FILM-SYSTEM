<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FilmCatalogTest extends TestCase
{
    use RefreshDatabase;

    private array $matrix = [
        'id' => 603, 'media_type' => 'movie', 'title' => 'The Matrix', 'release_date' => '1999-03-30',
        'overview' => 'A hacker learns the truth about reality.', 'poster_path' => '/matrix.jpg',
        'backdrop_path' => '/matrix-bg.jpg', 'vote_average' => 8.2, 'vote_count' => 25000,
    ];

    private function fakeTmdb(): void
    {
        config(['services.tmdb.token' => 'test-token']);

        Http::fake([
            'api.themoviedb.org/3/trending/*' => Http::response(['results' => [$this->matrix]]),
            'api.themoviedb.org/3/search/*' => Http::response(['results' => [$this->matrix]]),
            'api.themoviedb.org/3/genre/movie/list*' => Http::response(['genres' => [['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction'], ['id' => 99, 'name' => 'Documentary']]]),
            'api.themoviedb.org/3/discover/movie*with_genres=28*' => Http::response(['results' => array_map(fn ($i) => ['id' => 1000 + $i, 'title' => "Action Film {$i}"] + $this->matrix, range(1, 20))]),
            'api.themoviedb.org/3/discover/movie*with_genres=878*' => Http::response(['results' => [$this->matrix]]),
            'api.themoviedb.org/3/discover/movie*' => Http::response(['results' => []]),
            'api.themoviedb.org/3/movie/603*' => Http::response($this->matrix + [
                'runtime' => 136,
                'genres' => [['name' => 'Action'], ['name' => 'Science Fiction']],
                'credits' => ['cast' => [['name' => 'Keanu Reeves']], 'crew' => [['job' => 'Director', 'name' => 'Lana Wachowski']]],
                'recommendations' => ['results' => []],
            ]),
        ]);
    }

    public function test_pages_use_tmdb_when_a_token_is_set(): void
    {
        $this->fakeTmdb();
        $user = User::factory()->create();

        $this->get('/')->assertOk()->assertSee('image.tmdb.org/t/p/w342/matrix.jpg', false);

        $this->actingAs($user)->get('/films?q=matrix')
            ->assertOk()->assertSee('The Matrix')->assertSee(route('films.show', 'movie-603-the-matrix'), false);

        $this->actingAs($user)->get('/films/movie-603-the-matrix')
            ->assertOk()->assertSee('Keanu Reeves')->assertSee('Lana Wachowski')->assertSee('2h 16m')->assertSee('Science Fiction');

        foreach (['/', '/films', '/lists', '/profile'] as $page) {
            $this->actingAs($user)->get($page)->assertOk();
        }

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_home_rows_are_random_picks_with_no_repeats(): void
    {
        config(['services.tmdb.token' => 'test-token']);
        $films = fn (string $prefix, int $from) => ['results' => array_map(
            fn ($i) => ['id' => $from + $i, 'media_type' => 'movie', 'title' => "{$prefix} {$i}", 'backdrop_path' => '/bg.jpg'] + $this->matrix,
            range(1, 20),
        )];
        Http::fake([
            'api.themoviedb.org/3/trending/*' => Http::response($films('Trend', 100)),
            'api.themoviedb.org/3/discover/movie*vote_average.desc*' => Http::response($films('Top', 200)),
            'api.themoviedb.org/3/discover/movie*' => Http::response($films('Classic', 300)),
        ]);
        $user = User::factory()->create();

        $pages = collect(range(1, 4))->map(fn () => $this->actingAs($user)->get('/')->assertOk()->getContent());

        foreach ($pages as $html) {
            preg_match_all('#href="[^"]*/films/(movie-\d+)[^"]*"#', $html, $m);
            $this->assertCount(18, $m[1]);                          // 5 + featured + 6 + 6
            $this->assertCount(18, array_unique($m[1]));            // no film twice
            $this->assertSame(6, substr_count($html, 'title="Top '));
            $this->assertSame(6, substr_count($html, 'title="Classic '));
        }
        $this->assertGreaterThan(1, $pages->unique()->count());     // changes between visits
    }

    public function test_lists_page_shows_every_genre_in_a_box_with_see_more(): void
    {
        $this->fakeTmdb();

        $this->actingAs(User::factory()->create())->get('/lists')
            ->assertOk()
            ->assertSeeInOrder(['Action', '18 films', 'Action Film 1', 'Action Film 6', 'See more', 'Science Fiction', '1 film', 'The Matrix'])
            ->assertSee('id="genre-action-more"', false)      // 12 extra films hidden behind See more
            ->assertDontSee('Action Film 19')                 // capped at 18 per genre
            ->assertDontSee('id="genre-science-fiction-more"', false) // only 1 film: no See more
            ->assertDontSee('genre-documentary');             // empty genre is skipped
    }

    public function test_falls_back_to_the_built_in_catalog_without_a_token(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();

        $this->get('/')->assertOk()->assertSee('Arcane');
        $this->actingAs($user)->get('/films?q=arc')->assertOk()->assertSee('Arcane');
        $this->actingAs($user)->get('/films/arcane')->assertOk()->assertSee('Two sisters');
        $this->actingAs($user)->get('/lists')->assertOk();
    }

    public function test_falls_back_when_tmdb_is_unreachable(): void
    {
        config(['services.tmdb.token' => 'test-token']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));
        $user = User::factory()->create();

        $this->get('/')->assertOk()->assertSee('Arcane');
        $this->actingAs($user)->get('/films/movie-603-the-matrix')->assertOk()->assertSee('The Matrix');
        $this->actingAs($user)->get('/lists')->assertOk();
    }
}
