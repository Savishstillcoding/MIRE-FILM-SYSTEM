{{--
    The shell around EVERY page: <head>, fonts, header, success toast, footer, and the sign-in / join pop-ups for guests.
    Pages plug in with @extends('layouts.app') + @section('content').
    Styles/JS:  resources/scss/app.scss + resources/js/app.js (loaded by @vite, built with `npm run build`)
    Includes:   partials/header, partials/join-modal, partials/signin-modal; pages can @push('modals') extra pop-ups
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Miré Film')</title>                                                     {{-- ← each page sets its own with @section('title') --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@300;400;500;600;700&family=Michroma&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])                                       {{-- ← styles + JS from public/build (npm run build) --}}
</head>
<body class="d-flex flex-column" @auth data-live @endauth>  {{-- ← signed in: Back button reloads (re-checks login), see app.js "pageshow" --}}
    <a href="#main" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light z-3">Skip to content</a>

    @include('partials.header')                                                                     {{-- ← top navigation bar --}}

    @if (session('status'))
        <div class="toast-container position-fixed top-0 end-0 p-3">                                {{-- ← green pop-up for session("status") messages --}}
            <div class="toast align-items-center text-bg-primary border-0" role="status" aria-live="polite" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">{{ session('status') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    @endif

    <main id="main" class="container-xl flex-grow-1 pt-4 pt-lg-5 pb-5">
        @yield('content')                                                                           {{-- ← the page's own content goes here --}}
    </main>

    <footer class="border-top py-4 text-center small text-mire-muted">
        © {{ date('Y') }} <span class="font-display">MIRÉ</span> Film
        <p class="mt-2 mb-0 opacity-75">
            Film data and images from <a href="https://www.themoviedb.org" class="link-light" target="_blank" rel="noopener">TMDB</a>.
            This product uses the TMDB API but is not endorsed or certified by TMDB.
        </p>
    </footer>

    @guest                                                                                          {{-- ← pop-ups only exist for people not signed in --}}
        @include('partials.join-modal')
        @include('partials.signin-modal')
    @else
        @include('partials.trailer-modal')                                                          {{-- ← NEW: pop-up trailer player for members --}}
    @endguest

    @stack('modals')                                                                                {{-- ← pages can add pop-ups here (settings uses it) --}}
</body>
</html>
