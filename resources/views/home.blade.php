{{--
    Member home (route /, PageController::home → homeRows()). Random picks on every visit:
    $recent + $featured (trending), $favorites (acclaimed), $rewatch (classics). Uses <x-poster>.
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

@if ($featured)                                                                                     {{-- ← "Featured" banner with the film's backdrop --}}
    <a href="{{ route('films.show', $featured['slug']) }}" class="mire-banner mt-3 mx-auto"
       style="max-width: 56rem; background: linear-gradient(90deg, rgba(0,0,0,.75), rgba(0,0,0,.1)), url('{{ $featured['backdrop'] }}') center / cover;">
        <span><small class="d-block text-uppercase fs-6 fw-semibold opacity-75">Featured</small>{{ $featured['title'] }}</span>
    </a>
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
