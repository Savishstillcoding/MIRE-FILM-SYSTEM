{{--
    Member home (route /, PageController::home → homeRows()). Random picks on every visit:
    $recent + $featured (2 trending), $favorites (acclaimed), $rewatch (classics). Uses <x-poster>.
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
