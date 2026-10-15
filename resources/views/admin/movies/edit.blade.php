{{-- Edit movie (route /admin/movies/{movie}/edit). The form itself: admin/movies/form.blade.php --}}
@extends('layouts.admin')
@section('title', 'Edit '.$movie->title)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2">
        <li class="breadcrumb-item"><a href="{{ route('admin.movies.index') }}">Movies</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
    </ol>
</nav>
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <h1 class="display-5 mb-0">{{ $movie->title }}</h1>
    <span @class(['badge rounded-pill fs-6', 'text-bg-success' => $movie->status === 'published', 'text-bg-secondary' => $movie->status === 'draft'])>{{ ucfirst($movie->status) }}</span>
</div>

@include('admin.movies.form')
@endsection
