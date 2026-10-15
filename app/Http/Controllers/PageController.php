<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Support\FilmCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Builds the main pages: gathers the data each page needs and hands it to its Blade view.
 *
 * Routes:     routes/web.php ( /, /films, /films/{slug}, /trailers, /lists, /profile, /profile/{type} )
 * Film data:  app/Support/FilmCatalog.php (TMDB API, cached)
 * User data:  app/Models/User.php → likes(), watchlist(), ratings(), reviews()  (database)
 * Views:      resources/views/{welcome, home, films, film-show, trailers, lists, profile, profile-collection}.blade.php
 */
class PageController extends Controller
{
    /** Guests see the landing page (welcome.blade.php), members see their home (home.blade.php). */
    public function home()
    {
        if (! Auth::check()) {                                                                      // ← not signed in?
            return view('welcome', ['films' => FilmCatalog::trending(6)]);                          // ← landing page: 6 trending posters (FilmCatalog → TMDB)
        }

        return view('home', $this->homeRows());                                                     // ← member home: random rows from homeRows() below
    }

    /**
     * Random picks on every visit (the TMDB lists themselves are cached, only the picking is random).
     * A film never appears in two rows.
     */
    private function homeRows(int $perRow = 5, int $perShelf = 6): array
    {
        $pools = FilmCatalog::homePools();                                                          // ← 3 TMDB lists: trending / acclaimed / classics (cached 12h)

        $trending = collect($pools['trending'])->shuffle();                                         // ← shuffle = different picks every refresh
        $featured = $trending->filter(fn ($f) => ! empty($f['backdrop']))->take(2)->values();       // ← 2 "Featured" banners, each needs a backdrop image
        $recent = $trending->whereNotIn('slug', $featured->pluck('slug'))->take($perRow);           // ← top row (5), without the featured films

        $used = $recent->pluck('slug')->merge($featured->pluck('slug'));                            // ← remember what's shown so nothing repeats
        $favorites = collect($pools['acclaimed'])->whereNotIn('slug', $used)->shuffle()->take($perShelf);  // ← "Favorite of all time" row (6)

        $used = $used->merge($favorites->pluck('slug'));
        $rewatch = collect($pools['classics'])->whereNotIn('slug', $used)->shuffle()->take($perShelf);  // ← "Worth rewatching" row (6)

        return [
            'trailer' => FilmCatalog::firstWithTrailer(                                             // ← NEW: "Featured trailer" banner
                $trending->whereNotIn('slug', $used)->concat($trending->whereIn('slug', $used))->all()),  // ← prefer a film not shown below

            'featured' => $featured->all(),                                                         // ← the 2 featured films (plain array for the view)
            'recent' => $recent->values()->all(),
            'favorites' => $favorites->values()->all(),
            'rewatch' => $rewatch->values()->all(),
        ];
    }

    /** Films page (films.blade.php): search results (?q=), trending row and "Popular reviews this week". */
    public function films(Request $request)
    {
        $q = trim((string) $request->query('q', ''));                                               // ← search text from the header search box (?q=)

        return view('films', [
            'q' => $q,
            'results' => $q === '' ? null : FilmCatalog::search($q),                                // ← null = no search, so the results section is hidden
            'trending' => FilmCatalog::trending(12),                                                // ← "Trending this week" row
            ...$this->weeklyReviews($request),                                                      // ← adds 'reviews' + 'reviewsFromTmdb' for the reviews section
        ]);
    }

    /** Most-liked user reviews from the last 7 days; recent TMDB reviews when there are none yet. */
    private function weeklyReviews(Request $request, int $limit = 4): array
    {
        $reviews = Review::where('created_at', '>=', now()->subWeek())                              // ← only reviews from the last 7 days
            ->forDisplay($request->user())->popular()->take($limit)->get()                          // ← most liked first (Review.php scopes)
            ->map->toCard($request->user())->all();                                                 // ← shape for partials/review-card.blade.php

        return $reviews
            ? ['reviews' => $reviews, 'reviewsFromTmdb' => false]                                   // ← members have posted: show theirs
            : ['reviews' => FilmCatalog::trendingReviews($limit), 'reviewsFromTmdb' => true];       // ← nobody yet: TMDB reviews as backup
    }

    /**
     * One film's page (film-show.blade.php). $slug looks like "movie-603-the-matrix".
     * Also loads this user's rating / watchlist / like / review for the buttons, and the film's reviews.
     */
    public function show(Request $request, string $slug)
    {
        $user = $request->user();

        $film = FilmCatalog::findOrPlaceholder($slug);                                              // ← TMDB details (cast, genres, recommendations, TMDB reviews)
        $reviews = Review::where('film_slug', $slug)->forDisplay($user)->popular()->get()->map->toCard($user)->all();  // ← members' reviews of this film

        return view('film-show', [
            'film' => $film,
            'rating' => (int) $user->ratings()->where('film_slug', $slug)->value('rating'),         // ← lights up your stars (0 = not rated)
            'inWatchlist' => $user->watchlist()->where('film_slug', $slug)->exists(),               // ← gold eye button when true
            'liked' => $user->likes()->where('film_slug', $slug)->exists(),                         // ← red heart button when true
            'myReview' => $user->reviews()->where('film_slug', $slug)->value('body'),               // ← pre-fills the review form for editing
            // Members' reviews first; TMDB's only when nobody here has reviewed it yet.
            'reviews' => $reviews ?: ($film['reviews'] ?? []),
            'reviewsFromTmdb' => ! $reviews && ! empty($film['reviews']),
        ]);
    }

    /** NEW: Trailers page (trailers.blade.php): Coming soon / Trending / Popular rows of YouTube trailers from TMDB. */
    public function trailers()
    {
        return view('trailers', ['shelves' => FilmCatalog::trailerShelves()]);
    }

    /** Lists page (lists.blade.php): every TMDB genre with its popular films. */
    public function lists()
    {
        return view('lists', ['shelves' => FilmCatalog::genreShelves()]);                           // ← one box per genre
    }

    /** Profile page (profile.blade.php): stats, recent likes, watchlist and the user's reviews. */
    public function profile(Request $request)
    {
        $user = $request->user();

        return view('profile', [
            'likes' => $user->likes()->newest()->take(5)->get()->map->toFilm()->all(),              // ← "Recent likes" (newest 5)
            'watchlist' => $user->watchlist()->newest()->take(5)->get()->map->toFilm()->all(),      // ← "Watchlist" (newest 5)
            'likesCount' => $user->likes()->count(),                                                // ← numbers in the stats bar + "See all (n)"
            'watchlistCount' => $user->watchlist()->count(),
            'ratedCount' => $user->ratings()->count(),
            'myReviews' => $user->reviews()->forDisplay($user)->latest()->latest('id')->take(3)->get()->map->toCard($user)->all(),  // ← "Your reviews" (latest 3)
            'reviewsCount' => $user->reviews()->count(),
        ]);
    }

    /** "See all" pages for the profile's likes and watchlist. */
    public function collection(Request $request, string $type)
    {
        $relation = $type === 'likes' ? $request->user()->likes() : $request->user()->watchlist();  // ← /profile/likes or /profile/watchlist

        return view('profile-collection', [
            'type' => $type,
            'items' => $relation->newest()->get(),
        ]);
    }
}
