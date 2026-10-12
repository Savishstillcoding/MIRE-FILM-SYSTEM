{{--
    Landing page for guests (route /, PageController::home). Shows trending posters from FilmCatalog::trending().
    Buttons open the join / sign-in pop-ups from layouts/app.blade.php.
--}}
@extends('layouts.app')

@section('content')
    <div class="landing-backdrop" aria-hidden="true">                                               {{-- ← faded audience photos behind the page, crossfading (app.scss) --}}
        @foreach (range(1, 4) as $i)                                                                {{-- ← public/images/landing/crowd-1…4.webp --}}
            <img src="{{ asset("images/landing/crowd-$i.webp") }}" alt="" @if ($i > 1) loading="lazy" @endif> {{-- ← first photo loads right away, the rest lazily --}}
        @endforeach
    </div>

    <section class="mire-hero">
        <p class="quote">“Discover new movies, rate your favorites, and see what your friends are watching.”</p>

        <button class="btn btn-primary btn-lg px-4 py-3 mt-5 fs-4 fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#join">  {{-- ← opens partials/join-modal.blade.php --}}
            Get started — it’s free!
        </button>

        <p class="sub mt-5 d-flex align-items-center justify-content-center flex-wrap gap-2">
            <span>Everything is free, when it’s <span class="brand">MIRÉ</span>. Also available on</span>
            @include('partials.store-icons')
        </p>
    </section>

    <section class="landing-posters" aria-label="Featured films">
        @foreach ($films as $film)                                                                  {{-- ← 6 trending films (PageController::home) --}}
            <x-poster :film="$film" href="#join" data-bs-toggle="modal" data-bs-target="#join" />   {{-- ← guests: clicking a poster asks them to join --}}
        @endforeach
    </section>
@endsection
