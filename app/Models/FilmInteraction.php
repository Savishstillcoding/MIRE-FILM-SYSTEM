<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Base class for "a user did something with a film": Like, WatchlistItem, Rating and Review extend it.
 * Each row = one user + one film (film_slug), plus a saved title/poster so lists don't need TMDB.
 *
 * Tables:  database/migrations/2026_10_12_… (likes, watchlist_items, ratings), 2026_10_13_… (reviews)
 * Used by: PageController (profile lists), FilmActionController / ReviewController (saving)
 */
abstract class FilmInteraction extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);                                                       // ← who did it
    }

    /** Newest first; id breaks ties between rows created in the same second. */
    public function scopeNewest(Builder $query): void
    {
        $query->latest()->latest('id');                                                             // ← newest first
    }

    /** The array shape the <x-poster> component expects. */
    public function toFilm(): array
    {
        return ['slug' => $this->film_slug, 'title' => $this->title, 'poster' => $this->poster];    // ← what <x-poster :film> needs
    }
}
