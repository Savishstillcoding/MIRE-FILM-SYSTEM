<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FilmActionController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
 * Every page address in the app and the controller method that handles it.
 * Run `php artisan route:list` to see them all. Views use the ->name() values, e.g. route('films.show', $slug).
 */

// Landing page for guests, member home for signed-in users.
Route::get('/', [PageController::class, 'home'])->name('home');                                     // ← welcome.blade.php or home.blade.php

// Signed-out visitors who open a members-only URL are sent here by the 'auth' middleware (which also remembers
// the URL, so AuthController::login sends them back to it after signing in).
Route::get('/login', fn () => redirect()->route('home')->with('signin_required', true));  // ← opens the sign-in box with "Please sign in…" (layouts/app + signin-modal)

Route::middleware('guest')->group(function () {                                                     // ← only for people NOT signed in
    Route::post('/register', [AuthController::class, 'register'])->name('register');                // ← partials/join-modal.blade.php
    Route::post('/login', [AuthController::class, 'login'])->name('login');                         // ← partials/signin-modal.blade.php
});

Route::middleware(['auth', 'no-history'])->group(function () {                         // ← everything below needs a signed-in user; browser keeps no copies
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/films', [PageController::class, 'films'])->name('films');                          // ← films.blade.php (+ search ?q=)
    Route::get('/films/{slug}', [PageController::class, 'show'])->name('films.show');               // ← film-show.blade.php
    // Film page buttons (saved to the database, sent instantly by resources/js/app.js)
    Route::post('/films/{slug}/rate', [FilmActionController::class, 'rate'])->name('films.rate');   // ← ★ stars
    Route::post('/films/{slug}/watch', [FilmActionController::class, 'watch'])->name('films.watch');  // ← 👁 Watchlist button
    Route::post('/films/{slug}/like', [FilmActionController::class, 'like'])->name('films.like');   // ← ❤️ Like button

    // Reviews (form on the film page, cards in resources/views/partials/review-card.blade.php)
    Route::post('/films/{slug}/reviews', [ReviewController::class, 'store'])->name('reviews.store');  // ← Post / Update review
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');  // ← Delete my review
    Route::post('/reviews/{review}/like', [ReviewController::class, 'like'])->name('reviews.like');  // ← ❤️ on a review

    Route::get('/lists', [PageController::class, 'lists'])->name('lists');                          // ← lists.blade.php (genre boxes)
    Route::get('/profile', [PageController::class, 'profile'])->name('profile');                    // ← profile.blade.php
    // "See all" pages for likes / watchlist
    Route::get('/profile/{type}', [PageController::class, 'collection'])->whereIn('type', ['likes', 'watchlist'])->name('profile.collection');  // ← profile-collection.blade.php

    // Stubs from the design. Wire to real logic when ready.
    Route::view('/settings', 'settings')->name('settings');                                         // ← settings.blade.php (form not saved yet)
    Route::put('/settings', fn () => back())->name('settings.update');
    Route::delete('/account', fn () => redirect()->route('home'))->name('account.deactivate');
});
