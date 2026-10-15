{{--
    Member home (route /, PageController::home → homeRows()). Random picks on every visit:
    $trailer (NEW: featured trailer banner), $recent + $featured (2 trending), $favorites (acclaimed), $rewatch (classics). Uses <x-poster>.
--}}
@extends('layouts.app')
@section('title', 'Home · Miré Film')

@section('content')
{{-- Every row is a random pick from TMDB on each visit (see PageController::homeRows). --}}
<section class="text-center">
    <h1 class="fw-light fst-italic display-6 mx-auto" style="max-width: 46rem">
        Welcome back, <a href="{{ route('profile') }}" class="link-light link-underline-opacity-50 text-decoration-underline">{{ auth()->user()->name }}</a>.
        Here’s what we’ve been watching.
    </h1>
</section>

@if ($trailer)                                                                                      {{-- ← NEW: "Featured trailer" banner (PageController::homeRows → FilmCatalog::firstWithTrailer) --}}
    <section class="trailer-feature mt-4 mx-auto" style="max-width: 56rem; background-image: linear-gradient(90deg, rgba(0,0,0,.85), rgba(0,0,0,.15)), url('{{ $trailer['backdrop'] }}')">
        <small class="text-uppercase fw-semibold opacity-75">Featured trailer</small>
        <h2 class="display-5 mb-3">{{ $trailer['title'] }}</h2>
        <a href="https://www.youtube.com/watch?v={{ $trailer['trailer']['key'] }}" target="_blank" rel="noopener"
           class="btn btn-light btn-lg d-inline-flex align-items-center gap-2"
           data-trailer="{{ $trailer['trailer']['key'] }}" data-trailer-title="{{ $trailer['title'] }} — {{ $trailer['trailer']['name'] }}"
           data-film-url="{{ route('films.show', $trailer['slug']) }}">                             {{-- ← pop-up player; "View film page" inside it --}}
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
            Play trailer
        </a>
    </section>
@endif

<div class="poster-rail mt-4 mx-auto" style="max-width: 56rem">                                     {{-- ← top row: 5 random trending (PageController::homeRows) --}}
    @foreach ($recent as $film)
        <x-poster :film="$film" />
    @endforeach
</div>

@if ($featured)                                                                                     {{-- ← 2 "Featured" banners side by side (stacked on phones) --}}
    <div class="featured-pair mt-3 mx-auto" style="max-width: 56rem">                               {{-- ← grid in app.scss .featured-pair --}}
        @foreach ($featured as $film)                                                               {{-- ← 2 random trending films with a backdrop (PageController::homeRows) --}}
            <a href="{{ route('films.show', $film['slug']) }}" class="mire-banner"                  {{-- ← whole banner opens the film page --}}
               style="background: linear-gradient(90deg, rgba(0,0,0,.75), rgba(0,0,0,.1)), url('{{ $film['backdrop'] }}') center / cover;">
                <span><small class="d-block text-uppercase fs-6 fw-semibold opacity-75">Featured</small>{{ $film['title'] }}</span>
            </a>
        @endforeach
    </div>
@endif

@if ($favorites)
    <section class="mire-section">
        <x-section-heading>Favorite of all time</x-section-heading>
        <div class="poster-rail fill">
            @foreach ($favorites as $film)                                                          {{-- ← "Favorite of all time": 6 random acclaimed films --}}
                <x-poster :film="$film" />
            @endforeach
        </div>
    </section>
@endif

@if ($rewatch)
    <section class="mire-section">
        <x-section-heading>Worth rewatching</x-section-heading>
        <div class="poster-rail fill">
            @foreach ($rewatch as $film)                                                            {{-- ← "Worth rewatching": 6 random 10+ year-old films --}}
                <x-poster :film="$film" />
            @endforeach
        </div>
    </section>
@endif
@endsection
