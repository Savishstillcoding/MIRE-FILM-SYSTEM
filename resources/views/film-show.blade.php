{{--
    One film (route /films/{slug}, PageController::show). Details from FilmCatalog::find(),
    Like/Watchlist/stars → FilmActionController (instant via app.js "data-instant"), review form → ReviewController.
--}}
@extends('layouts.app')
@section('title', $film['title'].' · Miré Film')

@section('content')
@if (! empty($film['backdrop']))
    {{-- Faded backdrop behind the top of the page. --}}
    <div class="film-backdrop" style="background-image: url('{{ $film['backdrop'] }}')" aria-hidden="true"></div>  {{-- ← faded TMDB backdrop behind the top (.film-backdrop in app.scss) --}}
@endif

<div class="row g-4 g-lg-5 position-relative">
    <div class="col-7 col-sm-5 col-md-4 col-lg-3 mx-auto mx-md-0">
        <x-poster :film="$film" :href="false" large />                                              {{-- ← components/poster.blade.php; large = sharper image --}}
    </div>

    <div class="col-md-8 col-lg-6">
        <h1 class="display-5 fw-semibold mb-1">{{ $film['title'] }}</h1>
        <p class="d-flex flex-wrap align-items-center gap-2 gap-md-3 text-mire-muted fs-5 mb-3">
            @if ($film['years'])<span>{{ implode(' – ', $film['years']) }}</span>@endif
            @if (! empty($film['runtime']))<span>· {{ intdiv($film['runtime'], 60) ? intdiv($film['runtime'], 60).'h ' : '' }}{{ $film['runtime'] % 60 }}m</span>@endif
            @if (! empty($film['age_rating']))
                <span class="badge rounded-pill border border-light-subtle fw-semibold fs-6" title="MTRCB rating">{{ $film['age_rating'] }}</span>  {{-- ← NEW: admin movies (G, PG, R-13…) --}}
            @endif
            @if (! empty($film['score']))
                <span class="badge rounded-pill text-bg-warning fs-6" title="TMDB user score">★ {{ number_format($film['score'], 1) }}</span>  {{-- ← TMDB score out of 10 --}}
            @endif
        </p>

        @if (! empty($film['genres']))
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($film['genres'] as $genre)                                                {{-- ← genre pills --}}
                    <span class="badge rounded-pill border border-light-subtle fw-normal">{{ $genre }}</span>
                @endforeach
            </div>
        @endif

        @if (! empty($film['tagline']))
            <p class="fst-italic text-mire-muted mb-2">“{{ $film['tagline'] }}”</p>                 {{-- ← the film's tagline from TMDB --}}
        @endif
        <p class="fs-5 fw-light" style="max-width: 50ch">{{ $film['description'] ?: 'No description yet.' }}</p>

        @if (! empty($film['trailer']))                                                             {{-- ← NEW: only when TMDB has a YouTube trailer --}}
            <a href="https://www.youtube.com/watch?v={{ $film['trailer']['key'] }}" target="_blank" rel="noopener"
               class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2"
               data-trailer="{{ $film['trailer']['key'] }}" data-trailer-title="{{ $film['title'] }} — {{ $film['trailer']['name'] }}">  {{-- ← opens the pop-up player (app.js) --}}
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                Watch trailer
            </a>
        @endif

        @if ($film['seasons'] || $film['status'] || ! empty($film['director']) || ! empty($film['cast']))
            <dl class="row mt-4 mb-0" style="max-width: 36rem">
                @if (! empty($film['director']))
                    <dt class="col-4 col-sm-3 fw-semibold">{{ ($film['type'] ?? '') === 'tv' ? 'Created by' : 'Director' }}</dt><dd class="col-8 col-sm-9">{{ $film['director'] }}</dd>
                @endif
                @if (! empty($film['cast']))                                                        {{-- ← top 8 actors --}}
                    <dt class="col-4 col-sm-3 fw-semibold">Cast</dt><dd class="col-8 col-sm-9">{{ implode(', ', $film['cast']) }}</dd>
                @endif
                @if ($film['seasons'])
                    <dt class="col-4 col-sm-3 fw-semibold">Seasons</dt><dd class="col-8 col-sm-9">{{ $film['seasons'] }}</dd>
                @endif
                @if ($film['status'])
                    <dt class="col-4 col-sm-3 fw-semibold">Status</dt><dd class="col-8 col-sm-9"><span class="badge text-bg-success">{{ $film['status'] }}</span></dd>
                @endif
            </dl>
        @endif
    </div>

    <aside class="col-md-8 offset-md-4 col-lg-3 offset-lg-0" aria-label="Film actions">
        <div class="mire-card film-actions overflow-hidden">
            {{-- data-instant: app.js submits these with fetch and updates them in place (plain POST still works without JS). --}}
            <div class="d-flex border-bottom">
                <form method="POST" action="{{ route('films.watch', $film['slug']) }}" class="w-50" data-instant="watchlisted">  {{-- ← sends to FilmActionController::watch --}}
                    @csrf
                    <button type="submit" @class(['action-btn', 'on' => $inWatchlist]) aria-pressed="{{ $inWatchlist ? 'true' : 'false' }}"
                            data-label-on="On watchlist" data-label-off="Watchlist">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 5C6 5 2 12 2 12s4 7 10 7 10-7 10-7-4-7-10-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/></svg>
                        <span class="small label">{{ $inWatchlist ? 'On watchlist' : 'Watchlist' }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('films.like', $film['slug']) }}" class="w-50 border-start" data-instant="liked">  {{-- ← sends to FilmActionController::like --}}
                    @csrf
                    <button type="submit" @class(['action-btn like', 'on' => $liked]) aria-pressed="{{ $liked ? 'true' : 'false' }}"
                            data-label-on="Liked" data-label-off="Like">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.8-8 11-8 11z"/></svg>
                        <span class="small label">{{ $liked ? 'Liked' : 'Like' }}</span>
                    </button>
                </form>
            </div>

            <div class="text-center py-3 border-bottom">
                <p class="mb-1 text-mire-muted small text-uppercase fw-semibold" data-rate-label>{{ $rating ? 'Your rating' : 'Rate' }}</p>
                <form method="POST" action="{{ route('films.rate', $film['slug']) }}" class="star-rating" data-instant="rating">  {{-- ← sends to FilmActionController::rate --}}
                    @csrf
                    {{-- Reversed order + row-reverse so hovering a star lights it and every star before it. --}}
                    @for ($i = 5; $i >= 1; $i--)                                                    {{-- ← 5→1 so hover lights the stars to the left (CSS trick) --}}
                        <button type="submit" name="rating" value="{{ $i }}" @class(['on' => $i <= $rating])
                                aria-label="Rate {{ $i }} out of 5{{ $i === $rating ? ' (click again to clear)' : '' }}">★</button>
                    @endfor
                </form>
            </div>

            <div class="list-group list-group-flush">
                <a href="#write-review" class="list-group-item list-group-item-action py-3">{{ $myReview ? 'Edit your review' : 'Write a review' }}</a>  {{-- ← jumps down to the review form --}}
                <button type="button" class="list-group-item list-group-item-action py-3" disabled title="Coming soon">Add film to lists</button>  {{-- ← not built yet --}}
                <button type="button" class="list-group-item list-group-item-action py-3" data-share="{{ route('films.show', $film['slug']) }}">Share</button>  {{-- ← copies the link (app.js "data-share") --}}
            </div>
        </div>
    </aside>
</div>

<section class="mire-section" id="reviews" style="scroll-margin-top: 6rem">
    <x-section-heading>
        Reviews
        <x-slot:action>{{ $reviewsFromTmdb ? 'From TMDB' : count($reviews).' '.Str::plural('review', count($reviews)) }}</x-slot:action>
    </x-section-heading>

    <form method="POST" action="{{ route('reviews.store', $film['slug']) }}" id="write-review" class="mire-card p-3 p-md-4 mb-3" style="scroll-margin-top: 6rem">  {{-- ← sends to ReviewController::store --}}
        @csrf
        <label for="review-body" class="form-label fw-semibold">{{ $myReview ? 'Edit your review' : 'Write a review' }}</label>
        <textarea id="review-body" name="body" rows="3" maxlength="2000" required
                  @class(['form-control', 'is-invalid' => $errors->has('body')])
                  placeholder="What did you think of {{ $film['title'] }}?">{{ old('body', $myReview) }}</textarea>  {{-- ← your existing review when editing --}}
        @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
            <span class="small text-mire-muted">
                {{ $rating ? "Your {$rating}★ rating will show with it." : 'Tip: rate the film above and your stars show with your review.' }}
            </span>
            <button type="submit" class="btn btn-primary">{{ $myReview ? 'Update review' : 'Post review' }}</button>
        </div>
    </form>

    @if ($reviewsFromTmdb)                                                                          {{-- ← nobody here reviewed it yet → TMDB reviews shown --}}
        <p class="small text-mire-muted">No member reviews yet. Here’s what people on TMDB said:</p>
    @elseif (! $reviews)
        <p class="small text-mire-muted">No reviews yet. Be the first!</p>
    @endif
    <div class="vstack gap-3">
        @foreach ($reviews as $review)
            @include('partials.review-card', ['review' => $review, 'showFilm' => false])            {{-- ← partials/review-card.blade.php --}}
        @endforeach
    </div>
</section>

@php $similar = $film['similar'] ?: collect(\App\Support\FilmCatalog::trending(9))->where('slug', '!=', $film['slug'])->take(8)->all(); @endphp  {{-- ← TMDB recommendations, else trending --}}
@if ($similar)
    <section class="mire-section">
        <x-section-heading>{{ $film['similar'] ? 'You might also like' : 'Trending this week' }}</x-section-heading>
        <div class="poster-rail">
            @foreach ($similar as $item)
                <x-poster :film="$item" />
            @endforeach
        </div>
    </section>
@endif
@endsection
