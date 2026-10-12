{{--
    Lists page (route /lists, PageController::lists). One box per TMDB genre (FilmCatalog::genreShelves()),
    6 films shown + "See more / See less" (Bootstrap collapse). Styles: .genre-box / .see-toggle / .chip-rail in app.scss
--}}
@extends('layouts.app')
@section('title', 'Lists · Miré Film')

@section('content')
@php $perRow = 6; // films shown before "See more" @endphp

<h1 class="display-6 fw-light mb-3">Lists</h1>

<nav class="chip-rail" aria-label="Jump to genre">                                                  {{-- ← genre jump buttons (swipe on phones) --}}
    @foreach ($shelves as $shelf)
        <a href="#genre-{{ $shelf['slug'] }}" class="btn btn-sm btn-outline-light rounded-pill">{{ $shelf['name'] }}</a>
    @endforeach
</nav>

@foreach ($shelves as $shelf)
    @php
        $id = 'genre-'.$shelf['slug'];
        $first = array_slice($shelf['films'], 0, $perRow);                                          // ← shown
        $rest = array_slice($shelf['films'], $perRow);                                              // ← behind "See more"
    @endphp
    <section class="mire-section" id="{{ $id }}" style="scroll-margin-top: 6rem">
        <div class="genre-box">                                                                     {{-- ← the box around each genre --}}
            <x-section-heading variant="caps">
                {{ $shelf['name'] }}
                <x-slot:action>{{ count($shelf['films']) }} {{ Str::plural('film', count($shelf['films'])) }}</x-slot:action>
            </x-section-heading>

            <div class="row row-cols-3 row-cols-sm-4 row-cols-md-6 g-3">
                @foreach ($first as $film)                                                          {{-- ← first 6 films, always visible --}}
                    <div class="col"><x-poster :film="$film" /></div>
                @endforeach
            </div>

            @if ($rest)
                <div class="collapse" id="{{ $id }}-more">                                          {{-- ← the rest, hidden until "See more" --}}
                    <div class="row row-cols-3 row-cols-sm-4 row-cols-md-6 g-3 pt-3">
                        @foreach ($rest as $film)
                            <div class="col"><x-poster :film="$film" /></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if ($rest)
            <div class="see-toggle">                                                                {{-- ← button sits OUTSIDE the box --}}
                <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse"
                        data-bs-target="#{{ $id }}-more" aria-expanded="false" aria-controls="{{ $id }}-more">  {{-- ← opens/closes the matching collapse (Bootstrap) --}}
                    <span class="more">See more ▾</span><span class="less">See less ▴</span>
                </button>
            </div>
        @endif
    </section>
@endforeach
@endsection
