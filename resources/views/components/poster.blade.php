{{--
    Pass a catalog $film array (preferred) or just a $title. Shows the TMDB poster when there is one,
    else public/images/posters/{slug}.jpg if it exists, else a gradient with the title on it.
    While an image loads the poster is a dark shimmering box; the image fades in on load and the
    gradient + title only appear if there's no image or it fails.
    $href = false renders a non-link poster (e.g. on the film's own page). $large = true uses a bigger file.
    Usage: <x-poster :film="$film" />. Used on almost every page. Styles: .mire-poster / .poster-rail in app.scss
--}}
@props(['film' => null, 'title' => null, 'href' => null, 'large' => false])
@php
    $film ??= \App\Support\FilmCatalog::byTitles([$title])[0];                                      // ← only a title given? look it up on TMDB
    $local = 'images/posters/'.$film['slug'].'.jpg';
    $image = $film['poster'] ?? (file_exists(public_path($local)) ? asset($local) : null);          // ← TMDB poster, else a local file, else none
    if ($image && ! $large) {
        $image = str_replace('/t/p/w500/', '/t/p/w342/', $image); // grid posters are ≤ 180px wide
    }
    $hue = crc32($film['slug']) % 360;
    $bg = $film['bg'] ?? "linear-gradient(170deg, hsl({$hue} 45% 32%), hsl(".(($hue + 40) % 360).' 55% 14%))';  // ← fallback colour, unique per film
    $href = $href === false ? null : ($href ?? route('films.show', $film['slug']));
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['mire-poster', 'static' => ! $href, 'has-image' => $image])->merge(['title' => $film['title']]) }}
    style="--poster-bg: {{ $bg }}; color: {{ $film['color'] ?? '#fff' }}">
    <span class="title">{{ $film['title'] }}</span>
    <span class="subtitle">{{ $film['subtitle'] ?? '' }}</span>
    @if ($image)
        <img src="{{ $image }}" alt="{{ $film['title'] }} poster" @unless ($large) loading="lazy" @endunless decoding="async"
             onload="this.parentNode.classList.add('is-loaded')" onerror="this.parentNode.classList.add('is-failed'); this.remove()">  {{-- ← fade in when loaded / show title box if it fails --}}
    @endif
</{{ $tag }}>
