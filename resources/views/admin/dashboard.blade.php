{{--
    Admin dashboard (route /admin, Admin\DashboardController). Stat cards + recent activity.
--}}
@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
@php
    $icons = [
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'review' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'movie' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16"/>',
    ];
@endphp

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="display-5 mb-1">Welcome back, {{ auth()->user()->name }}.</h1>
        <p class="text-mire-muted fs-5 fw-light mb-0">Here’s what’s happening today.</p>
    </div>
    <a href="{{ route('admin.movies.create') }}" class="btn btn-primary btn-lg">+ Add movie</a>
</div>

<div class="row row-cols-2 row-cols-lg-4 g-3 mb-5">                                                   {{-- ← 2 per row on phones, 4 on laptops --}}
    @foreach ($stats as $stat)
        <div class="col">
            <div class="admin-stat h-100">
                <span class="label">{{ $stat['label'] }}</span>
                <span class="value">{{ number_format($stat['total']) }}</span>
                <span @class(['today', 'up' => $stat['today'] > 0])>+{{ $stat['today'] }} today</span>
                @if ($stat['url'])
                    <a href="{{ $stat['url'] }}" class="stretched-link" aria-label="Open {{ $stat['label'] }}"></a>
                @endif
            </div>
        </div>
    @endforeach
</div>

<x-section-heading variant="caps">
    Recent activity
    @if ($drafts)
        <x-slot:action><a href="{{ route('admin.movies.index', ['status' => 'draft']) }}">{{ $drafts }} {{ Str::plural('draft', $drafts) }} waiting →</a></x-slot:action>
    @endif
</x-section-heading>

@if ($activity)
    <ul class="admin-activity list-unstyled mb-0">
        @foreach ($activity as $item)
            <li>
                <span class="icon {{ $item['icon'] }}">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $icons[$item['icon']] !!}</svg>
                </span>
                <span class="flex-grow-1">{{ $item['text'] }}</span>
                <time class="text-mire-muted small text-nowrap" datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->diffForHumans() }}</time>
            </li>
        @endforeach
    </ul>
@else
    <p class="text-mire-muted">Nothing yet. Sign-ups, reviews and ratings will show up here.</p>
@endif
@endsection
