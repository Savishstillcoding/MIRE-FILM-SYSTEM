{{--
    The Add / Edit movie form (included by admin/movies/create + edit). Sends to Admin\MovieController store/update.
    Poster preview + Replace/Remove: resources/js/app.js → "[data-poster]". Status comes from the button pressed.
--}}
@php
    $selected = collect(old('genres', $movie->genres->pluck('id')->all()))->map(fn ($id) => (int) $id);
    $poster = $movie->posterUrl();
@endphp
<form method="POST" enctype="multipart/form-data" novalidate
      action="{{ $movie->exists ? route('admin.movies.update', $movie) : route('admin.movies.store') }}" class="mire-card p-4 p-md-5">
    @csrf
    @if ($movie->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Please fix the highlighted fields.</div>
    @endif

    <div class="row g-4 g-lg-5">
        <div class="col-md-4 col-lg-3">                                                             {{-- ← poster (left) --}}
            <div data-poster>
                <span class="form-label d-block">Poster</span>
                <label for="poster" @class(['admin-poster-drop', 'has-image' => $poster, 'is-invalid' => $errors->has('poster')])>
                    <img src="{{ $poster }}" alt="" @unless ($poster) hidden @endunless data-poster-preview>
                    <span class="empty">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M12 12v6M9 15h6"/></svg>
                        <span class="fw-semibold">Upload poster</span>
                        <span class="small">JPG, PNG or WebP · max 2 MB<br>Portrait 2 : 3</span>
                    </span>
                </label>
                <input id="poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden" data-poster-input>
                <input type="hidden" name="remove_poster" value="0" data-poster-remove-flag>
                <div class="d-flex gap-3 justify-content-center mt-2 small" data-poster-actions @unless ($poster) hidden @endunless>
                    <label for="poster" class="btn btn-link btn-sm p-0">Replace</label>
                    <button type="button" class="btn btn-link btn-sm p-0 link-danger" data-poster-remove>Remove</button>
                </div>
                @error('poster') <div class="invalid-feedback d-block text-center">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-md-8 col-lg-9">                                                             {{-- ← details (right) --}}
            <div class="mb-4">
                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                <input id="title" name="title" type="text" required maxlength="200" value="{{ old('title', $movie->title) }}"
                       @class(['form-control form-control-lg', 'is-invalid' => $errors->has('title')])>
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-4 mb-4">
                <div class="col-sm-6">
                    <label for="year" class="form-label">Year released <span class="text-danger">*</span></label>
                    <input id="year" name="year" type="number" required min="1888" max="{{ now()->year + 2 }}" placeholder="{{ now()->year }}"
                           value="{{ old('year', $movie->year) }}" @class(['form-control form-control-lg', 'is-invalid' => $errors->has('year')])>
                    @error('year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label for="runtime" class="form-label">Runtime <span class="text-mire-muted small">(minutes)</span></label>
                    <input id="runtime" name="runtime" type="number" min="1" max="999" placeholder="120"
                           value="{{ old('runtime', $movie->runtime) }}" @class(['form-control form-control-lg', 'is-invalid' => $errors->has('runtime')])>
                    @error('runtime') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <fieldset class="mb-4">
                <legend class="form-label fs-6">Genres <span class="text-danger">*</span> <span class="text-mire-muted small">(pick one or more)</span></legend>
                <div class="d-flex flex-wrap gap-2">                                                {{-- ← toggle chips (checkboxes styled as buttons) --}}
                    @foreach ($genres as $genre)
                        <input type="checkbox" class="btn-check" name="genres[]" value="{{ $genre->id }}" id="genre-{{ $genre->id }}" autocomplete="off" @checked($selected->contains($genre->id))>
                        <label class="btn btn-outline-light rounded-pill" for="genre-{{ $genre->id }}">{{ $genre->name }}</label>
                    @endforeach
                </div>
                @error('genres') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </fieldset>

            <div class="row g-4 mb-4">
                <div class="col-sm-6">
                    <label for="age_rating" class="form-label">Age rating <span class="text-mire-muted small">(MTRCB)</span></label>
                    <select id="age_rating" name="age_rating" @class(['form-select form-select-lg', 'is-invalid' => $errors->has('age_rating')])>
                        <option value="">Not rated</option>
                        @foreach (\App\Models\Movie::AGE_RATINGS as $rating)
                            <option value="{{ $rating }}" @selected(old('age_rating', $movie->age_rating) === $rating)>{{ $rating }}</option>
                        @endforeach
                    </select>
                    @error('age_rating') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label for="director" class="form-label">Director</label>
                    <input id="director" name="director" type="text" maxlength="120" value="{{ old('director', $movie->director) }}"
                           @class(['form-control form-control-lg', 'is-invalid' => $errors->has('director')])>
                    @error('director') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-4">
                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                <textarea id="description" name="description" rows="6" required maxlength="5000"
                          @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $movie->description) }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex flex-wrap justify-content-end gap-2">
                <a href="{{ route('admin.movies.index') }}" class="btn btn-outline-light btn-lg">Cancel</a>
                <button type="submit" name="status" value="draft" class="btn btn-outline-primary btn-lg">Save as draft</button>
                <button type="submit" name="status" value="published" class="btn btn-primary btn-lg px-4">
                    {{ $movie->exists && $movie->status === 'published' ? 'Update' : 'Publish' }}
                </button>
            </div>
        </div>
    </div>
</form>
