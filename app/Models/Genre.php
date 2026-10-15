<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Our own genres for admin-added movies (TMDB films keep TMDB's genres). Table: genres, filled by the 2026_10_15 migration. */
#[Fillable(['name'])]
class Genre extends Model
{
    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class);
    }
}
