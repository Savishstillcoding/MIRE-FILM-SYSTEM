{{--
    NEW: Trailers page (route /trailers, PageController::trailers → FilmCatalog::trailerShelves()).
    Rows: Coming soon / Trending / Popular. Chips at the top jump to a row. Cards: partials/trailer-card.
--}}
@extends('layouts.app')
@section('title', 'Trailers · Miré Film')

@section('content')
<h1 class="display-5 mb-3">Trailers</h1>

@if ($shelves)
    <nav class="chip-rail mb-2" aria-label="Trailer categories">                                    {{-- ← jump to a row (same chips as the Lists page) --}}
        @foreach ($shelves as $shelf)
            <a href="#{{ $shelf['slug'] }}" class="btn btn-outline-light rounded-pill">{{ $shelf['name'] }}</a>
        @endforeach
    </nav>

    @foreach ($shelves as $shelf)
        <section @class(['mire-section' => ! $loop->first, 'mt-4' => $loop->first]) id="{{ $shelf['slug'] }}" style="scroll-margin-top: 6rem">
            <x-section-heading variant="caps">
                {{ $shelf['name'] }}
                <x-slot:action>{{ count($shelf['films']) }} {{ Str::plural('trailer', count($shelf['films'])) }}</x-slot:action>
            </x-section-heading>
            <div class="trailer-rail">                                                              {{-- ← sideways scroll on phones (app.scss .trailer-rail) --}}
                @foreach ($shelf['films'] as $film)
                    @include('partials.trailer-card', ['film' => $film, 'showRelease' => $shelf['slug'] === 'coming-soon'])
                @endforeach
            </div>
        </section>
    @endforeach
@else
    <div class="mire-card p-5 text-center">
        <p class="fs-5 fw-light mb-0">No trailers right now. They come from TMDB, so check your internet connection or TMDB token and try again.</p>
    </div>
@endif
@endsection
