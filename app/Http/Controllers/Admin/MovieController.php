<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Like;
use App\Models\Movie;
use App\Models\Rating;
use App\Models\Review;
use App\Models\WatchlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin movies: list (search + All/Published/Drafts), add, edit, delete. Posters go to storage/app/public/posters.
 *
 * Routes:  routes/web.php ( /admin/movies … via Route::resource )
 * Views:   resources/views/admin/movies/{index, create, edit, form}.blade.php
 * Tables:  movies, genres, genre_movie (database/migrations/2026_10_15_000001_…)
 */
class MovieController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), Movie::STATUSES, true) ? $request->query('status') : null;  // ← tab: null = All
        $q = trim((string) $request->query('q', ''));

        $movies = Movie::with('genres')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where('title', 'like', "%{$q}%"))                // ← search box
            ->latest('updated_at')->latest('id')
            ->paginate(15)->withQueryString();                                                      // ← keeps ?status / ?q on page links

        return view('admin.movies.index', [
            'movies' => $movies,
            'status' => $status,
            'q' => $q,
            'counts' => ['all' => Movie::count()] + Movie::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),  // ← numbers on the tabs
        ]);
    }

    public function create()
    {
        return view('admin.movies.create', ['movie' => new Movie, 'genres' => Genre::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $movie = new Movie($data);
        $movie->created_by = $request->user()->id;
        if ($request->hasFile('poster')) {
            $movie->poster = $request->file('poster')->store('posters', 'public');                 // ← storage/app/public/posters/xxxx.jpg
        }
        $movie->save();
        $movie->genres()->sync($data['genres']);

        return redirect()->route('admin.movies.index')->with('status', $movie->status === 'published' ? 'Movie published' : 'Draft saved');
    }

    public function edit(Movie $movie)
    {
        return view('admin.movies.edit', ['movie' => $movie->load('genres'), 'genres' => Genre::orderBy('name')->get()]);
    }

    public function update(Request $request, Movie $movie)
    {
        $data = $this->validated($request);

        if ($request->hasFile('poster') || $request->boolean('remove_poster')) {                    // ← new poster uploaded, or "Remove" clicked
            $this->deletePoster($movie);
            $movie->poster = $request->hasFile('poster') ? $request->file('poster')->store('posters', 'public') : null;
        }
        $movie->fill($data)->save();
        $movie->genres()->sync($data['genres']);

        return redirect()->route('admin.movies.index')->with('status', $movie->status === 'published' ? 'Movie published' : 'Draft saved');
    }

    public function destroy(Movie $movie)
    {
        $this->deletePoster($movie);
        foreach ([Like::class, WatchlistItem::class, Rating::class, Review::class] as $model) {      // ← NEW: members' likes / watchlist / ratings / reviews of it
            $model::where('film_slug', $movie->slug())->delete();                                   //   exact "local-5" (never touches "local-50")
        }
        $movie->delete();                                                                           // ← genre_movie rows go too (cascade)

        return redirect()->route('admin.movies.index')->with('status', "“{$movie->title}” deleted");
    }

    /** Shared rules for add + edit. "status" comes from whichever button was pressed (Save as draft / Publish). */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:200',
            'year' => 'required|integer|min:1888|max:'.(now()->year + 2),                          // ← 1888 = first film ever; allow upcoming releases
            'runtime' => 'nullable|integer|min:1|max:999',
            'genres' => 'required|array|min:1',
            'genres.*' => 'integer|exists:genres,id',
            'age_rating' => ['nullable', Rule::in(Movie::AGE_RATINGS)],
            'director' => 'nullable|string|max:120',
            'description' => 'required|string|max:5000',
            'poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',                          // ← 2 MB
            'status' => ['required', Rule::in(Movie::STATUSES)],
        ], [
            'genres.required' => 'Pick at least one genre.',
            'poster.max' => 'The poster must be 2 MB or smaller.',
        ]);
    }

    private function deletePoster(Movie $movie): void
    {
        if ($movie->poster) {
            Storage::disk('public')->delete($movie->poster);
        }
    }
}
