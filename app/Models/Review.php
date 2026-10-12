<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's written review of a film (one per user per film). Table: reviews.
 * Saved by ReviewController; displayed with resources/views/partials/review-card.blade.php via toCard().
 */
#[Fillable(['film_slug', 'title', 'poster', 'year', 'rating', 'body'])]
class Review extends FilmInteraction
{
    protected function casts(): array
    {
        return ['rating' => 'integer', 'year' => 'integer'];
    }

    public function likes(): HasMany                                                                // ← review_likes rows for this review
    {
        return $this->hasMany(ReviewLike::class);
    }

    /** Most liked first, then newest. Expects withCount('likes'). */
    public function scopePopular(Builder $query): void
    {
        $query->orderByDesc('likes_count')->latest()->latest('id');                                 // ← most liked, then newest
    }

    /** Adds author, like count and whether $viewer liked it. */
    public function scopeForDisplay(Builder $query, ?User $viewer): void
    {
        $query->with('user:id,name')                                                                // ← author's username
            ->withCount('likes')                                                                    // ← adds likes_count
            ->withExists(['likes as liked' => fn ($q) => $q->where('user_id', $viewer?->id)]);      // ← did the viewer already like it?
    }

    /** The array shape partials/review-card expects (shared with TMDB reviews). */
    public function toCard(?User $viewer): array
    {
        return [
            'source' => 'user',
            'id' => $this->id,
            'film' => $this->toFilm() + ['years' => array_filter([$this->year])],
            'author' => $this->user->name,
            'rating' => $this->rating,
            'body' => $this->body,
            'likes' => (int) $this->likes_count,
            'liked' => (bool) $this->liked,
            'mine' => $viewer && $viewer->id === $this->user_id,                                    // ← shows "Delete my review"
            'created_at' => $this->created_at,
            'url' => null,
        ];
    }
}
