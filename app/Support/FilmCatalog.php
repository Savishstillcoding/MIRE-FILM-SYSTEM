<?php

namespace App\Support;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The ONLY place that talks to TMDB (themoviedb.org): films, posters, genres, search, TMDB reviews and trailers.
 * NEW: also mixes in published admin movies (movies table, slugs "local-{id}"): film page, search, Films row, Lists.
 * Results are cached so pages stay fast; several requests are fetched at once (getMany / Http::pool).
 * Without a token, or while TMDB is unreachable, the small built-in catalog at the bottom is used instead.
 *
 * Every film is a plain array (see normalize()); slugs look like "movie-603-the-matrix" or "tv-94605-arcane".
 *
 * Token:     .env → TMDB_TOKEN, read via config/services.php ('tmdb.token')
 * Used by:   app/Http/Controllers/PageController.php (every page), FilmActionController & ReviewController (title/poster snapshot)
 *            resources/views/components/poster.blade.php (byTitles() fallback), films.blade.php ("Just reviewed" sample row)
 * Tested in: tests/Feature/FilmCatalogTest.php (TMDB faked, no real calls)
 */
class FilmCatalog
{
    private const API = 'https://api.themoviedb.org/3';
    private const IMG = 'https://image.tmdb.org/t/p/';
    private const DOWN_KEY = 'tmdb:down';

    // ---------- Lists ----------

    /** Movies and series trending this week. */
    public static function trending(int $limit = 12): array
    {
        $data = self::get('/trending/all/week', ttl: now()->addHours(6));                           // ← TMDB "trending this week" (movies + TV)

        return $data ? self::results($data, $limit) : array_slice(self::fallback(), 0, $limit);     // ← no TMDB? use the built-in films
    }

    /**
     * The three pools the member home page picks from, fetched in one parallel round:
     * trending this week, acclaimed (high score AND widely voted) and classics (10+ years old, still popular).
     */
    public static function homePools(int $pages = 3): array
    {
        $acclaimed = ['sort_by' => 'vote_average.desc', 'vote_count.gte' => 5000, 'include_adult' => 'false'];  // ← high score AND 5,000+ votes = real classics
        $classics = [
            'primary_release_date.lte' => now()->subYears(10)->format('Y-01-01'),                   // ← 10+ years old
            'sort_by' => 'popularity.desc', 'vote_count.gte' => 2000, 'include_adult' => 'false',
        ];

        $requests = ['trending' => ['/trending/all/week', []]];
        foreach (range(1, $pages) as $p) {
            $requests["acclaimed.{$p}"] = ['/discover/movie', $acclaimed + ['page' => $p]];
            $requests["classics.{$p}"] = ['/discover/movie', $classics + ['page' => $p]];
        }
        $data = self::getMany($requests, now()->addHours(12));                                      // ← all 7 requests at once, cached 12h

        $pool = fn (string $name) => collect($data)
            ->filter(fn ($d, $key) => str_starts_with($key, "{$name}."))
            ->flatMap(fn ($d) => $d['results'] ?? [])
            ->map(fn ($r) => self::normalize($r, 'movie'))
            ->unique('slug')->values()->all();

        return [
            'trending' => $data['trending'] ? self::results($data['trending'], 20) : self::fallback(),
            'acclaimed' => $pool('acclaimed'),
            'classics' => $pool('classics'),
        ];
    }

    public static function search(string $q): array
    {
        $local = Movie::published()->where('title', 'like', '%'.$q.'%')->latest()->take(12)->get()  // ← NEW: admin movies first
            ->map->toCatalogFilm()->all();
        $data = self::get('/search/multi', ['query' => $q, 'include_adult' => 'false'], now()->addHour());  // ← searches movies AND TV shows

        if ($data === null) {
            return array_merge($local, collect(self::fallback())->filter(fn ($f) => stripos($f['title'], $q) !== false)->values()->all());
        }

        return array_merge($local, self::results($data, 30));
    }

    /** NEW: newest published admin movies, for the Films page "New on Miré Film" row. */
    public static function localLatest(int $limit = 12): array
    {
        return Movie::published()->latest()->latest('id')->take($limit)->get()->map->toCatalogFilm()->all();
    }

    /**
     * Look films up by title, keeping the given order. Used by the curated sample rows
     * (home, lists, profile) so they get real posters. Uncached titles are fetched in parallel.
     */
    public static function byTitles(array $titles): array
    {
        $key = fn (string $t) => 'tmdb:title:'.Str::slug($t);
        $found = [];
        foreach ($titles as $t) {
            $found[$t] = Cache::get($key($t));                                                      // ← already looked this title up before?
        }

        $missing = array_keys(array_filter($found, fn ($f) => $f === null));
        if ($missing && self::available()) {
            try {
                $responses = Http::pool(fn (Pool $pool) => array_map(
                    fn ($t) => self::configure($pool->as($t))->get('/search/multi', ['query' => $t, 'include_adult' => 'false']),  // ← fetch the missing titles in parallel
                    $missing,
                ));
            } catch (ConnectionException $e) {
                self::markDown($e);
                $responses = [];
            }

            foreach ($responses as $t => $res) {
                if (! $res instanceof Response || ! $res->successful()) {
                    continue;
                }
                $match = self::bestMatch($t, $res->json('results') ?? []);                          // ← pick the exact title match if there is one
                if ($match) {
                    Cache::put($key($t), $found[$t] = self::normalize($match), now()->addWeek());
                }
            }
        }

        return array_map(fn ($t) => $found[$t] ?? self::fallbackFind(Str::slug($t)) ?? self::placeholder(Str::slug($t), $t), $titles);
    }

    /**
     * Every TMDB movie genre with its most popular films, for the Lists page:
     * [['name' => 'Action', 'slug' => 'action', 'films' => [...]], ...]. Genres are fetched in parallel.
     * Without TMDB, returns one "Featured" shelf with the built-in films.
     */
    public static function genreShelves(int $perGenre = 18): array
    {
        $genres = self::get('/genre/movie/list', ttl: now()->addWeek())['genres'] ?? null;          // ← the 19 genre names (cached a week)
        if (! $genres) {
            return self::withLocalGenres([['name' => 'Featured', 'slug' => 'featured', 'films' => self::fallback()]]);
        }

        $data = self::getMany(collect($genres)->mapWithKeys(fn ($g) => [$g['id'] => ['/discover/movie', [  // ← popular films per genre, all genres at once
            'with_genres' => $g['id'], 'sort_by' => 'popularity.desc', 'vote_count.gte' => 200, 'include_adult' => 'false',
        ]]])->all());

        return self::withLocalGenres(collect($genres)
            ->map(fn ($g) => [
                'name' => $g['name'],
                'slug' => Str::slug($g['name']),
                'films' => collect($data[$g['id']]['results'] ?? [])->take($perGenre)
                    ->map(fn ($r) => self::normalize($r, 'movie'))->all(),
            ])
            ->filter(fn ($shelf) => $shelf['films'])                                                // ← skip genres with no films
            ->values()->all());
    }

    /** Our genre names that TMDB spells differently. */
    private const GENRE_ALIASES = ['sci-fi' => 'science fiction'];

    /**
     * NEW: put published admin movies on the Lists page. A movie goes to the FRONT of the TMDB box with the same
     * genre name (Action, Comedy, Sci-fi → Science Fiction…); genres TMDB doesn't have (BL, Filipino Teleserye)
     * get their own box at the end. Genres with no published movies add nothing.
     */
    private static function withLocalGenres(array $shelves): array
    {
        $genres = Genre::whereHas('movies', fn ($q) => $q->published())                            // ← only genres that have a published movie
            ->with(['movies' => fn ($q) => $q->published()->latest()->with('genres')])->orderBy('name')->get();

        foreach ($genres as $genre) {
            $name = Str::lower($genre->name);
            $name = self::GENRE_ALIASES[$name] ?? $name;
            $films = $genre->movies->map->toCatalogFilm()->all();
            $i = collect($shelves)->search(fn ($s) => Str::lower($s['name']) === $name);

            if ($i !== false) {
                $shelves[$i]['films'] = array_merge($films, $shelves[$i]['films']);                // ← front of the matching TMDB box
            } else {
                $shelves[] = ['name' => $genre->name, 'slug' => Str::slug($genre->name), 'films' => $films];  // ← new box (e.g. BL)
            }
        }

        return $shelves;
    }

    /**
     * Backup for "Popular reviews this week" while users haven't posted any:
     * the newest TMDB reviews of this week's trending titles (fetched in parallel, cached).
     */
    public static function trendingReviews(int $limit = 4): array
    {
        $films = collect(self::homePools()['trending'])->filter(fn ($f) => isset($f['tmdb_id']))->take(8)->keyBy('slug');  // ← reviews of 8 trending titles…
        $data = self::getMany($films->map(fn ($f) => ["/{$f['type']}/{$f['tmdb_id']}/reviews", []])->all(), now()->addHours(12));

        return $films
            ->flatMap(fn ($film, $slug) => collect($data[$slug]['results'] ?? [])->map(fn ($rv) => self::normalizeReview($rv, $film)))
            ->sortByDesc('created_at')                                                              // ← …newest first
            ->take($limit)
            ->values()->all();
    }

    // ---------- Trailers (YouTube videos stored on TMDB) ----------

    /**
     * NEW: the Trailers page (trailers.blade.php): 3 rows of films that have a YouTube trailer.
     * [['name' => 'Coming soon', 'slug' => 'coming-soon', 'films' => [film + 'trailer'], ...], ...]
     * Lists are fetched in parallel, then every film's videos in parallel (all cached). Without TMDB: [].
     */
    public static function trailerShelves(int $perShelf = 10): array
    {
        $lists = self::getMany([
            'coming-soon' => ['/discover/movie', [                                                  // ← not released yet, most anticipated first
                'primary_release_date.gte' => now()->addDay()->toDateString(),
                'sort_by' => 'popularity.desc', 'include_adult' => 'false',
            ]],
            'trending' => ['/trending/movie/week', []],                                             // ← popular this week
            'popular' => ['/movie/popular', ['page' => 1]],                                         // ← popular overall
        ]);
        $names = ['coming-soon' => 'Coming soon', 'trending' => 'Trending', 'popular' => 'Popular'];
        $today = now()->toDateString();

        $used = [];
        $shelves = [];
        foreach ($names as $slug => $name) {
            $films = collect($lists[$slug]['results'] ?? [])
                ->map(fn ($r) => self::normalize($r, 'movie'))
                ->when($slug === 'coming-soon', fn ($c) => $c
                    ->filter(fn ($f) => ($f['release_date'] ?? '') > $today)                        // ← only future release dates
                    ->sortBy('release_date'))                                                       // ← soonest first
                ->whereNotIn('slug', $used)                                                         // ← a film appears in one row only
                ->take($perShelf)->values()->all();
            $used = array_merge($used, array_column($films, 'slug'));
            $shelves[] = ['name' => $name, 'slug' => $slug, 'films' => $films];
        }

        $videos = self::videosFor(array_merge(...array_column($shelves, 'films')));

        return collect($shelves)
            ->map(fn ($shelf) => ['films' => collect($shelf['films'])
                ->map(fn ($f) => $f + ['trailer' => self::pickTrailer($videos[$f['slug']] ?? null)])
                ->filter(fn ($f) => $f['trailer'])                                                  // ← skip films without a YouTube trailer
                ->values()->all()] + $shelf)
            ->filter(fn ($shelf) => $shelf['films'])
            ->values()->all();
    }

    /** NEW: the first of $films (with a backdrop) that has a trailer, for the Home "Featured trailer" banner. Null if none. */
    public static function firstWithTrailer(array $films, int $tries = 4): ?array
    {
        $candidates = collect($films)->filter(fn ($f) => isset($f['tmdb_id']) && ! empty($f['backdrop']))->take($tries)->values()->all();
        $videos = self::videosFor($candidates);                                                     // ← up to 4 videos requests at once

        foreach ($candidates as $film) {
            if ($trailer = self::pickTrailer($videos[$film['slug']] ?? null)) {
                return $film + ['trailer' => $trailer];
            }
        }

        return null;
    }

    /** Videos for several films in parallel: [slug => TMDB videos payload|null]. Cached a day. */
    private static function videosFor(array $films): array
    {
        return self::getMany(collect($films)
            ->filter(fn ($f) => isset($f['tmdb_id']))
            ->mapWithKeys(fn ($f) => [$f['slug'] => ["/{$f['type']}/{$f['tmdb_id']}/videos", []]])
            ->all(), now()->addDay());
    }

    /**
     * Best YouTube video: official Trailer → any Trailer → Teaser. Returns ['key', 'name', 'type'] or null.
     * The key is the YouTube video id (youtube.com/watch?v=KEY).
     */
    private static function pickTrailer(?array $videos): ?array
    {
        $video = collect($videos['results'] ?? [])
            ->filter(fn ($v) => ($v['site'] ?? '') === 'YouTube' && ! empty($v['key']))             // ← we can only embed YouTube
            ->sortBy(fn ($v) => match (true) {
                ($v['type'] ?? '') === 'Trailer' && ! empty($v['official']) => 0,
                ($v['type'] ?? '') === 'Trailer' => 1,
                ($v['type'] ?? '') === 'Teaser' => 2,
                default => 9,
            })
            ->first(fn ($v) => in_array($v['type'] ?? '', ['Trailer', 'Teaser'], true));           // ← no clips / featurettes

        return $video ? ['key' => $video['key'], 'name' => $video['name'] ?? 'Trailer', 'type' => $video['type']] : null;
    }

    // ---------- Single film ----------

    public static function find(string $slug): ?array
    {
        if (preg_match('/^local-(\d+)$/', $slug, $m)) {                                             // ← NEW: admin movie, e.g. local-5 (movies table id 5)
            return self::findLocal((int) $m[1]);
        }

        if (! preg_match('/^(movie|tv)-(\d+)/', $slug, $m)) {                                       // ← e.g. movie-603-the-matrix → type + TMDB id
            return self::fallbackFind($slug);
        }

        [, $type, $id] = $m;
        $data = self::get("/{$type}/{$id}", ['append_to_response' => 'credits,recommendations,reviews,videos'], now()->addDay());  // ← 1 request: details + cast + recommendations + reviews + videos (NEW: trailer)

        return $data ? self::normalize($data, $type, detailed: true) : null;
    }

    /** NEW: a published admin movie with its details; drafts / deleted movies → null. "Similar" = other movies sharing a genre. */
    private static function findLocal(int $id): ?array
    {
        $movie = Movie::published()->with('genres')->find($id);
        if (! $movie) {
            return null;
        }

        $similar = Movie::published()->whereKeyNot($id)
            ->whereHas('genres', fn ($q) => $q->whereIn('genres.id', $movie->genres->modelKeys()))
            ->latest()->take(8)->get()->map->toCatalogFilm()->all();

        return ['similar' => $similar] + $movie->toCatalogFilm(detailed: true);
    }

    /**
     * Any TMDB-style slug still gets a page, even if TMDB doesn't know it.
     * NEW: an admin movie that's a draft or deleted → 404 (no page, no likes/ratings on it).
     */
    public static function findOrPlaceholder(string $slug): array
    {
        $film = self::find($slug);
        abort_if(! $film && str_starts_with($slug, 'local-'), 404);

        return $film ?? self::placeholder($slug);
    }

    // ---------- TMDB plumbing (requests, caching, outage handling) ----------

    public static function enabled(): bool
    {
        return filled(config('services.tmdb.token'));                                               // ← .env TMDB_TOKEN via config/services.php
    }

    /** Enabled and not in a recent outage (so a dead connection doesn't slow every page). */
    private static function available(): bool
    {
        return self::enabled() && ! Cache::has(self::DOWN_KEY);                                     // ← skip TMDB for 60s after a connection failure
    }

    private static function configure(PendingRequest $request): PendingRequest
    {
        return $request->withToken(config('services.tmdb.token'))
            ->baseUrl(self::API)
            ->acceptJson()
            ->connectTimeout(4)                                                                     // ← give up fast if the internet is down
            ->timeout(8);
    }

    private static function get(string $path, array $query = [], $ttl = null): ?array
    {
        $key = 'tmdb:'.$path.'?'.http_build_query($query);
        if (($hit = Cache::get($key)) !== null) {                                                   // ← cached? no TMDB call needed
            return $hit;
        }
        if (! self::available()) {
            return null;
        }

        try {
            $res = self::configure(Http::createPendingRequest())->get($path, $query);
        } catch (ConnectionException $e) {
            self::markDown($e);

            return null;
        }

        if (! $res->successful()) {
            Log::warning('TMDB request failed', ['path' => $path, 'status' => $res->status()]);

            return null;
        }

        Cache::put($key, $data = $res->json(), $ttl ?? now()->addHours(6));                         // ← save for next time

        return $data;
    }

    /**
     * Like get(), for several requests at once: cached ones are read from the cache,
     * the rest are fetched in parallel. Takes [key => [path, query]], returns [key => data|null].
     */
    private static function getMany(array $requests, $ttl = null): array
    {
        $cacheKey = fn (array $r) => 'tmdb:'.$r[0].'?'.http_build_query($r[1]);
        $data = array_map(fn ($r) => Cache::get($cacheKey($r)), $requests);

        $missing = array_keys(array_filter($data, fn ($d) => $d === null));
        if (! $missing || ! self::available()) {
            return $data;
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($key) => self::configure($pool->as((string) $key))->get(...$requests[$key]),
                $missing,
            ));
        } catch (ConnectionException $e) {
            self::markDown($e);

            return $data;
        }

        foreach ($missing as $key) {
            $res = $responses[(string) $key] ?? null;
            if ($res instanceof Response && $res->successful()) {
                Cache::put($cacheKey($requests[$key]), $data[$key] = $res->json(), $ttl ?? now()->addHours(6));
            }
        }

        return $data;
    }

    private static function markDown(\Throwable $e): void
    {
        Log::warning('TMDB unreachable, using fallback catalog for 60s', ['error' => $e->getMessage()]);
        Cache::put(self::DOWN_KEY, true, 60);                                                       // ← remember the outage for 60 seconds
    }

    /** Movie/TV entries from a paged TMDB response (skips people). */
    private static function results(array $data, int $limit): array
    {
        return collect($data['results'] ?? [])
            ->filter(fn ($r) => in_array($r['media_type'] ?? 'movie', ['movie', 'tv'], true))
            ->take($limit)
            ->map(fn ($r) => self::normalize($r))
            ->values()->all();
    }

    /** Prefer an exact title match, otherwise TMDB's top movie/TV hit. */
    private static function bestMatch(string $title, array $results): ?array
    {
        $candidates = collect($results)->filter(fn ($r) => in_array($r['media_type'] ?? '', ['movie', 'tv'], true));
        $norm = fn ($s) => Str::slug($s, ''); // "10 Dance" matches "10DANCE"

        return $candidates->first(fn ($r) => $norm($r['title'] ?? $r['name'] ?? '') === $norm($title))
            ?? $candidates->first();
    }

    /** Turn a TMDB movie/TV payload into the array shape the views use. */
    private static function normalize(array $r, ?string $type = null, bool $detailed = false): array
    {
        $type ??= $r['media_type'] ?? (isset($r['first_air_date']) ? 'tv' : 'movie');
        $title = $r['title'] ?? $r['name'] ?? 'Untitled';
        $year = fn (?string $date) => $date ? (int) substr($date, 0, 4) : null;
        $start = $year($r['release_date'] ?? $r['first_air_date'] ?? null);
        $end = $type === 'tv' ? $year($r['last_air_date'] ?? null) : null;

        $film = [
            'slug' => "{$type}-{$r['id']}-".(Str::slug($title) ?: 'untitled'),
            'tmdb_id' => $r['id'],
            'type' => $type,
            'title' => $title,
            'subtitle' => $type === 'tv' ? 'Series' : (string) ($start ?? ''),
            'poster' => ! empty($r['poster_path']) ? self::IMG.'w500'.$r['poster_path'] : null,     // ← full image URL on TMDB's image server
            'backdrop' => ! empty($r['backdrop_path']) ? self::IMG.'w1280'.$r['backdrop_path'] : null,
            'description' => $r['overview'] ?? '',
            'release_date' => $r['release_date'] ?? $r['first_air_date'] ?? null,                   // ← NEW: "Releases Nov 21" on Coming soon trailers
            'years' => array_values(array_unique(array_filter([$start, $end]))),
            'score' => ($r['vote_count'] ?? 0) > 0 ? round($r['vote_average'], 1) : null,
            'seasons' => null,
            'status' => null,
            'similar' => [],
            'reviews' => [],
        ];

        if ($detailed) {
            $film += [
                'tagline' => $r['tagline'] ?? null,
                'genres' => array_column($r['genres'] ?? [], 'name'),
                'runtime' => $r['runtime'] ?? ($r['episode_run_time'][0] ?? null),
                'cast' => array_column(array_slice($r['credits']['cast'] ?? [], 0, 8), 'name'),
                'director' => collect($r['credits']['crew'] ?? [])->firstWhere('job', 'Director')['name']
                    ?? ($r['created_by'][0]['name'] ?? null),
                'trailer' => self::pickTrailer($r['videos'] ?? null),                               // ← NEW: "Watch trailer" button on the film page
            ];
            $film['seasons'] = $r['number_of_seasons'] ?? null;
            $film['status'] = $type === 'tv' ? ($r['status'] ?? null) : null;
            $film['reviews'] = array_map(
                fn ($rv) => self::normalizeReview($rv, $film),
                array_slice($r['reviews']['results'] ?? [], 0, 5),
            );
            $film['similar'] = collect($r['recommendations']['results'] ?? [])
                ->take(8)
                ->map(fn ($s) => self::normalize($s + ['media_type' => $s['media_type'] ?? $type]))
                ->all();
        }

        return $film;
    }

    /** A TMDB review in the same shape as Review::toCard() (no likes; links back to TMDB). */
    private static function normalizeReview(array $rv, array $film): array
    {
        $score = $rv['author_details']['rating'] ?? null; // TMDB uses 0–10

        return [
            'source' => 'tmdb',
            'id' => null,
            'film' => array_intersect_key($film, array_flip(['slug', 'title', 'poster', 'years'])),
            'author' => $rv['author_details']['username'] ?? $rv['author'] ?? 'TMDB user',
            'rating' => $score ? max(1, (int) round($score / 2)) : null,
            'body' => Str::limit(trim(strip_tags($rv['content'] ?? '')), 320),
            'likes' => null,
            'liked' => false,
            'mine' => false,
            'created_at' => isset($rv['created_at']) ? \Illuminate\Support\Carbon::parse($rv['created_at']) : null,
            'url' => $rv['url'] ?? null,
        ];
    }

    private static function placeholder(string $slug, ?string $title = null): array
    {
        return [
            'slug' => $slug,
            'title' => $title ?? Str::headline(preg_replace('/^(movie|tv)-\d+-/', '', $slug)),
            'description' => 'No description yet.',
            'years' => [], 'seasons' => null, 'status' => null, 'similar' => [], 'reviews' => [],
        ];
    }

    // ---------- Offline fallback (the original demo catalog) ----------

    private static function fallback(): array
    {
        $films = [
            ['title' => 'American Psycho', 'subtitle' => 'Christian Bale · Mary Harron', 'bg' => 'linear-gradient(180deg,#c8353a,#8e1a22)', 'description' => 'A Wall Street banker hides a violent double life behind polished surfaces.', 'years' => [2000]],
            ['title' => 'Arcane', 'subtitle' => 'A Netflix series', 'bg' => 'linear-gradient(160deg,#f2f2ee 20%,#d9a6b6 55%,#5a7ea8)', 'color' => '#14233f', 'description' => 'Two sisters are pulled to opposite sides of a war between two cities.', 'years' => [2021, 2024], 'seasons' => 2, 'status' => 'Complete'],
            ['title' => 'Pearl', 'subtitle' => 'A film by Ti West', 'bg' => 'linear-gradient(180deg,#171b1a,#6b0f12 70%,#d8d8d0)', 'description' => 'A young woman on a 1918 Texas farm dreams of stardom.', 'years' => [2022]],
            ['title' => 'Project Hail Mary', 'subtitle' => '', 'bg' => 'linear-gradient(180deg,#b98a3d,#e0641c 45%,#7a2208)', 'description' => 'A lone astronaut wakes up far from Earth with a mission to save it.'],
            ['title' => 'White Chicks', 'subtitle' => 'Summer', 'bg' => 'linear-gradient(180deg,#f4f4f4,#bcd0e8)', 'color' => '#18365e', 'description' => 'Two FBI agents go undercover in a way nobody expected.', 'years' => [2004]],
        ];

        return array_map(fn ($f) => $f + ['slug' => Str::slug($f['title']), 'years' => [], 'seasons' => null, 'status' => null, 'similar' => [], 'reviews' => []], $films);
    }

    private static function fallbackFind(string $slug): ?array
    {
        return collect(self::fallback())->firstWhere('slug', $slug);
    }
}
