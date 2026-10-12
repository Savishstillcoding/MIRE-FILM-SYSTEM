{{--
    Landing page for guests (route /, PageController::home). Shows trending posters from FilmCatalog::trending().
    Buttons open the join / sign-in pop-ups from layouts/app.blade.php.
--}}
@extends('layouts.app')

@section('content')
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
        @foreach ($films as $film)                                                                  {{-- ← 5 trending films (PageController::home) --}}
            <x-poster :film="$film" href="#join" data-bs-toggle="modal" data-bs-target="#join" />   {{-- ← guests: clicking a poster asks them to join --}}
        @endforeach
    </section>
@endsection
