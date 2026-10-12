<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/** A film on the user's watchlist (👁 on the film page). Table: watchlist_items. Saved by FilmActionController::watch(). */
#[Fillable(['film_slug', 'title', 'poster'])]
class WatchlistItem extends FilmInteraction
{
}
