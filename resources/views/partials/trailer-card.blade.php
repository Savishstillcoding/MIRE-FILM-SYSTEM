{{--
    NEW: one trailer in a row on the Trailers page (trailers.blade.php). 16:9 thumbnail + ▶, title, trailer name.
    Click → pop-up player (partials/trailer-modal, app.js "data-trailer"). Without JavaScript it opens YouTube instead.
    $film = a FilmCatalog film with 'trailer' => ['key', 'name', 'type']; $showRelease = add "Releases Nov 21".
--}}
@php
    $key = $film['trailer']['key'];
    $thumb = ! empty($film['backdrop'])
        ? str_replace('/t/p/w1280/', '/t/p/w780/', $film['backdrop'])                               // ← TMDB backdrop (smaller file for a card)
        : "https://i.ytimg.com/vi/{$key}/mqdefault.jpg";                                            // ← else YouTube's own thumbnail
@endphp
<a href="https://www.youtube.com/watch?v={{ $key }}" target="_blank" rel="noopener" class="trailer-card"
   data-trailer="{{ $key }}" data-trailer-title="{{ $film['title'] }} — {{ $film['trailer']['name'] }}"
   data-film-url="{{ route('films.show', $film['slug']) }}">
    <span class="thumb">
        <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async">
        <span class="play" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span>
    </span>
    <span class="title">{{ $film['title'] }}</span>
    <span class="meta">
        {{ $film['trailer']['type'] === 'Teaser' ? 'Teaser' : 'Official trailer' }}
        @if (! empty($showRelease) && ! empty($film['release_date']))
            · Releases {{ \Illuminate\Support\Carbon::parse($film['release_date'])->format('M j') }}
        @endif
    </span>
</a>
