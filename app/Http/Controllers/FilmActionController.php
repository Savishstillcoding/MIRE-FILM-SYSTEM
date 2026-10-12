<?php

namespace App\Http\Controllers;

use App\Support\FilmCatalog;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * The Like, Watchlist and star-rating buttons on a film page. Each click saves to the database.
 * Answers JSON for the instant (no-reload) buttons, and redirects back for plain form posts when JavaScript is off.
 *
 * Routes:   routes/web.php ( POST /films/{slug}/like | /watch | /rate )
 * Buttons:  resources/views/film-show.blade.php (forms marked data-instant)
 * Instant:  resources/js/app.js → "form[data-instant]" sends the click and updates the button
 * Tables:   app/Models/Like.php, WatchlistItem.php, Rating.php (created in database/migrations/2026_10_12_…)
 */
class FilmActionController extends Controller
{
    public function like(Request $request, string $slug)
    {
        $liked = $this->toggle($request->user()->likes(), $slug);                                   // ← likes table: add if missing, remove if there

        return $this->respond($request, ['liked' => $liked], $liked ? 'Added to your likes' : 'Removed from your likes');  // ← app.js reads 'liked' to colour the heart
    }

    public function watch(Request $request, string $slug)
    {
        $listed = $this->toggle($request->user()->watchlist(), $slug);                              // ← watchlist_items table

        return $this->respond($request, ['watchlisted' => $listed], $listed ? 'Added to your watchlist' : 'Removed from your watchlist');
    }

    /** Clicking the star you already gave clears the rating. */
    public function rate(Request $request, string $slug)
    {
        $n = (int) $request->validate(['rating' => 'required|integer|between:1,5'])['rating'];      // ← which star was clicked (1–5)
        if ((int) $request->user()->ratings()->where('film_slug', $slug)->value('rating') === $n) {  // ← same star as before?
            $request->user()->ratings()->where('film_slug', $slug)->delete();                       // ← …then clear the rating
            $n = 0;
        } else {
            $request->user()->ratings()->updateOrCreate(['film_slug' => $slug], $this->snapshot($slug) + ['rating' => $n]);  // ← otherwise save / change it (ratings table)
        }

        return $this->respond($request, ['rating' => $n], $n ? "Rated {$n} out of 5" : 'Rating cleared');
    }

    /** Remove the row if it exists, otherwise create it. Returns the new state. */
    private function toggle(HasMany $relation, string $slug): bool
    {
        // The extra where() doesn't affect create(); HasMany still sets user_id.
        if ($relation->where('film_slug', $slug)->delete()) {                                       // ← deleted something = it was on, now off
            return false;
        }

        $relation->create(['film_slug' => $slug] + $this->snapshot($slug));                         // ← wasn't there: add it with title + poster

        return true;
    }

    /** Title + poster saved with the row so profile lists don't need TMDB. */
    private function snapshot(string $slug): array
    {
        $film = FilmCatalog::findOrPlaceholder($slug);                                              // ← cached TMDB lookup

        return ['title' => $film['title'], 'poster' => $film['poster'] ?? null];
    }

    private function respond(Request $request, array $state, string $message)
    {
        return $request->expectsJson()                                                              // ← JSON = clicked via app.js (no reload)
            ? response()->json($state + ['message' => $message])                                    // ← message shows in the pop-up toast
            : back()->with('status', $message);                                                     // ← no JavaScript: reload the page instead
    }
}
