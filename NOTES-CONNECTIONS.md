# How MIRÉ Film connects (Oct 15, 2026)

How the pieces of the system talk to each other: browser ↔ server, server ↔ database, server ↔ outside APIs.
Every connection shows **where it happens (file + function)**, **the real code**, **what data is sent**, and **what comes back**.

> Secrets are never shown here. `.env` values like tokens and passwords are written as `***`.

---

## 0. The big picture

```
                         ┌────────────────────── outside services ───────────────────────┐
                         │  TMDB API  api.themoviedb.org     (films, genres, reviews,    │
                         │                                     trailers: server → TMDB)  │
                         │  TMDB images  image.tmdb.org      (posters: browser → TMDB)   │
                         │  YouTube  youtube-nocookie.com    (trailer player: browser)   │
                         │  Google Fonts                     (fonts: browser)            │
                         │  haveibeenpwned.com               (password check, live only) │
                         └───────────────────────────────────────────────────────────────┘
                                          ▲                         ▲
                                          │ HTTPS (server)          │ HTTPS (browser)
┌──────────┐  HTTP   ┌──────────────────────┐   ┌────────────┐   ┌──────────────┐   ┌─────────────┐
│ Browser  │ ──────► │ Web server           │──►│ routes/    │──►│ Controllers  │──►│ MySQL `mire`│
│ (pages,  │ ◄────── │ Laragon: Apache      │   │ web.php    │   │ + Models     │◄──│ users, likes│
│  app.js) │  HTML / │   or artisan serve   │   │ (+ middle- │   │ + FilmCatalog│   │ reviews,    │
└──────────┘  JSON   │ Docker: nginx →      │   │  ware)     │   └──────┬───────┘   │ sessions,   │
                     │   app:9000 (PHP-FPM) │   └────────────┘          │           │ cache, …    │
                     └──────────────────────┘                           ▼           └─────────────┘
                                                              Blade views (HTML) ──► back to browser
```

**One request, step by step** (e.g. clicking ❤️ Like):
1. `app.js` sends `POST /films/{slug}/like` in the background.
2. Apache (Laragon), PHP's built-in server (`php artisan serve`) or nginx (Docker) hands it to Laravel (`public/index.php`).
3. `routes/web.php` matches the URL and runs the middleware: signed in? (`auth`), no browser copy (`no-history`).
4. `FilmActionController@like` saves or deletes a row in the **`likes`** table.
5. It answers JSON `{"liked": true, "message": "Added to your likes"}`, and `app.js` colours the heart.

---

## 1. Configuration: where the connection settings live

### `.env` (names only)
```ini
APP_URL=http://mire.test

DB_CONNECTION=mysql          # → MySQL (Laragon)
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mire
DB_USERNAME=root
DB_PASSWORD=***

SESSION_DRIVER=database      # → logins are stored in MySQL (sessions table)
CACHE_STORE=database         # → TMDB answers are cached in MySQL (cache table)
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

TMDB_TOKEN=***               # ✅ USED: TMDB "API Read Access Token"
TMDB_API_KEY=***             # ⚠️ NOT USED by any code
YOUTUBE_API_KEY=***          # ⚠️ NOT USED yet (only for a future "search YouTube" feature)
```

### `.env` → config → code (TMDB token)
`config/services.php`
```php
'tmdb' => [
    'token' => env('TMDB_TOKEN'),
],
```
`app/Support/FilmCatalog.php` → `configure()`: **every** TMDB request goes through this:
```php
private const API = 'https://api.themoviedb.org/3';

private static function configure(PendingRequest $request): PendingRequest
{
    return $request->withToken(config('services.tmdb.token'))   // header: Authorization: Bearer ***
        ->baseUrl(self::API)
        ->acceptJson()                                          // header: Accept: application/json
        ->connectTimeout(4)                                     // give up after 4s if no connection
        ->timeout(8);                                           // give up after 8s waiting for an answer
}
```

### Docker overrides the database settings
`docker-compose.yml`: inside Docker, the app talks to the **`db` container**, not Laragon:
```yaml
app:
  environment:
    DB_CONNECTION: mysql
    DB_HOST: db                                  # ← the MySQL container's name
    DB_PORT: 3306
    DB_DATABASE: ${DOCKER_DB_DATABASE:-mire}
    DB_USERNAME: ${DOCKER_DB_USERNAME:-mire}
    DB_PASSWORD: ${DOCKER_DB_PASSWORD:-secret}
```
`docker/nginx/default.conf`: nginx passes PHP requests to the `app` container on port 9000:
```nginx
root /var/www/html/public;
location / { try_files $uri $uri/ /index.php?$query_string; }   # every URL → Laravel
location ~ \.php$ { fastcgi_pass app:9000; }                     # → PHP-FPM container
```

| | Laragon (Apache) | `php artisan serve` | Docker |
|---|---|---|---|
| Web server | Apache → `C:\laragon\www\mire\public` | PHP's built-in server → `public/index.php` | nginx (`web`) → `app:9000` |
| Database | `127.0.0.1:3306`, user `root` | same as Laragon (reads `.env`) | `db:3306` (from your PC: `127.0.0.1:3308`), user `mire` |
| URL (this laptop) | http://mire.test | http://127.0.0.1:8000 | http://127.0.0.1:8081 |
| Other devices (same Wi-Fi) | needs extra setup | `http://<your IP>:8000` with `--host=0.0.0.0` (today's IP: see NOTES-SETUP.md) | `http://<your IP>:8081` |
| Notes | many users at once | one request at a time; run `npm run build` first | slower on Windows (~6s/page) |

---

## 2. Routes: every URL the system answers

From `php artisan route:list --except-vendor` (29 routes). File: `routes/web.php`.

**Middleware legend:**
- `web`: sessions, cookies and CSRF check (all routes).
- `guest`: only when signed **out**.
- `auth`: only when signed **in** (else → `/login` → sign-in box).
- `no-history`: browser may not keep a copy (`app/Http/Middleware/PreventBackHistory.php`).
- `can:admin`: only users with `is_admin = true` (else 403).

### Public / sign-in
| Method | URL | Name | Handled by | Middleware |
|---|---|---|---|---|
| GET | `/` | home | `PageController@home` | web |
| GET | `/login` | – | redirect → `/` + opens sign-in box | web |
| POST | `/login` | login | `AuthController@login` | guest |
| POST | `/register` | register | `AuthController@register` | guest |
| POST | `/logout` | logout | `AuthController@logout` | auth, no-history |
| GET | `/up` | – | Laravel health check | (built-in) |

### Members (signed in)
| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/films` | films | `PageController@films` (`?q=` search) |
| GET | `/films/{slug}` | films.show | `PageController@show` |
| POST | `/films/{slug}/like` | films.like | `FilmActionController@like` |
| POST | `/films/{slug}/watch` | films.watch | `FilmActionController@watch` |
| POST | `/films/{slug}/rate` | films.rate | `FilmActionController@rate` |
| POST | `/films/{slug}/reviews` | reviews.store | `ReviewController@store` |
| DELETE | `/reviews/{review}` | reviews.destroy | `ReviewController@destroy` |
| POST | `/reviews/{review}/like` | reviews.like | `ReviewController@like` |
| GET | `/trailers` | trailers | `PageController@trailers` |
| GET | `/lists` | lists | `PageController@lists` |
| GET | `/profile` | profile | `PageController@profile` |
| GET | `/profile/{type}` | profile.collection | `PageController@collection` (`likes` or `watchlist`) |
| GET | `/settings` | settings | view `settings` |
| PUT | `/settings` | settings.update | ⚠️ **stub**: does nothing yet |
| DELETE | `/account` | account.deactivate | ⚠️ **stub**: does nothing yet |

All use middleware: `auth, no-history`.

### Admin (signed in + `is_admin`)
| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/admin` | admin.dashboard | `Admin\DashboardController` |
| GET | `/admin/movies` | admin.movies.index | `Admin\MovieController@index` (`?status=`, `?q=`) |
| GET | `/admin/movies/create` | admin.movies.create | `Admin\MovieController@create` |
| POST | `/admin/movies` | admin.movies.store | `Admin\MovieController@store` |
| GET | `/admin/movies/{movie}/edit` | admin.movies.edit | `Admin\MovieController@edit` |
| PUT/PATCH | `/admin/movies/{movie}` | admin.movies.update | `Admin\MovieController@update` |
| DELETE | `/admin/movies/{movie}` | admin.movies.destroy | `Admin\MovieController@destroy` |
| GET | `/admin/users` | admin.users.index | `Admin\UserController@index` (`?q=`) |
| PATCH | `/admin/users/{user}/admin` | admin.users.admin | `Admin\UserController@toggleAdmin` |

All use middleware: `auth, no-history, can:admin`.

The admin rule is defined in `app/Providers/AppServiceProvider.php`:
```php
Gate::define('admin', fn (User $user) => $user->is_admin);
```

---

## 3. Browser → server (JavaScript, no page reload)

File: `resources/js/app.js`. These send data in the background with `fetch()`.

**How CSRF protection works here:** every form has `@csrf`, which adds a hidden field `_token`. `new FormData(form)` includes it, so Laravel accepts the request. Without it, Laravel answers **419**.

### 3.1 Sign in (`form[data-require-all]` → `POST /login`)
```js
const body = new FormData(form);
const res = await fetch(form.action, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body,
});
if (res.ok) return (location.href = (await res.json()).redirect);
if (res.status === 419) return location.reload();          // page sat open too long (expired token)
```
| | |
|---|---|
| **Sends** | `_token`, `name` (username), `password`, `remember` (`1` if ticked) |
| **Server** | `AuthController@login` → `Auth::attempt($creds, $remember)` checks the `users` table |
| **Back (OK)** | `200 {"redirect": "http://mire.test/…"}`, then the browser goes there |
| **Back (wrong details)** | `422 {"message": "...", "errors": {"credentials": ["Username or password is incorrect."]}}` |
| **Back (empty field)** | `422 {"errors": {"name": [...]}}` or `{"password": [...]}` |

### 3.2 Instant buttons (`form[data-instant]`): like, watchlist, stars, review hearts
```js
const body = new FormData(form, e.submitter);              // includes which star was clicked
const res = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', ... }, body });
if (res.status === 401 || res.status === 419) return location.reload();   // signed out / stale page
const data = await res.json();
```
Server side, `app/Http/Controllers/FilmActionController.php`:
```php
private function respond(Request $request, array $state, string $message)
{
    return $request->expectsJson()
        ? response()->json($state + ['message' => $message])   // ← app.js gets this
        : back()->with('status', $message);                    // ← no JavaScript: normal reload
}
```
| Button | Route | Sends | JSON back |
|---|---|---|---|
| ❤️ Like | `POST /films/{slug}/like` | `_token` | `{"liked": true, "message": "Added to your likes"}` |
| 👁 Watchlist | `POST /films/{slug}/watch` | `_token` | `{"watchlisted": true, "message": "Added to your watchlist"}` |
| ★ Stars | `POST /films/{slug}/rate` | `_token`, `rating` (1–5) | `{"rating": 4, "message": "Rated 4 out of 5"}` (same star again → `0`, "Rating cleared") |
| ❤️ on a review | `POST /reviews/{id}/like` | `_token` | `{"liked": true, "likes": 3, "message": "You liked this review"}` (`ReviewController@like`) |

### 3.3 Trailer pop-up (`[data-trailer]` → YouTube, not our server)
```js
frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(link.dataset.trailer)}?autoplay=1&rel=0`;
trailerModal.addEventListener('hidden.bs.modal', () => frame.removeAttribute('src'));   // closing stops the video
```
- **Sends:** only the YouTube video id (e.g. `3CqymRQ1uUU`) in the iframe URL, straight from the browser to YouTube.
- The id comes from TMDB (see section 5) and is printed in the page as `data-trailer="…"`.

### 3.4 Other browser-only behaviour (no server request)
- `[data-share]`: copies the film link (`navigator.clipboard`) or opens the phone's share sheet.
- `[data-confirm]`: "Are you sure?" before admin delete or make-admin.
- `[data-poster]`: shows the chosen poster before uploading (`URL.createObjectURL`, local only).
- `[data-password-rules]`: password checklist (local only; the server checks again).

---

## 4. Browser → server (normal forms, page reloads)

| Form | Where | Route | Data sent | Server does |
|---|---|---|---|---|
| Create account | `partials/join-modal.blade.php` | `POST /register` | `_token, email, name, password` | validate → `User::create()` → `Auth::login()` |
| Sign out | header menu | `POST /logout` | `_token` | `Auth::logout()`, new session |
| Write/update review | `film-show.blade.php` | `POST /films/{slug}/reviews` | `_token, body` | `updateOrCreate` in `reviews` |
| Delete review | `partials/review-card.blade.php` | `DELETE /reviews/{id}` | `_token, _method=DELETE` | only the author (`abort_unless … 403`) |
| Search | header | `GET /films?q=…` | `q` | `FilmCatalog::search()` → TMDB |
| Add movie | `admin/movies/form.blade.php` | `POST /admin/movies` | `multipart`: fields + `poster` file + `status` | save to `movies` + `genre_movie` + poster file |
| Edit movie | same form | `PUT /admin/movies/{id}` | same + `remove_poster` (0/1), `_method=PUT` | update row, replace/delete poster |
| Delete movie | `admin/movies/index.blade.php` | `DELETE /admin/movies/{id}` | `_token, _method=DELETE` | delete row + poster file |
| Make/remove admin | `admin/users/index.blade.php` | `PATCH /admin/users/{id}/admin` | `_token, _method=PATCH` | flip `is_admin` (not yourself → 403) |

**`_method`:** HTML forms can only send GET or POST. `@method('DELETE')` adds a hidden `_method` field and Laravel treats the request as DELETE, PUT or PATCH.

### Create account (`app/Http/Controllers/AuthController.php` → `register()`)
```php
$data = $request->validateWithBag('register', [
    'email'    => 'required|email|unique:users,email',             // ← checks the users table
    'name'     => 'required|string|min:3|max:30|unique:users,name',
    'password' => ['required', 'string', Password::defaults()],    // ← 8+ chars, Aa, number, symbol
]);
$user = User::create($data);       // password is hashed by the User model ('hashed' cast)
Auth::login($user);
```

### Add movie (`app/Http/Controllers/Admin/MovieController.php` → `store()`)
```php
$data = $this->validated($request);                    // title, year, runtime, genres[], age_rating, director, description, poster, status
$movie = new Movie($data);
$movie->created_by = $request->user()->id;
if ($request->hasFile('poster')) {
    $movie->poster = $request->file('poster')->store('posters', 'public');   // → storage/app/public/posters/xxxx.jpg
}
$movie->save();                                        // → movies table
$movie->genres()->sync($data['genres']);               // → genre_movie table
```
The form needs `enctype="multipart/form-data"` (in `admin/movies/form.blade.php`), or the file isn't sent.

---

## 5. Server → outside APIs (TMDB, haveibeenpwned)

**Only one file talks to TMDB:** `app/Support/FilmCatalog.php`.

It's called from the controllers (`PageController`, `FilmActionController`, `ReviewController`) **and from some views directly**:
- `components/poster.blade.php`: `byTitles()` when a poster only has a title
- `films.blade.php`: `byTitles()` for the "Just reviewed" sample row
- `film-show.blade.php`: `trending(9)` when TMDB has no "You might also like"

### Every TMDB endpoint used
| Endpoint | Function | Used for | Cached |
|---|---|---|---|
| `GET /trending/all/week` | `trending()`, `homePools()` | Landing, Home rows, Films "Trending" | 6h / 12h |
| `GET /discover/movie` (acclaimed, classics) | `homePools()` | Home "Favorite of all time", "Worth rewatching" | 12h |
| `GET /discover/movie?with_genres=…` | `genreShelves()` | Lists page (one per genre) | 6h |
| `GET /discover/movie?primary_release_date.gte=…` | `trailerShelves()` | Trailers "Coming soon" | 6h |
| `GET /trending/movie/week` | `trailerShelves()` | Trailers "Trending" | 6h |
| `GET /movie/popular` | `trailerShelves()` | Trailers "Popular" | 6h |
| `GET /search/multi?query=…` | `search()`, `byTitles()` | Search box, sample rows | 1h / 1 week |
| `GET /genre/movie/list` | `genreShelves()` | Genre names | 1 week |
| `GET /{type}/{id}?append_to_response=credits,recommendations,reviews,videos` | `find()` | Film page (details, cast, reviews, trailer) in **1 request** | 1 day |
| `GET /{type}/{id}/reviews` | `trendingReviews()` | Films "Popular reviews" backup | 12h |
| `GET /{type}/{id}/videos` | `videosFor()` | Trailer keys (Trailers page, Home banner) | 1 day |

`{type}` is `movie` or `tv`, and `{id}` is the TMDB id, both taken from the slug (`movie-603-the-matrix` → `movie`, `603`).

**Sends:** `Authorization: Bearer ***` (from `TMDB_TOKEN`) + the query values above.
**Back:** JSON, which `normalize()` turns into one "film" array the views use:
```php
[
  'slug' => 'movie-603-the-matrix', 'tmdb_id' => 603, 'type' => 'movie',
  'title' => 'The Matrix', 'poster' => 'https://image.tmdb.org/t/p/w500/…jpg',
  'backdrop' => 'https://image.tmdb.org/t/p/w1280/…jpg', 'description' => '…',
  'release_date' => '1999-03-30', 'years' => [1999], 'score' => 8.2,
  // film page only: 'genres', 'runtime', 'cast', 'director', 'trailer' => ['key' => '…', 'name' => '…', 'type' => 'Trailer'], …
]
```

### One request: `get()` with cache + outage protection
```php
private static function get(string $path, array $query = [], $ttl = null): ?array
{
    $key = 'tmdb:'.$path.'?'.http_build_query($query);          // cache key, e.g. tmdb:/movie/popular?page=1
    if (($hit = Cache::get($key)) !== null) {                     // ← cached? no TMDB call
        return $hit;
    }
    if (! self::available()) {                                    // ← no token, or TMDB failed in the last 60s
        return null;
    }
    try {
        $res = self::configure(Http::createPendingRequest())->get($path, $query);
    } catch (ConnectionException $e) {
        self::markDown($e);                                       // ← remember the outage for 60s (cache key tmdb:down)
        return null;
    }
    ...
    Cache::put($key, $data = $res->json(), $ttl ?? now()->addHours(6));   // ← saved in MySQL `cache` table
    return $data;
}
```

### Many requests at once: `getMany()` (parallel)
```php
$responses = Http::pool(fn (Pool $pool) => array_map(
    fn ($key) => self::configure($pool->as((string) $key))->get(...$requests[$key]),
    $missing,                                                     // ← only the ones not already cached
));
```
For example, the Trailers page fetches 3 lists **at the same time**, then about 30 `/videos` requests **at the same time**.

### Picking the trailer: `pickTrailer()`
```php
->filter(fn ($v) => ($v['site'] ?? '') === 'YouTube' && ! empty($v['key']))   // only YouTube can be embedded
->sortBy(...)    // official Trailer → any Trailer → Teaser
```

### Admin movies mixed in (database, not TMDB)
`FilmCatalog` also returns **published** movies from the `movies` table, so the public pages show them with no extra code in the views. They're **never sent to TMDB** (no `tmdb_id`).

| Where | Function | What happens |
|---|---|---|
| Film page `/films/local-5` | `find()` → `findLocal()` | `Movie::published()->find(5)` + "similar" = movies sharing a genre. No title in the link, so **renaming a movie keeps its likes, ratings and reviews** |
| Draft or deleted movie | `findOrPlaceholder()` | **404**, so there's no page and no likes or ratings on it |
| Search box | `search()` | matching published titles come **first**, then TMDB results |
| Films page | `localLatest()` | "New on Miré Film" row (hidden when empty) |
| Lists page | `withLocalGenres()` | added to the front of the matching TMDB box (Sci-fi → Science Fiction); BL / Filipino Teleserye get their own box |

`app/Models/Movie.php` turns a row into the same "film" array as TMDB films:
```php
public function slug(): string { return "local-{$this->id}"; }        // no title: likes/reviews match this exact text

public function posterUrl(): ?string
{
    return $this->poster ? '/storage/'.$this->poster : null;          // no http://host: works on mire.test, 192.168.x.x:8000, …
}

public function toCatalogFilm(bool $detailed = false): array { /* slug, title, poster, years, description … genres, runtime, director, age_rating */ }
```
Deleting a movie in admin also deletes members' likes, watchlist, ratings and reviews of it (`MovieController::destroy()`: `film_slug = 'local-5'`).

The poster saved with a like or review is `/storage/posters/…` with no host, so it still shows on a phone or after the Wi-Fi IP changes.

### When TMDB isn't available
- No `TMDB_TOKEN`, or the internet is down → `fallback()` returns 5 built-in films (Arcane, Pearl, …), so pages still work.
- The Trailers page shows "No trailers right now".

### Server → haveibeenpwned.com (password leak check, live site only)
`app/Providers/AppServiceProvider.php`
```php
return $this->app->isProduction() ? $rule->uncompromised() : $rule;
```
- Only when `APP_ENV=production`: during **Create account**, the **server** asks haveibeenpwned.com whether the new password appeared in a data leak.
- **Sends:** only the first 5 characters of the password's SHA-1 hash, never the password itself.
- **Back:** a list of matching hashes; if the full hash is in it, sign-up is refused.
- Off on your laptop (`APP_ENV=local`), so sign-up works without internet.

---

## 6. Browser → other outside services (straight from the page)

| Service | Where in the code | What the browser requests |
|---|---|---|
| **TMDB images** | `components/poster.blade.php`, home banners, trailer cards | `https://image.tmdb.org/t/p/w342/…jpg` (posters), `w780`/`w1280` (backdrops) |
| **YouTube player** | `partials/trailer-modal.blade.php` + `app.js` | `https://www.youtube-nocookie.com/embed/{key}` (privacy mode, no cookies until play) |
| **YouTube thumbnails** | `partials/trailer-card.blade.php` | `https://i.ytimg.com/vi/{key}/mqdefault.jpg` (only if TMDB has no backdrop) |
| **YouTube (no JS)** | trailer links' `href` | `https://www.youtube.com/watch?v={key}` |
| **Google Fonts** | `layouts/app.blade.php`, `layouts/admin.blade.php` | Bebas Neue, Manrope, Michroma |

```blade
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@300;400;500;600;700&family=Michroma&display=swap" rel="stylesheet">
@vite(['resources/scss/app.scss', 'resources/js/app.js'])   {{-- our own CSS/JS from public/build --}}
```

---

## 7. Server ↔ database (MySQL `mire`)

Laravel talks to MySQL through **Eloquent models** (`app/Models/`). Each model = one table.

### Tables and how they link
```
users ─┬─< likes            (user_id, film_slug, title, poster)              unique(user_id, film_slug)
       ├─< watchlist_items  (user_id, film_slug, title, poster)              unique(user_id, film_slug)
       ├─< ratings          (user_id, film_slug, title, poster, rating 1–5)  unique(user_id, film_slug)
       ├─< reviews ─< review_likes (user_id, review_id)                       unique(user_id, film_slug) / (user_id, review_id)
       ├─< movies (created_by) ─< genre_movie >─ genres
       └─< sessions         (who is signed in)

cache   (TMDB answers, key → value + expiry)     jobs (queue, unused for now)
```
- `─<` = "one to many". `>─<` = "many to many" through `genre_movie`.
- **Delete a user** → their likes, watchlist, ratings, reviews and review likes are deleted too (`cascadeOnDelete`). Movies they added stay, with `created_by` set to empty (`nullOnDelete`).
- **`film_slug`** (e.g. `movie-603-the-matrix`, or `local-5` for an admin movie) links a row to a film. There's no TMDB table: films live in TMDB, and we only keep a **snapshot** (title + poster) so the profile page doesn't need TMDB.

| Table | Model | Created by migration | Written by |
|---|---|---|---|
| `users` (+ `is_admin`) | `User` | `0001_01_01_000000_…`, `2026_10_15_000000_…` | `AuthController@register`, `mire:admin`, `Admin\UserController` |
| `sessions` | – | `0001_01_01_000000_…` | Laravel itself (`SESSION_DRIVER=database`) |
| `cache` | – | `0001_01_01_000001_…` | `FilmCatalog` (`CACHE_STORE=database`) |
| `likes`, `watchlist_items`, `ratings` | `Like`, `WatchlistItem`, `Rating` | `2026_10_12_000000_…` | `FilmActionController` |
| `reviews`, `review_likes` | `Review`, `ReviewLike` | `2026_10_13_000000_…` | `ReviewController` |
| `movies`, `genres`, `genre_movie` | `Movie`, `Genre` | `2026_10_15_000001_…` | `Admin\MovieController` (genres pre-filled by the migration) |

### Relationships in code: `app/Models/User.php`
```php
public function likes(): HasMany     { return $this->hasMany(Like::class); }
public function watchlist(): HasMany { return $this->hasMany(WatchlistItem::class); }
public function ratings(): HasMany   { return $this->hasMany(Rating::class); }
public function reviews(): HasMany   { return $this->hasMany(Review::class); }
```
`app/Models/Movie.php`
```php
public function genres(): BelongsToMany { return $this->belongsToMany(Genre::class); }    // via genre_movie
public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }
```

### Example: what the Like button does in SQL: `FilmActionController::toggle()`
```php
if ($relation->where('film_slug', $slug)->delete()) {   // DELETE FROM likes WHERE user_id = ? AND film_slug = ?
    return false;                                       // something was deleted → now "not liked"
}
$relation->create(['film_slug' => $slug] + $this->snapshot($slug));
// INSERT INTO likes (user_id, film_slug, title, poster, created_at, updated_at) VALUES (…)
return true;
```

### Example: reading for a page: `PageController::profile()`
```php
'likes'      => $user->likes()->newest()->take(5)->get()->map->toFilm()->all(),   // SELECT … FROM likes WHERE user_id = ? ORDER BY created_at DESC LIMIT 5
'likesCount' => $user->likes()->count(),                                          // SELECT COUNT(*) FROM likes WHERE user_id = ?
```

### Protection built into the database layer
- **Passwords** are hashed (`'password' => 'hashed'` cast in `User.php`). The real password is never stored.
- **`is_admin` isn't fillable**, so forms can't set it. Only `forceFill()` (the command and admin page) can.
- **Unique indexes** stop double likes or double reviews, even if two clicks arrive at once.

---

## 8. File storage (admin posters)

```
upload (form) ──► storage/app/public/posters/abc123.jpg     (saved by MovieController: ->store('posters', 'public'))
                        ▲
public/storage ─────────┘  (junction made by `php artisan storage:link`)
                        │
browser ◄── http://mire.test/storage/posters/abc123.jpg     (URL from Movie::posterUrl())
```
```php
public function posterUrl(): ?string
{
    return $this->poster ? '/storage/'.$this->poster : null;          // no host: the browser adds the address it's on, so phones work too
}
```
- The database only stores the **path** (`posters/abc123.jpg`), not the image.
- The old file is deleted (`MovieController::deletePoster()`) when you **delete the movie**, or **save** the form after Replace/Remove. Pressing Remove alone only sets `remove_poster=1` in the browser; nothing happens until you save.

---

## 9. Command line → database

`routes/console.php`
```php
Artisan::command('mire:admin {email} {--remove}', function (string $email) {
    $user = User::where('email', $email)->first();                         // SELECT … FROM users WHERE email = ?
    $user->forceFill(['is_admin' => ! $this->option('remove')])->save();   // UPDATE users SET is_admin = ? …
});
```
```bash
php artisan mire:admin someone@email.com             # make admin
php artisan mire:admin someone@email.com --remove    # remove admin
php artisan migrate                                  # create/update the tables (database/migrations/)
```

---

## 10. Not connected yet (honest list)

- `TMDB_API_KEY` and `YOUTUBE_API_KEY` are in `.env` but **no code uses them**. Only `TMDB_TOKEN` is used.
- **Admin movies** show on the film page, search, Films ("New on Miré Film") and Lists, but **not on Home or Trailers** (they have no trailer or backdrop).
- `PUT /settings` and `DELETE /account` are **placeholders** (they just redirect).
- The `jobs` table (queue) exists but nothing uses background jobs yet.
- Uploaded posters may not show in **Docker**, because `public/storage` is a Windows junction the Linux container can't follow.
