<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Likes, watchlist and ratings per user. Each row keeps a snapshot of the film's
 * title and poster so the profile can list them without calling TMDB again.
 * Unique (user_id, film_slug): a user can like/save/rate a film only once.
 *
 * Models: app/Models/Like.php, WatchlistItem.php, Rating.php. Run with `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['likes', 'watchlist_items', 'ratings'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('film_slug');
                $table->string('title');
                $table->string('poster')->nullable();
                if ($name === 'ratings') {
                    $table->unsignedTinyInteger('rating');
                }
                $table->timestamps();

                $table->unique(['user_id', 'film_slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('watchlist_items');
        Schema::dropIfExists('likes');
    }
};
