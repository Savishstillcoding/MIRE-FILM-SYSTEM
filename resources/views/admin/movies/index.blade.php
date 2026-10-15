{{--
    Admin movies list (route /admin/movies, Admin\MovieController::index). Tabs All / Published / Drafts, search ?q=,
    Edit + Delete per row (Delete asks first: app.js → "form[data-confirm]").
--}}
@extends('layouts.admin')
@section('title', 'Movies')

@section('content')
@php
    $tabs = ['' => 'All', 'published' => 'Published', 'draft' => 'Drafts'];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <h1 class="display-5 mb-0">Movies</h1>
    <a href="{{ route('admin.movies.create') }}" class="btn btn-primary btn-lg">+ Add movie</a>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <ul class="nav nav-pills gap-2">                                                                {{-- ← All / Published / Drafts --}}
        @foreach ($tabs as $value => $label)
            @php $active = ($status ?? '') === $value; @endphp
            <li class="nav-item">
                <a @class(['nav-link rounded-pill', 'active' => $active]) @if ($active) aria-current="page" @endif
                   href="{{ route('admin.movies.index', array_filter(['status' => $value, 'q' => $q])) }}">
                    {{ $label }} <span class="opacity-75">({{ $value === '' ? $counts['all'] : ($counts[$value] ?? 0) }})</span>
                </a>
            </li>
        @endforeach
    </ul>
    @if ($q !== '')
        <p class="mb-0 text-mire-muted">Results for “{{ $q }}” · <a href="{{ route('admin.movies.index', array_filter(['status' => $status])) }}">Clear</a></p>
    @endif
</div>

@if ($movies->isEmpty())
    <div class="mire-card p-5 text-center">
        <p class="fs-5 fw-light mb-3">{{ $q !== '' || $status ? 'No movies match.' : 'No movies yet.' }}</p>
        <a href="{{ route('admin.movies.create') }}" class="btn btn-primary">+ Add your first movie</a>
    </div>
@else
    <div class="mire-card overflow-hidden">
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-4" style="width: 4.5rem"><span class="visually-hidden">Poster</span></th>
                        <th scope="col">Title</th>
                        <th scope="col" class="d-none d-md-table-cell">Genres</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="d-none d-lg-table-cell">Updated</th>
                        <th scope="col" class="text-end pe-4"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movies as $movie)
                        <tr>
                            <td class="ps-4"><x-poster :film="$movie->toFilm()" :href="false" class="admin-thumb" /></td>
                            <td>
                                <a href="{{ route('admin.movies.edit', $movie) }}" class="fw-semibold link-light">{{ $movie->title }}</a>
                                <span class="text-mire-muted">({{ $movie->year }})</span>
                            </td>
                            <td class="d-none d-md-table-cell text-mire-muted">{{ $movie->genres->pluck('name')->join(', ') }}</td>
                            <td>
                                <span @class(['badge rounded-pill', 'text-bg-success' => $movie->status === 'published', 'text-bg-secondary' => $movie->status === 'draft'])>{{ ucfirst($movie->status) }}</span>
                            </td>
                            <td class="d-none d-lg-table-cell text-mire-muted small">{{ $movie->updated_at->diffForHumans() }}</td>
                            <td class="text-end pe-4 text-nowrap">
                                <a href="{{ route('admin.movies.edit', $movie) }}" class="btn btn-sm btn-outline-light" aria-label="Edit {{ $movie->title }}">Edit</a>
                                <form method="POST" action="{{ route('admin.movies.destroy', $movie) }}" class="d-inline"
                                      data-confirm="Delete “{{ $movie->title }}”? This can’t be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $movie->title }}">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $movies->links() }}</div>
@endif
@endsection
