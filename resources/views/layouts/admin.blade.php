{{--
    The shell around every ADMIN page: sidebar (Dashboard / Movies / Users), top bar (search + account menu), toasts.
    Pages plug in with @extends('layouts.admin') + @section('content'). Styles: .admin-* in resources/scss/app.scss
    Routes: routes/web.php (admin group, admins only)
--}}
@php
    $nav = [
        ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => '<path d="M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z"/>'],
        ['route' => 'admin.movies.index', 'match' => 'admin.movies.*', 'label' => 'Movies', 'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4M3 15h4M17 9h4M17 15h4"/>'],
        ['route' => 'admin.users.index', 'match' => 'admin.users.*', 'label' => 'Users', 'icon' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M16 4a4 4 0 0 1 0 8M22 21c0-3-2-5-4-5.6"/>'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · Miré Film</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@300;400;500;600;700&family=Michroma&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="admin-body" data-live>
    <a href="#main" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light z-3">Skip to content</a>

    <div class="admin-shell">
        <aside class="admin-sidebar" aria-label="Admin">                                            {{-- ← left menu (becomes a top row on phones) --}}
            <a href="{{ route('admin.dashboard') }}" class="admin-brand">
                <span class="font-display">MIRÉ</span> <span class="admin-tag">ADMIN</span>
            </a>
            <nav>
                @foreach ($nav as $item)
                    @php $active = request()->routeIs($item['match']); @endphp
                    <a href="{{ route($item['route']) }}" @class(['admin-nav-link', 'active' => $active]) @if ($active) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $item['icon'] !!}</svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <a href="{{ route('home') }}" class="admin-nav-link back mt-lg-auto">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                Back to site
            </a>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">                                                           {{-- ← search movies + account menu --}}
                <form class="mire-search position-relative flex-grow-1" role="search" action="{{ route('admin.movies.index') }}" method="GET">
                    <label for="admin-search" class="visually-hidden">Search movies</label>
                    <input id="admin-search" name="q" type="search" class="form-control" placeholder="Search movies…" value="{{ request()->routeIs('admin.movies.index') ? request('q') : '' }}">
                    <button type="submit" class="btn btn-link" aria-label="Search">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </button>
                </form>
                <div class="dropdown">
                    <button class="btn d-flex align-items-center gap-2 px-0 border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <x-avatar size="40" />
                        <span class="fw-semibold d-none d-sm-inline">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="{{ route('home') }}">Back to site</a></li>
                        <li><a class="dropdown-item" href="{{ route('profile') }}">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            @if (session('status'))
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast align-items-center text-bg-primary border-0" role="status" aria-live="polite" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">{{ session('status') }}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            @endif

            <main id="main" class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('modals')
</body>
</html>
