<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movies added by admins (admin/movies pages), our own genres (incl. ones TMDB doesn't have, like BL)
 * and the genre_movie link table (a movie can have several genres).
 *
 * Models: app/Models/Movie.php, Genre.php. Run with `php artisan migrate`.
 */
return new class extends Migration
{
    /** Starting genres, from the admin dashboard design. Add more later in the genres table. */
    private const GENRES = ['Action', 'Comedy', 'Romance', 'BL', 'Sci-fi', 'Filipino Teleserye', 'Horror'];

    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('runtime')->nullable();     // minutes
            $table->string('age_rating', 10)->nullable();            // MTRCB: G, PG, R-13, R-16, R-18
            $table->string('director')->nullable();
            $table->text('description');
            $table->string('poster')->nullable();                    // path on the "public" disk, e.g. posters/abc.jpg
            $table->string('status', 10)->default('draft');          // draft | published
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('genre_movie', function (Blueprint $table) {
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();

            $table->primary(['genre_id', 'movie_id']);
        });

        DB::table('genres')->insert(collect(self::GENRES)
            ->map(fn ($name) => ['name' => $name, 'created_at' => now(), 'updated_at' => now()])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('genre_movie');
        Schema::dropIfExists('movies');
        Schema::dropIfExists('genres');
    }
};
