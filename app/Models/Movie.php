<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * A movie added by an admin (not from TMDB). Table: movies (+ genre_movie for its genres).
 * Saved by app/Http/Controllers/Admin/MovieController.php; listed on resources/views/admin/movies/index.blade.php.
 */
#[Fillable(['title', 'year', 'runtime', 'age_rating', 'director', 'description', 'poster', 'status'])]
class Movie extends Model
{
    public const STATUSES = ['draft', 'published'];

    public const AGE_RATINGS = ['G', 'PG', 'R-13', 'R-16', 'R-18'];                                 // ← MTRCB (Philippines)

    protected function casts(): array
    {
        return ['year' => 'integer', 'runtime' => 'integer'];
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function creator(): BelongsTo                                                            // ← the admin who added it
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /** Public URL of the uploaded poster, or null (needs `php artisan storage:link` once). */
    public function posterUrl(): ?string
    {
        return $this->poster ? Storage::disk('public')->url($this->poster) : null;
    }

    /** The array shape the <x-poster> component expects. */
    public function toFilm(): array
    {
        return ['slug' => "local-{$this->id}", 'title' => $this->title, 'poster' => $this->posterUrl()];
    }
}
