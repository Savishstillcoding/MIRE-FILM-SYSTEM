{{--
    One review: $review from Review::toCard() or FilmCatalog (TMDB). $showFilm = false hides the poster/title
    (used on the film's own page). Members' reviews get an instant like button; TMDB ones link back to TMDB.
    Used by: films.blade.php, film-show.blade.php, profile.blade.php. Buttons → ReviewController (like / destroy).
    Styles: .review-like / .review-body in app.scss
--}}
@php $showFilm ??= true; @endphp
<article class="mire-card p-3 p-md-4 d-flex gap-3 gap-md-4">
    @if ($showFilm)
        <x-poster :film="$review['film']" style="width: clamp(4.5rem, 12vw, 7rem); flex: none" />
    @endif
    <div class="min-w-0 flex-grow-1">
        @if ($showFilm)
            <h3 class="fs-4 mb-2">
                <a href="{{ route('films.show', $review['film']['slug']) }}" class="link-light">{{ $review['film']['title'] }}</a>
                @if (! empty($review['film']['years']))<span class="text-mire-muted ms-1">{{ $review['film']['years'][0] }}</span>@endif
            </h3>
        @endif

        <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 small">
            <x-avatar size="32" />
            <span class="fw-semibold">{{ $review['author'] }}</span>
            @if ($review['rating'])
                <span class="stars-static fs-5" role="img" aria-label="{{ $review['rating'] }} out of 5 stars">{{ str_repeat('★', $review['rating']) }}</span>
            @endif
            @if ($review['created_at'])
                <span class="text-mire-muted" title="{{ $review['created_at'] }}">{{ $review['created_at']->gt(now()->subMinute()) ? 'just now' : $review['created_at']->diffForHumans() }}</span>
            @endif

            @if ($review['source'] === 'user')
                <form method="POST" action="{{ route('reviews.like', $review['id']) }}" data-instant="liked" class="ms-auto">  {{-- ← sends to ReviewController::like (no reload) --}}
                    @csrf
                    <button type="submit" @class(['review-like', 'on' => $review['liked']]) aria-pressed="{{ $review['liked'] ? 'true' : 'false' }}"
                            aria-label="Like this review">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.8-8 11-8 11z"/></svg>
                        <span data-count>{{ number_format($review['likes']) }}</span> <span>{{ Str::plural('like', $review['likes']) }}</span>
                    </button>
                </form>
            @else
                <span class="badge rounded-pill border border-light-subtle fw-normal ms-auto">From TMDB</span>  {{-- ← TMDB reviews can't be liked --}}
            @endif
        </div>

        <p class="review-body mt-3 mb-0">{{ $review['body'] }}</p>

        @if ($review['url'])
            <a href="{{ $review['url'] }}" class="small d-inline-block mt-2" target="_blank" rel="noopener">Read the full review on TMDB</a>
        @endif
        @if ($review['mine'])
            <form method="POST" action="{{ route('reviews.destroy', $review['id']) }}" class="d-inline-block mt-2" onsubmit="return confirm('Delete your review?')">  {{-- ← only on your own reviews ($review['mine']) --}}
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link btn-sm p-0 link-danger">Delete my review</button>
            </form>
        @endif
    </div>
</article>
