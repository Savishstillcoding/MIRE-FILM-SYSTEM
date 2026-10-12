<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/** A film the user liked (❤️ on the film page). Table: likes. Saved by FilmActionController::like(). */
#[Fillable(['film_slug', 'title', 'poster'])]
class Like extends FilmInteraction
{
}
