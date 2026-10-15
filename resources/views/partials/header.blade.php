{{--
    Top navigation bar on every page (included by layouts/app.blade.php).
    Guests: FILMS/LISTS and search open the sign-in pop-up (partials/signin-modal); members get links + account menu.
    Logo: partials/logo-mark.blade.php (image) + "MIRÉ / FILM" text. Styles: .mire-navbar / .mire-logo in app.scss
--}}
<nav class="navbar navbar-expand-lg mire-navbar sticky-top" aria-label="Main">
    <div class="container-xl gap-3">
        <a class="mire-logo navbar-brand me-0" href="{{ route('home') }}" aria-label="Miré Film home">
            @include('partials.logo-mark')                                                          {{-- ← camera image (public/images/logo-mark.png) --}}
            <span class="wordmark">
                <span class="word">MIRÉ</span>
                <span class="film" aria-hidden="true"><span>F</span><span>I</span><span>L</span><span>M</span></span>
            </span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse gap-3" id="main-nav">
            <ul class="navbar-nav mx-lg-auto gap-lg-3">
                @auth
                    <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('films*')]) href="{{ route('films') }}" @if (request()->routeIs('films*')) aria-current="page" @endif>FILMS</a></li>
                    <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('lists')]) href="{{ route('lists') }}" @if (request()->routeIs('lists')) aria-current="page" @endif>LISTS</a></li>
                    <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('trailers')]) href="{{ route('trailers') }}" @if (request()->routeIs('trailers')) aria-current="page" @endif>TRAILERS</a></li>  {{-- ← NEW --}}
                @else
                    <li class="nav-item"><a class="nav-link" href="#signin" data-bs-toggle="modal" data-bs-target="#signin">FILMS</a></li>
                    <li class="nav-item"><a class="nav-link" href="#signin" data-bs-toggle="modal" data-bs-target="#signin">LISTS</a></li>
                    <li class="nav-item"><a class="nav-link" href="#signin" data-bs-toggle="modal" data-bs-target="#signin">TRAILERS</a></li>  {{-- ← NEW: guests get the sign-in box --}}
                @endauth
            </ul>

            <form class="mire-search position-relative my-3 my-lg-0 flex-lg-grow-0" role="search"
                  @auth action="{{ route('films') }}" method="GET" @else data-gate @endauth>        {{-- ← members search /films?q=…; guests get the sign-in pop-up --}}
                <label for="nav-search" class="visually-hidden">Search films</label>
                <input id="nav-search" name="q" type="search" class="form-control" placeholder="Search films…" value="{{ request('q') }}">
                <button type="submit" class="btn btn-link" aria-label="Search">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </button>
            </form>

            @auth
                <div class="dropdown">
                    <button class="btn d-flex align-items-center gap-2 px-0 border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <x-avatar size="40" />
                        <span class="fw-semibold">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">                             {{-- ← account menu under your username --}}
                        <li><a class="dropdown-item" href="{{ route('home') }}">Home</a></li>
                        <li><a class="dropdown-item" href="{{ route('profile') }}">Profile</a></li>
                        <li><a class="dropdown-item" href="{{ route('settings') }}">Settings</a></li>
                        @can('admin')                                                               {{-- ← only admins (Gate in AppServiceProvider) --}}
                            <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin dashboard</a></li>
                        @endcan
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">                     {{-- ← → AuthController::logout --}}
                                @csrf
                                <button type="submit" class="dropdown-item">Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#signin">Sign in</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#join">Create account</button>
                </div>
            @endauth
        </div>
    </div>
</nav>
