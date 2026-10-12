<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Support\FilmCatalog;
use Illuminate\Http\Request;

/**
 * Member reviews: write/update, delete, and like/unlike.
 *
 * Routes:   routes/web.php ( POST /films/{slug}/reviews, DELETE /reviews/{review}, POST /reviews/{review}/like )
 * Form:     resources/views/film-show.blade.php (#write-review)
 * Cards:    resources/views/partials/review-card.blade.php (like + delete buttons)
 * Instant:  resources/js/app.js → "form[data-instant]" (review hearts update the count without reloading)
 * Tables:   app/Models/Review.php, ReviewLike.php (created in database/migrations/2026_10_13_…)
 * Shown on: films page ("Popular reviews this week"), film page, profile ("Your reviews") via PageController
 */
class ReviewController extends Controller
{
    /** One review per user per film: posting again updates it. */
    public function store(Request $request, string $slug)
    {
        $body = $request->validate(['body' => 'required|string|min:2|max:2000'])['body'];           // ← the textarea on the film page
        $film = FilmCatalog::findOrPlaceholder($slug);
        $user = $request->user();

        $user->reviews()->updateOrCreate(['film_slug' => $slug], [                                  // ← one review per user per film: create or update
            'title' => $film['title'],
            'poster' => $film['poster'] ?? null,
            'year' => $film['years'][0] ?? null,
            'rating' => $user->ratings()->where('film_slug', $slug)->value('rating'),               // ← copies your current stars onto the review
            'body' => trim($body),
        ]);

        return redirect(route('films.show', $slug).'#reviews')->with('status', 'Review posted');    // ← back to the film page, scrolled to Reviews
    }

    public function destroy(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id, 403);                               // ← only the author can delete
        $review->delete();

        return back()->with('status', 'Review deleted');
    }

    /** Toggle; answers JSON with the new count for the instant heart button. */
    public function like(Request $request, Review $review)
    {
        $liked = ! $review->likes()->where('user_id', $request->user()->id)->delete();              // ← removed a like = now unliked
        if ($liked) {
            $review->likes()->create(['user_id' => $request->user()->id]);                          // ← review_likes table
        }

        $state = ['liked' => $liked, 'likes' => $review->likes()->count()];                         // ← app.js updates the heart + count

        return $request->expectsJson()
            ? response()->json($state + ['message' => $liked ? 'You liked this review' : 'Like removed'])
            : back();
    }
}
