{{--
    Films page (route /films, PageController::films). Search results (?q=), trending row,
    "Just reviewed" (still sample titles) and "Popular reviews this week" (partials/review-card).
--}}
@extends('layouts.app')
@section('title', 'Films · Miré Film')

@section('content')
@php
    // Sample data: replace with values from a controller.
    $reviewed = ['Guardians of the Galaxy', 'Legally Blonde', 'Coraline', 'Five Nights at Freddy’s', 'The Devil Wears Prada', 'Mamma Mia!'];

    // One batched TMDB lookup for every sample title on the page.
    $found = array_combine($reviewed, \App\Support\FilmCatalog::byTitles($reviewed));               // ← real posters for the sample titles
@endphp

<h1 class="visually-hidden">Films</h1>

@isset($results)                                                                                    {{-- ← only after a search (?q=) --}}
    <section class="mb-5">
        <x-section-heading variant="caps">
            Results for “{{ $q }}”
            <x-slot:action>{{ count($results) }} found</x-slot:action>
        </x-section-heading>
        @if ($results)
            <div class="row row-cols-3 row-cols-sm-4 row-cols-md-5 row-cols-lg-6 g-3 g-md-4">
                @foreach ($results as $film)                                                        {{-- ← FilmCatalog::search() results --}}
                    <div class="col"><x-poster :film="$film" /></div>
                @endforeach
            </div>
        @else
            <p class="text-mire-muted fst-italic">No films match that search.</p>
        @endif
    </section>
@endisset

<section>
    <x-section-heading variant="caps">Trending this week</x-section-heading>
    <div class="poster-rail">
        @foreach ($trending as $film)                                                               {{-- ← "Trending this week" from TMDB --}}
            <x-poster :film="$film" />
        @endforeach
    </div>
</section>

<section class="mire-section">
    <x-section-heading variant="caps">Just reviewed</x-section-heading>
    <div class="poster-rail">
        @foreach ($reviewed as $title)                                                              {{-- ← still sample titles (see @php above) --}}
            <x-poster :film="$found[$title]" />
        @endforeach
    </div>
</section>

<section class="mire-section">
    <x-section-heading variant="caps">
        Popular reviews this week
        @if ($reviewsFromTmdb && $reviews)
            <x-slot:action>Recent reviews from TMDB</x-slot:action>
        @endif
    </x-section-heading>
    @if ($reviewsFromTmdb)                                                                          {{-- ← no member reviews this week → TMDB backup --}}
        <p class="small text-mire-muted">No member reviews this week yet. Open any film and write the first one!</p>
    @endif
    <div class="vstack gap-3">
        @foreach ($reviews as $review)
            @include('partials.review-card', ['review' => $review])                                 {{-- ← partials/review-card.blade.php --}}
        @endforeach
    </div>
</section>
@endsection
