<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Rating;
use App\Models\Review;
use App\Models\User;

/**
 * Admin home (route /admin): stat cards + recent activity across the site.
 * View: resources/views/admin/dashboard.blade.php. Only admins get here (routes/web.php, can:admin).
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = now()->startOfDay();

        return view('admin.dashboard', [
            'stats' => [                                                                            // ← the 4 cards: total + how many today
                ['label' => 'Users', 'total' => User::count(), 'today' => User::where('created_at', '>=', $today)->count(), 'url' => route('admin.users.index')],
                ['label' => 'Reviews', 'total' => Review::count(), 'today' => Review::where('created_at', '>=', $today)->count(), 'url' => null],
                ['label' => 'Ratings', 'total' => Rating::count(), 'today' => Rating::where('created_at', '>=', $today)->count(), 'url' => null],
                ['label' => 'Movies added', 'total' => Movie::count(), 'today' => Movie::where('created_at', '>=', $today)->count(), 'url' => route('admin.movies.index')],
            ],
            'drafts' => Movie::where('status', 'draft')->count(),
            'activity' => $this->recentActivity(),
        ]);
    }

    /** Newest sign-ups, reviews, ratings and added movies, mixed together, newest first. */
    private function recentActivity(int $limit = 10): array
    {
        $users = User::latest()->take($limit)->get()
            ->map(fn ($u) => ['icon' => 'user', 'text' => "{$u->name} joined", 'at' => $u->created_at]);
        $reviews = Review::with('user:id,name')->latest()->take($limit)->get()
            ->map(fn ($r) => ['icon' => 'review', 'text' => "{$r->user->name} reviewed “{$r->title}”", 'at' => $r->created_at]);
        $ratings = Rating::with('user:id,name')->latest()->take($limit)->get()
            ->map(fn ($r) => ['icon' => 'star', 'text' => "{$r->user->name} rated “{$r->title}” {$r->rating}★", 'at' => $r->created_at]);
        $movies = Movie::with('creator:id,name')->latest()->take($limit)->get()
            ->map(fn ($m) => ['icon' => 'movie', 'text' => ($m->creator->name ?? 'An admin')." added “{$m->title}”", 'at' => $m->created_at]);

        return $users->concat($reviews)->concat($ratings)->concat($movies)
            ->sortByDesc('at')->take($limit)->values()->all();
    }
}
