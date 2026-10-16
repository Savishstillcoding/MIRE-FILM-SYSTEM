# MIRÉ Film: changelog

Every update to the system, **newest first**. Each entry says **what changed, why, which files, and how it was checked**.

**Other notes:**
- `NOTES-SETUP.md`: how to run it (Laragon, Docker, other devices, admin commands)
- `NOTES-CONNECTIONS.md`: how the parts connect (routes, APIs, database)

**Legend:** ✨ new feature · 🔧 change · 🐛 bug fix · 🔒 security · 🧪 tests · 📝 notes · ⚙️ setup (outside the code)

---

## How to add an entry

Copy this to the top of the list, under the line below:

```markdown
## YYYY-MM-DD: short title
**Commit:** `abc1234` "commit message"   (or: not committed yet)

- ✨ / 🔧 / 🐛 … what changed, in one line
  - Why: …
  - Files: `path/to/file`
- 🧪 Tests: N passing (`php artisan test`)
- To apply: commands needed after pulling (e.g. `php artisan migrate`, `npm run build`)
```

**Tips**
- See what changed since the last commit with `git status`, and line by line with `git diff`.
- See past commits with `git log --oneline`.
- Write the entry **before** committing, then commit the code and this file together.

---

## 2026-10-15 (evening): Terminal guide in NOTES-SETUP.md
**Commit:** not committed yet

- 📝 New section **"✅ After setup: which terminal for what"** at the top of `NOTES-SETUP.md`:
  - A quick check that setup still works.
  - A table of which commands run in the Laragon terminal, VS Code terminal and Git Bash. `npm`, `node` and `mysql` only work in Laragon's terminal.
  - Fixes when a command "isn't found" in VS Code (Laragon terminal, full path, or add Node to PATH).
  - Common errors in each terminal, and an everyday flow to copy.

---

## 2026-10-15 (evening): Show / hide password on sign-up
**Commit:** not committed yet

- ✨ **👁 button in the Create account password box**
  - Click to see what you typed, click again to hide it. The icon switches to a crossed-out eye while the password is visible.
  - It hides itself again when you submit or close the pop-up.
  - Screen readers hear "Show password" / "Hide password".
  - 🔧 Restyled: the eye sits **inside** the box on the right (like the search bar's 🔍) instead of a separate box beside it.
  - Files: `resources/views/partials/join-modal.blade.php`, `resources/js/app.js` (`data-password-toggle`), `resources/scss/app.scss` (`.password-toggle`)
- 🧪 Tests: **71 passing** (`PasswordRulesTest` checks the button is there)
- **To apply:** `npm run build`

---

## 2026-10-15 (afternoon): Admin movies on the public site
**Commit:** not committed yet (10 changed files + 1 new)

- ✨ **Published admin movies show on the public site**
  - **Film page** `/films/local-{id}`: poster, year, runtime, genres, director, description. Like / watchlist / stars / reviews all work. "You might also like" shows your movies with the same genre.
  - **Search**: your movies come first, then TMDB.
  - **Films page**: new "New on Miré Film" row (hidden when empty).
  - **Lists page**: added to the front of the matching genre box (Sci-fi → TMDB "Science Fiction"). BL / Filipino Teleserye get their own box, only when they have movies.
  - **Not** on Home or Trailers (admin movies have no trailer or backdrop).
  - Files: `app/Support/FilmCatalog.php` (`find`, `findLocal`, `search`, `localLatest`, `withLocalGenres`), `app/Models/Movie.php` (`slug`, `toCatalogFilm`), `app/Http/Controllers/PageController.php`, `resources/views/films.blade.php`
- ✨ **MTRCB age rating badge** (G, PG, R-13…) on admin movies' film pages: `resources/views/film-show.blade.php`
- ✨ **"View" button** for published movies in Admin → Movies: `resources/views/admin/movies/index.blade.php`
- 🔒 **Drafts are private**: a draft or deleted movie's page gives **404**, and it can't be liked or rated: `FilmCatalog::findOrPlaceholder()`
- 🔧 **Deleting a movie also deletes members' likes / watchlist / ratings / reviews of it**: `app/Http/Controllers/Admin/MovieController.php` (`destroy`)
- 🐛 **Poster links work on phones**
  - Before: posters were saved as `http://mire.test/storage/…`, which phones can't open, and it broke when the Wi-Fi IP changed.
  - After: `/storage/…` with no host (`Movie::posterUrl()`).
- 🐛 **Renaming a movie keeps its likes and reviews**: the link is just `local-5` (no title), because likes and reviews are matched by that exact text (`Movie::slug()`).
- 🧪 Tests: **71 passing**. New: `tests/Feature/LocalMoviesTest.php` (9 tests).
  - Fixed `tests/Feature/FilmCatalogTest.php`: one test was secretly calling the real internet (6s → 0.5s).
- 📝 Updated `NOTES-CONNECTIONS.md` ("Admin movies mixed in") and `NOTES-SETUP.md`
- 📝 Added this file, `NOTES-CHANGELOG.md`
- **To apply:** `php artisan migrate` (if not done yet), `npm run build`

---

## 2026-10-15: Admin dashboard, Trailers, password rules, connection notes
**Commit:** `e8c97a2` "[UPDATES/CHANGES] Added Admin Dashbaord and Trailer in Nav Bar and has more changes in the UI" (38 files)

### Admin dashboard
- ✨ **Admin area** at `/admin` with its own layout (sidebar + top bar):
  - **Dashboard**: stat cards (Users, Reviews, Ratings, Movies added: total + today), recent activity, "+ Add movie"
  - **Movies**: All / Published / Drafts tabs, search, Edit, Delete (asks first)
  - **Add / Edit movie**:
    - Poster upload (2:3, JPG/PNG/WebP, max 2 MB, preview, Replace / Remove)
    - Title, Year, Runtime, Genres (multi-pick), Age rating (MTRCB), Director, Description
    - Cancel / Save as draft / Publish
  - **Users**: search, joined date, review/rating counts, Make admin / Remove admin
  - Files: `app/Http/Controllers/Admin/` (Dashboard, Movie, User), `resources/views/admin/`, `resources/views/layouts/admin.blade.php`, `app/Models/Movie.php`, `app/Models/Genre.php`
- ✨ **Database**: `is_admin` on users; new `movies`, `genres` (7 pre-filled: Action, Comedy, Romance, BL, Sci-fi, Filipino Teleserye, Horror), `genre_movie`
  - Files: `database/migrations/2026_10_15_000000_add_is_admin_to_users_table.php`, `…_000001_create_movies_and_genres_tables.php`
- ✨ **Command** `php artisan mire:admin {email} [--remove]` to make the first admin: `routes/console.php`
- 🔒 **Admin-only access**:
  - Gate `admin` (`app/Providers/AppServiceProvider.php`) + `can:admin` on all `/admin` routes (`routes/web.php`).
  - Members get 403, guests go to sign-in.
  - `is_admin` can't be set through forms (not fillable in `app/Models/User.php`), and admins can't remove their own access.
- ✨ "Admin dashboard" link in the account menu (admins only): `resources/views/partials/header.blade.php`

### Trailers
- ✨ **TRAILERS** in the navbar, plus a `/trailers` page with **Coming soon / Trending / Popular** rows (no "In theaters")
- ✨ **Pop-up player** (YouTube privacy mode). Closing it stops the video, and "View film page →" goes to the film.
- ✨ **▶ Watch trailer** button on film pages
- ✨ **Featured trailer** banner on Home
- Data from TMDB `/videos` (no new API key needed). Best video picked: official Trailer → Trailer → Teaser.
- Files: `app/Support/FilmCatalog.php` (`trailerShelves`, `firstWithTrailer`, `videosFor`, `pickTrailer`), `resources/views/trailers.blade.php`, `resources/views/partials/trailer-card.blade.php`, `resources/views/partials/trailer-modal.blade.php`, `resources/views/home.blade.php`, `resources/views/film-show.blade.php`, `resources/js/app.js`, `resources/scss/app.scss`
- 🐛 "Coming soon" first used TMDB "upcoming" (mostly already-released films, so only 1 trailer). It now asks for release dates after today (10 trailers).

### Password rules
- 🔒 **Strong passwords on sign-up**: at least 8 characters, an uppercase **and** a lowercase letter, a number, a symbol.
  - Live site only: also refuses passwords from known data leaks (haveibeenpwned).
  - Existing accounts aren't affected.
  - Files: `app/Providers/AppServiceProvider.php` (`Password::defaults`), `app/Http/Controllers/AuthController.php`
- ✨ **Live checklist** under the password box (grey ○ → green ✓ as you type): `resources/views/partials/join-modal.blade.php`, `resources/js/app.js`, `resources/scss/app.scss`

### Smaller changes
- 🔧 Settings: "Danger zone" heading → "Delete account": `resources/views/settings.blade.php`
- 🐛 Dropdown options were white text on white: `resources/scss/app.scss` (`.form-select option`)
- 🐛 `.mire-card` was used on 7 pages but never defined (cards had no background): `resources/scss/app.scss`

### Tests and notes
- 🧪 Tests: **62 passing**. New: `tests/Feature/AdminTest.php` (15), `TrailersTest.php` (6), `PasswordRulesTest.php` (4).
  - `RouteSecurityTest.php` now also checks `/trailers` is members-only.
- 📝 New `NOTES-CONNECTIONS.md` (how everything connects). `NOTES-SETUP.md` got the admin section.
- **To apply:** `php artisan migrate`, `php artisan storage:link`, `php artisan mire:admin your@email`, `npm run build`

---

## 2026-10-14: Setup notes + Docker fix
**Commit:** `50bbd62` "[UPDATED] Few files that has few updates/changes" (4 files)

- 🐛 **Docker database login**
  - `docker-compose.yml` now uses its own `DOCKER_DB_*` settings (default `mire` / `secret`).
  - Before, it read Laragon's `DB_USERNAME=root` and the MySQL container refused to start.
- 📝 New `NOTES-SETUP.md`: Laragon + Docker setup, commands, troubleshooting, the link for other devices
- 🔧 `artisan` and `docker/entrypoint.sh`: only the file permission (executable flag) changed, a Windows side effect with no content change. `git config core.fileMode false` stops it showing up.

---

## 2026-10-13 → 10-15: Machine setup (outside the project, not in git)

- ⚙️ **Laragon set up** for daily coding: http://mire.test
  - Junction `C:\laragon\www\mire` → the project folder
  - MySQL database `mire` created and migrated
- ⚙️ **PHP 8.4.26** added to Laragon (`C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\`), because `composer.lock` needs PHP 8.4.1 or newer
- ⚙️ **Windows user PATH**: `C:\xampp\php` (8.2) replaced with Laragon's PHP 8.4
  - Backup: `C:\Users\Acer\path-backup-2026-10-13.txt`
- ⚙️ **VS Code settings** (`%APPDATA%\Code\User\settings.json`, backup `settings.backup-2026-10-15.json`):
  - `editor.fontFamily` → Consolas (matches the terminal)
  - `LaravelExtraIntellisense.phpCommand` → `php` (was XAMPP 8.2, autocomplete was broken)
  - `editor.wordWrap` → on
- ⚙️ **Google Cloud**: YouTube Data API v3 key created (in `.env`, **not used by code yet**)
- ⚙️ **Sharing on Wi-Fi**: `php artisan serve --host=0.0.0.0 --port=8000` → `http://<your IP>:8000` (see NOTES-SETUP.md)

---

## Before these notes (from git history)

| Date | Commit | Message |
|---|---|---|
| 2026-10-13 | `93f5a2d` | [UPDATE] Added pictures to the landing page and updated a few files |
| 2026-10-12 | `e32905a` | [MAJOR UPDATE/CHANGES] Upgrade UI to Bootstrap, add TMDB data, likes/watchlist/ratings, reviews, genre lists, sign-in warnings and secured routing |
| 2026-10-06 | `ac5a946` | MIRE-FILM Initial Set-up |

---

## Still to do

- [ ] Settings → **Save changes** (profile form) is a placeholder
- [ ] Settings → **Delete account** is a placeholder (heading says Delete, button says Deactivate; pick one)
- [ ] Admin movies on **Home / Trailers** (would need a trailer link field in Add movie)
- [ ] **Trailer link** field in Add movie (paste a YouTube link)
- [ ] Uploaded posters in **Docker** (the `public/storage` junction is Windows-only)
- [ ] Add Node to the Windows PATH (so `npm` works in VS Code's terminal)
- [ ] `restart: unless-stopped` in `docker-compose.yml` (containers come back after a Docker crash)
- [ ] Before going public: `APP_DEBUG=false`, strong DB passwords, deploy with `docker-compose.prod.yml`
