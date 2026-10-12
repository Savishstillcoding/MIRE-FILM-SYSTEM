<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/** The user's 1–5 star rating of a film. Table: ratings. Saved by FilmActionController::rate(). */
#[Fillable(['film_slug', 'title', 'poster', 'rating'])]
class Rating extends FilmInteraction
{
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }
}
