{{--
    Profile (route /profile, PageController::profile). Stats, recent likes, watchlist and "Your reviews"
    from the database. "See all" links → profile-collection.blade.php. Reloads on Back via data-live (app.js).
--}}
@extends('layouts.app')
@section('title', 'Profile · Miré Film')

@section('content')
@php
    $username = auth()->user()->name;
    // Followers/Following are still sample numbers; Rated, Likes and Watchlist are real.
    $stats = ['Followers' => 6, 'Following' => 7, 'Rated' => $ratedCount, 'Likes' => $likesCount, 'Watchlist' => $watchlistCount];  // ← counts come from PageController::profile
    $favorites = [];
    $slots = 5; // posters per row on desktop (3 on phones); empty slots show as placeholders
@endphp

<div data-live>{{-- app.js reloads this page when it comes back from the browser's back/forward cache --}}

<section class="mire-card p-4 p-md-5 d-flex flex-column flex-lg-row align-items-center gap-4 gap-lg-5 text-center text-lg-start">  {{-- ← stacked on phones/tablets, side by side on laptops --}}
    <x-avatar size="160" class="shadow" />
    <div class="flex-grow-1">
        <h1 class="display-5 fw-bold mb-1">{{ $username }}</h1>
        <p class="text-mire-muted mb-0">Member since {{ auth()->user()->created_at?->format('F Y') }}</p>
    </div>
    <ul class="profile-stats list-unstyled d-flex flex-wrap justify-content-center row-gap-3 mb-0 text-center">  {{-- ← stats bar --}}
        @foreach ($stats as $label => $count)
            <li class="stat px-3 px-md-4">
                <span class="value d-block">{{ $count }}</span>
                <span class="text-mire-muted">{{ $label }}</span>
            </li>
        @endforeach
    </ul>
</section>

<section class="mire-section">
    <x-section-heading>Favorite films</x-section-heading>
    @if ($favorites)
        <div class="row row-cols-3 row-cols-md-5 g-3 g-md-4">
            @foreach ($favorites as $title)
                <div class="col"><x-poster :title="$title" /></div>
            @endforeach
        </div>
    @else
        <p class="fs-5 fw-light text-mire-muted">Don’t forget to select your favorite films!</p>
    @endif
</section>

@foreach (['Recent likes' => ['items' => $likes, 'count' => $likesCount, 'type' => 'likes', 'icon' => 'heart', 'empty' => 'Tap the heart on any film to like it.'],
           'Watchlist' => ['items' => $watchlist, 'count' => $watchlistCount, 'type' => 'watchlist', 'icon' => 'eye', 'empty' => 'Use the watchlist button on any film to save it here.']] as $heading => $group)
    <section class="mire-section">
        <x-section-heading>
            {{ $heading }}
            <x-slot:action>
                <a href="{{ route('profile.collection', $group['type']) }}" class="d-inline-flex align-items-center gap-2">  {{-- ← "See all" → profile-collection.blade.php --}}
                    @if ($group['icon'] === 'heart')
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.8-8 11-8 11z"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    @endif
                    See all ({{ $group['count'] }})
                </a>
            </x-slot:action>
        </x-section-heading>
        <div class="row row-cols-3 row-cols-md-5 g-3 g-md-4">
            @foreach (array_pad(array_slice($group['items'], 0, $slots), $slots, null) as $item)    {{-- ← pads with empty dashed boxes --}}
                <div @class(['col', 'd-none d-md-block' => $loop->index >= 3])>                     {{-- ← phones show 3, desktop 5 --}}
                    @if ($item)
                        <x-poster :film="$item" />
                    @else
                        <div class="poster-placeholder" aria-hidden="true"></div>
                    @endif
                </div>
            @endforeach
        </div>
        @unless ($group['items'])
            <p class="small text-mire-muted mt-2 mb-0">{{ $group['empty'] }}</p>
        @endunless
    </section>
@endforeach

<section class="mire-section">
    <x-section-heading>
        Your reviews
        <x-slot:action>{{ $reviewsCount }} {{ Str::plural('review', $reviewsCount) }}</x-slot:action>
    </x-section-heading>
    @forelse ($myReviews as $review)                                                                {{-- ← "Your reviews" (latest 3) --}}
        <div class="mb-3">@include('partials.review-card', ['review' => $review])</div>
    @empty
        <p class="small text-mire-muted">Reviews you write on film pages will show up here.</p>
    @endforelse
</section>
</div>
@endsection
