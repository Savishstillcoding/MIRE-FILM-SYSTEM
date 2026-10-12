{{--
    "See all" page for likes or watchlist (route /profile/{likes|watchlist}, PageController::collection).
--}}
@extends('layouts.app')
@php $heading = $type === 'likes' ? 'Likes' : 'Watchlist'; @endphp
@section('title', $heading.' · Miré Film')

@section('content')
<div data-live>
    <a href="{{ route('profile') }}" class="link-light small">← Back to profile</a>

    <x-section-heading class="mt-3" as="h1">
        {{ $heading }}
        <x-slot:action>{{ $items->count() }} {{ Str::plural('film', $items->count()) }}</x-slot:action>
    </x-section-heading>

    @if ($items->isEmpty())
        <p class="fs-5 fw-light text-mire-muted">
            {{ $type === 'likes' ? 'Nothing liked yet. Tap the heart on any film.' : 'Your watchlist is empty. Use the watchlist button on any film.' }}
            <a href="{{ route('films') }}">Browse films</a>
        </p>
    @else
        <div class="row row-cols-3 row-cols-sm-4 row-cols-md-5 row-cols-lg-6 g-3 g-md-4">
            @foreach ($items as $item)
                <div class="col">
                    <x-poster :film="$item->toFilm()" />
                    <p class="small text-mire-muted text-center mt-2 mb-0" title="{{ $item->created_at }}">{{ $item->created_at->diffForHumans() }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
