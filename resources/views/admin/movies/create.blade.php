{{-- Add movie (route /admin/movies/create). The form itself: admin/movies/form.blade.php --}}
@extends('layouts.admin')
@section('title', 'Add movie')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2">
        <li class="breadcrumb-item"><a href="{{ route('admin.movies.index') }}">Movies</a></li>
        <li class="breadcrumb-item active" aria-current="page">Add movie</li>
    </ol>
</nav>
<h1 class="display-5 mb-4">Add movie</h1>

@include('admin.movies.form')
@endsection
