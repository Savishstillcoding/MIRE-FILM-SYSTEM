<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin area (/admin): who can get in, adding / editing / deleting movies, and managing admins. Routes in routes/web.php. */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/admin', '/admin/movies', '/admin/movies/create', '/admin/users'];

    private function admin(): User
    {
        return tap(User::factory()->create(['name' => 'boss']), fn (User $u) => $u->forceFill(['is_admin' => true])->save());
    }

    private function movieData(array $overrides = []): array
    {
        return $overrides + [                                                                       // ← "+" keeps the LEFT value, so overrides win
            'title' => 'Hello, Love, Goodbye', 'year' => 2019, 'runtime' => 117, 'age_rating' => 'PG',
            'director' => 'Cathy Garcia-Molina', 'description' => 'Two OFWs in Hong Kong.',
            'genres' => Genre::whereIn('name', ['Romance', 'Comedy'])->pluck('id')->all(),
            'status' => 'published',
        ];
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        foreach (self::PAGES as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_regular_members_are_forbidden(): void
    {
        $member = User::factory()->create();

        foreach (self::PAGES as $url) {
            $this->actingAs($member)->get($url)->assertForbidden();
        }
        $this->actingAs($member)->post('/admin/movies', $this->movieData())->assertForbidden();
        $this->assertDatabaseCount('movies', 0);
        $this->actingAs($member)->get('/profile')->assertDontSee('Admin dashboard');
    }

    public function test_admins_can_open_every_admin_page(): void
    {
        $admin = $this->admin();

        foreach (self::PAGES as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get('/profile')->assertSee('Admin dashboard');
    }

    public function test_sign_up_cannot_make_itself_admin(): void
    {
        $this->post('/register', ['email' => 'sneaky@x.co', 'name' => 'sneaky', 'password' => 'Str0ng!Pass', 'is_admin' => '1']);  // ← strong password (sign-up rules)

        $this->assertFalse(User::where('name', 'sneaky')->first()->is_admin);
    }

    public function test_dashboard_shows_stats_and_recent_activity(): void
    {
        $admin = $this->admin();
        $fan = User::factory()->create(['name' => 'juan']);
        $fan->ratings()->create(['film_slug' => 'pearl', 'title' => 'Pearl', 'rating' => 5]);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Welcome back, boss.')
            ->assertSee('juan joined')
            ->assertSee('juan rated “Pearl” 5★', false);
    }

    public function test_admin_can_add_a_movie_with_poster_and_genres(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/movies', $this->movieData([
            'poster' => UploadedFile::fake()->create('poster.jpg', 300, 'image/jpeg'),
        ]))->assertRedirect(route('admin.movies.index'));

        $movie = Movie::with('genres')->sole();
        $this->assertSame('published', $movie->status);
        $this->assertSame($admin->id, $movie->created_by);
        $this->assertEqualsCanonicalizing(['Comedy', 'Romance'], $movie->genres->pluck('name')->all());
        Storage::disk('public')->assertExists($movie->poster);

        $this->actingAs($admin)->get('/admin/movies')->assertSee('Hello, Love, Goodbye')->assertSee('Published');
    }

    public function test_save_as_draft(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/movies', $this->movieData(['status' => 'draft']));

        $this->assertSame('draft', Movie::sole()->status);
        $this->actingAs($admin)->get('/admin/movies?status=published')->assertSee('No movies match.');
        $this->actingAs($admin)->get('/admin/movies?status=draft')->assertSee('Hello, Love, Goodbye');
    }

    public function test_title_genres_and_description_are_required(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->from('/admin/movies/create')
            ->post('/admin/movies', $this->movieData(['title' => '', 'genres' => [], 'description' => '']))
            ->assertSessionHasErrors(['title', 'genres', 'description']);
        $this->assertDatabaseCount('movies', 0);
    }

    public function test_poster_must_be_a_small_image(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/movies', $this->movieData(['poster' => UploadedFile::fake()->create('virus.exe', 10)]))
            ->assertSessionHasErrors('poster');
        $this->actingAs($admin)->post('/admin/movies', $this->movieData(['poster' => UploadedFile::fake()->create('huge.jpg', 3000, 'image/jpeg')]))
            ->assertSessionHasErrors('poster');
    }

    public function test_admin_can_edit_and_remove_the_poster(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/movies', $this->movieData(['poster' => UploadedFile::fake()->create('p.jpg', 100, 'image/jpeg')]));
        $movie = Movie::sole();
        $oldPoster = $movie->poster;

        $this->actingAs($admin)->get("/admin/movies/{$movie->id}/edit")->assertOk()->assertSee('Hello, Love, Goodbye');
        $this->actingAs($admin)->put("/admin/movies/{$movie->id}", $this->movieData(['title' => 'Hello Love Again', 'remove_poster' => '1']))
            ->assertRedirect(route('admin.movies.index'));

        $movie->refresh();
        $this->assertSame('Hello Love Again', $movie->title);
        $this->assertNull($movie->poster);
        Storage::disk('public')->assertMissing($oldPoster);
    }

    public function test_admin_can_delete_a_movie_and_its_poster(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/movies', $this->movieData(['poster' => UploadedFile::fake()->create('p.jpg', 100, 'image/jpeg')]));
        $movie = Movie::sole();

        $this->actingAs($admin)->delete("/admin/movies/{$movie->id}")->assertRedirect(route('admin.movies.index'));

        $this->assertDatabaseCount('movies', 0);
        $this->assertDatabaseCount('genre_movie', 0);
        Storage::disk('public')->assertMissing($movie->poster);
    }

    public function test_users_page_lists_and_searches_users(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'maria', 'email' => 'maria@lnu.edu.ph']);
        User::factory()->create(['name' => 'pedro']);

        $this->actingAs($admin)->get('/admin/users?q=lnu')->assertSee('maria')->assertDontSee('pedro');
    }

    public function test_admin_can_make_and_remove_admins_but_not_themselves(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/users/{$member->id}/admin")->assertRedirect();
        $this->assertTrue($member->refresh()->is_admin);
        $this->actingAs($admin)->patch("/admin/users/{$member->id}/admin");
        $this->assertFalse($member->refresh()->is_admin);

        $this->actingAs($admin)->patch("/admin/users/{$admin->id}/admin")->assertForbidden();
        $this->assertTrue($admin->refresh()->is_admin);
    }

    public function test_artisan_command_makes_someone_admin(): void
    {
        $user = User::factory()->create(['email' => 'me@lnu.edu.ph']);

        $this->artisan('mire:admin', ['email' => 'me@lnu.edu.ph'])->assertSuccessful();
        $this->assertTrue($user->refresh()->is_admin);

        $this->artisan('mire:admin', ['email' => 'me@lnu.edu.ph', '--remove' => true])->assertSuccessful();
        $this->assertFalse($user->refresh()->is_admin);

        $this->artisan('mire:admin', ['email' => 'nobody@x.co'])->assertFailed();
    }

    public function test_genres_from_the_design_are_ready(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Action', 'Comedy', 'Romance', 'BL', 'Sci-fi', 'Filipino Teleserye', 'Horror'],
            Genre::pluck('name')->all(),
        );
    }
}
