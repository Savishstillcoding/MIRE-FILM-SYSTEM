# Session notes: MIRE Film on Laragon + Docker (Oct 13, 2026)

## 📱 Link for other devices

```
http://192.168.137.127:8000
```

Before sharing it (Laragon terminal):
```bash
cd C:\laragon\www\mire
npm run build                                   # only if you changed CSS/JS
php artisan serve --host=0.0.0.0 --port=8000    # keep running; Ctrl+C stops it
```

- The other device must be on the **same Wi-Fi** as the laptop (the IP was taken on "TRISTAN KALBO 2", Oct 14).
- **This IP can change** when you switch Wi-Fi or reconnect. If the link stops working, run `ipconfig`, find **IPv4 Address** under "Wireless LAN adapter Wi-Fi", and use `http://<that IP>:8000`.
- On the laptop itself: `http://127.0.0.1:8000` or `http://mire.test`.

## 🛡️ Admin dashboard (added Oct 15, 2026)

### How admins work
- There is **one sign-up for everyone**. Every new account starts as a **regular user**.
- An admin is a user whose **`is_admin`** flag (a yes/no column in the `users` table) is switched on.
- Nobody can sign up *as* admin. Otherwise anyone could make themselves admin. `is_admin` is protected from forms (not "fillable" in `app/Models/User.php`).
- **First admin:** made by the developer with a server command (`php artisan mire:admin`). Only someone with access to the server's terminal can run it.
- **After that:** existing admins promote others in the browser: **Admin → Users → Make admin**.
- Admins sign in with the same sign-in box. They get an **"Admin dashboard"** link in the account menu.
- Regular users who open `/admin` get **403 Forbidden**, and guests are sent to sign in.

```
Anyone signs up ──► Regular user (is_admin = false)
                          │  promoted by: php artisan mire:admin   (first admin)
                          │           or: Admin → Users → Make admin (later)
                          ▼
                    Admin (is_admin = true) ──► can open /admin
```

### First-time setup (Laragon terminal; Laragon must be started)
```bash
cd C:\laragon\www\mire
php artisan migrate                           # adds is_admin + movies/genres tables (once)
php artisan storage:link                      # lets uploaded posters show (once; already done)
php artisan mire:admin 2402063@lnu.edu.ph     # make your account an admin
```
Then open **http://mire.test/admin**.

### Admin commands
```bash
php artisan mire:admin someone@email.com            # make admin      → "name is now an admin."
php artisan mire:admin someone@email.com --remove   # remove admin    → "name is no longer an admin."
php artisan list mire                               # show the app's own commands
```
- The person must **already have an account** (signed up). Otherwise: `No user with the email ...` (in red).
- `mire:admin` = the command name, `someone@email.com` = **argument** (which account), `--remove` = **option** (a switch).
- **Docker:** put `docker compose exec app` in front, e.g. `docker compose exec app php artisan migrate` and `docker compose exec app php artisan mire:admin someone@email.com`.
- Code: `routes/console.php`.

### Admin pages
| Page | URL | What it does |
|---|---|---|
| Dashboard | `/admin` | Stat cards (Users, Reviews, Ratings, Movies added: total + today), recent activity, "+ Add movie" |
| Movies | `/admin/movies` | All / Published / Drafts tabs, search, Edit, Delete (asks first) |
| Add / Edit movie | `/admin/movies/create` | Poster (2:3, JPG/PNG/WebP, max 2 MB), Title, Year, Runtime, Genres, Age rating (MTRCB), Director, Description; Cancel / Save as draft / Publish |
| Users | `/admin/users` | Search name/email, joined date, review + rating counts, Make admin / Remove admin (not yourself) |

### Terms (for explaining it)
| Term | Meaning in this app |
|---|---|
| Authentication | *Who are you?* The sign-in box checks username + password |
| Authorization | *What may you do?* The `admin` Gate (`app/Providers/AppServiceProvider.php`) checks `is_admin` |
| Role-based access control (RBAC) | Permissions depend on the role: user vs admin |
| Bootstrapping the first admin | Someone has to be the first admin: the `mire:admin` command |
| Mass-assignment protection | `is_admin` isn't fillable, so a tampered sign-up form can't set it |

### Files
- Routes: `routes/web.php` (admin group, `can:admin`), `routes/console.php` (`mire:admin`)
- Controllers: `app/Http/Controllers/Admin/` (Dashboard, Movie, User)
- Models: `app/Models/Movie.php`, `Genre.php`, `User.php` (`is_admin`)
- Views: `resources/views/layouts/admin.blade.php`, `resources/views/admin/`
- Migrations: `database/migrations/2026_10_15_*`
- Tests: `tests/Feature/AdminTest.php` (run all with `php artisan test`)

### Not done yet
- Published movies don't show on the public Films/Lists pages yet (TMDB only).
- Settings: Save changes + Delete/Deactivate account are still placeholders.
- Uploaded posters may not show in Docker (the `public/storage` link is a Windows junction).

## 1. Goal and the main idea

Goal: run the already-Dockerized Laravel app with Laragon too.

**Laragon and Docker do the same job** (they provide PHP, a web server and MySQL), so you don't put one inside the other. Both can run the same project folder side by side.

| | Laragon | Docker |
|---|---|---|
| Runs on | Windows directly | Linux containers inside Docker Desktop |
| Use it for | **Daily coding** (fast: ~1.2s per page) | Testing on other devices, checking before deploy (~5.7s per page) |
| URL | http://mire.test | http://127.0.0.1:8081 · phone: http://192.168.100.94:8081 |
| Database | Laragon MySQL → `mire` (user `root`, no password) | Docker MySQL → `mire` (user `mire`, password `secret`) |

The two have **separate databases**: users you create in one don't exist in the other.

## 2. What we did, in order

**Step 1: Moved into the right folder.**
- Problem: `Composer could not find a composer.json file`.
- Cause: the project is nested at `...\MIRE-FILM-SYSTEM\MIRE-FILM-SYSTEM`.
- Lesson: `composer`, `npm` and `php artisan` must run **inside the project folder**.

**Step 2: PHP version clash (XAMPP).**
- Problem: `requires php ^8.3 but your php version (8.2.12)`.
- Cause: the `php` command was XAMPP's PHP 8.2.
- Lesson: the PATH decides which `php.exe` runs.

**Step 3: Lock file needed PHP 8.4.**
- Problem: `symfony/... requires php >=8.4.1` (even on Laragon's PHP 8.3).
- Cause: `composer.lock` was created with PHP 8.4, the same version Docker uses.
- Lesson: never run `composer update` to "fix" this. It rewrites the lock file. Use `composer install`.

**Step 4: Installed PHP 8.4.26 into Laragon.**
- Downloaded **VS17 x64 Thread Safe** from windows.php.net. Apache needs Thread Safe (shown as "ZTS" in `php -v`).
- Checked the SHA256 hash to confirm the download wasn't corrupted.
- Extracted it to `C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\` and switched to it in Laragon → PHP → Version.

**Step 5: Installed dependencies.**
- `composer install` → `vendor/` (PHP packages).
- `npm install` → `node_modules/` (Vite, Bootstrap).
- Ignored the "npm 12 available" notice. The upgrade failed anyway, because it needs a newer Node.

**Step 6: Created the Laragon database.**
- `.env` was switched to MySQL: `DB_DATABASE=mire`, `DB_USERNAME=root`, empty password.
- Created the `mire` database and ran `php artisan migrate`. All 5 migrations ran.

**Step 7: Swapped XAMPP's PHP for Laragon's in your PATH.**
- In your **user PATH**, `C:\xampp\php` was replaced with `C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64`.
- Backup of the old PATH: `C:\Users\Acer\path-backup-2026-10-13.txt`.
- Lesson: terminals only read PATH **when they start**, so already-open windows keep the old one.

**Step 8: Linked the project into Laragon.**
- Created a junction (folder shortcut): `C:\laragon\www\mire` → your project folder.
- Laragon then created the `mire.test` virtual host and hosts-file entry automatically.

**Step 9: Fixed the Laragon page showing instead of your app.**
- Problem: `mire.test` showed the Laragon welcome page.
- Cause: Apache had been running since 8:50 PM, **before** the `mire.test` config existed, and Stop/Start didn't really restart it.
- Fix: force-stopped `httpd.exe` and started it again. `mire.test` then showed "Miré Film".

**Step 10: Fixed the Docker database clash.**
- Problem: Docker was reading Laragon's `DB_USERNAME=root`, and the MySQL container refuses to create a user called `root`.
- Fix: `docker-compose.yml` now reads `DOCKER_DB_*` variables instead, with defaults `mire` / `secret`. One `.env` now works for both.

**Step 11: Prepared for phone access.**
- Stopped `npm run dev` and ran `npm run build`. With the dev server on, phones look for CSS and JS at `localhost:5173`, which is the **phone itself**.
- Lesson: `public/hot` existing means dev-server mode is on.

**Step 12: Docker Desktop crashed.**
- At 10:02 PM, Docker's backend crashed (`exit 0xffffffff`). All containers stopped, and the dashboard window stopped opening.
- Fix: ended the Docker processes in Task Manager, reopened Docker Desktop, then ran `docker compose up -d`.

**Step 13: Result.**
- All 4 containers up, `db` healthy, and http://192.168.100.94:8081 serves Miré Film on your Wi-Fi.

## 3. Files and settings that changed

| What | Where | Change |
|---|---|---|
| PHP 8.4.26 | `C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\` | Added |
| User PATH | Windows environment variables | XAMPP PHP replaced with Laragon PHP 8.4 (backup in `C:\Users\Acer\path-backup-2026-10-13.txt`) |
| Junction | `C:\laragon\www\mire` | Created, points to the project |
| `.env` | Project | Switched to Laragon MySQL; `APP_URL=http://mire.test` |
| `docker-compose.yml` | Project | `DB_*` → `DOCKER_DB_*` (6 lines) |
| `vendor/`, `node_modules/`, `public/build/` | Project | Installed / built |
| Databases | Laragon MySQL and Docker MySQL | Both have `mire` with all tables |

## 4. Commands

### A. First-time setup (already done; for a fresh PC)

**Laragon Terminal (`λ`):**
```bash
# 1. Go to the project
cd C:\Users\Acer\Desktop\MIRE-FILM-SYSTEM\MIRE-FILM-SYSTEM

# 2. Check PHP (must be 8.4.x)
php -v

# 3. Install packages
composer install
npm install

# 4. Database (Laragon MySQL must be running → Start All)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS mire"
php artisan key:generate        # only if APP_KEY in .env is empty
php artisan migrate

# 5. Link the project into Laragon
mklink /J C:\laragon\www\mire C:\Users\Acer\Desktop\MIRE-FILM-SYSTEM\MIRE-FILM-SYSTEM
```
Then in Laragon click **Stop → Start All** and open http://mire.test.

### B. Daily coding (Laragon)
```bash
# Laragon window → Start All
# Laragon Terminal:
cd C:\laragon\www\mire
npm run dev                     # leave running; live CSS/JS reload
```
Open http://mire.test.

### C. Testing on your phone (Docker)
```bash
# 1. Stop npm run dev (Ctrl+C), then build the front-end (Laragon Terminal)
cd C:\laragon\www\mire
npm run build

# 2. Open Docker Desktop and wait for "Engine running", then (any terminal):
docker compose up -d            # add --build the first time or after Dockerfile changes
docker compose ps               # 4 containers Up, db (healthy)
docker compose exec app php artisan migrate
```
- Laptop: http://127.0.0.1:8081
- Phone (same Wi-Fi): http://192.168.100.94:8081
- phpMyAdmin: http://localhost:8082 (user `mire`, password `secret`)

When you're done: `docker compose down`. Back to coding: `npm run dev`.

### D. Docker cheat sheet
```bash
docker compose up -d                 # start
docker compose up -d --build         # rebuild + start (only after Dockerfile/docker/ changes)
docker compose down                  # stop (data kept)
docker compose down -v               # ⚠️ stop AND delete the Docker database
docker compose ps                    # status
docker compose logs -f app           # logs (also: db, web); Ctrl+C to exit
docker compose restart app           # restart one container
docker compose exec app php artisan <command>   # artisan inside Docker
```

### E. Troubleshooting

| Symptom | Fix |
|---|---|
| `could not find composer.json` / `package.json` | You're in the wrong folder: `cd C:\laragon\www\mire` |
| `php -v` shows 8.2.12 | Terminal was opened before the PATH change: open a new one, or in PowerShell: `$env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')` |
| `npm: command not found` | Use Laragon's Terminal (Node isn't on your PATH) |
| `cd C:\...` fails in Git Bash | Use `/c/Users/...` (Git Bash prompt is `$`) |
| mire.test shows the Laragon page | Apache didn't restart: Task Manager → end all `httpd.exe` → Laragon Start All |
| Site looks unstyled on phone | Run `npm run build`; make sure `public/hot` doesn't exist |
| Phone can't connect | Wi-Fi → set "kuyakambal 2" to **Private**; check your IP with `ipconfig` |
| `failed to connect to the docker API` | Docker Desktop isn't running: open it and wait for "Engine running" |
| Docker Desktop window won't open | Task Manager → end `Docker Desktop.exe` and `com.docker.backend.exe` → reopen → `docker compose up -d` |
| Weird config or cached behaviour | `php artisan optimize:clear` (and never run `config:cache` locally) |

## 5. Terminal guide

| Prompt | Terminal | Path style | npm? |
|---|---|---|---|
| `λ` | Laragon (Cmder) | `C:\Users\...` | ✅ |
| `PS C:\...>` | PowerShell / VS Code | `C:\Users\...` | ❌ |
| `$` | Git Bash | `/c/Users/...` | ❌ |

## 6. Still to do (optional)
- [ ] Set your Wi-Fi to **Private** if your phone can't connect.
- [ ] Add Node to your PATH, so `npm` works in VS Code.
- [ ] Add `restart: unless-stopped` to `docker-compose.yml`, so containers come back after a Docker crash.
- [ ] Commit the `docker-compose.yml` change to git.
- [ ] Before going public: `APP_DEBUG=false`, strong DB passwords, TMDB credit, deploy with `docker-compose.prod.yml`.
- [ ] Turn off Cmder transparency (your `.env` keys show through in screenshots).
