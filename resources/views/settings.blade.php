{{--
    Account settings (route /settings). NOTE: saving and deactivating are still stubs in routes/web.php.
--}}
@extends('layouts.app')
@section('title', 'Account Settings · Miré Film')

@section('content')
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 border-bottom pb-3">
    <h1 class="display-5 fw-light mb-0">Account settings</h1>
</div>

<div class="row g-4 mt-2">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('settings.update') }}" class="mire-card p-4 p-md-5">
            @csrf
            @method('PUT')
            <h2 class="h3 fw-light mb-4">Profile</h2>

            <div class="mb-4">
                <label for="settings-username" class="form-label">Username</label>
                <div class="input-group input-group-lg">
                    <input id="settings-username" name="username" type="text" readonly class="form-control" value="{{ old('username', auth()->user()->name) }}">
                    <button type="button" class="btn btn-outline-secondary" data-unlock="settings-username" aria-label="Edit username">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    </button>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-sm-6">
                    <label for="given_name" class="form-label">Given name</label>
                    <input id="given_name" name="given_name" type="text" class="form-control form-control-lg" value="{{ old('given_name') }}" autocomplete="given-name">
                </div>
                <div class="col-sm-6">
                    <label for="family_name" class="form-label">Family name</label>
                    <input id="family_name" name="family_name" type="text" class="form-control form-control-lg" value="{{ old('family_name') }}" autocomplete="family-name">
                </div>
            </div>

            <div class="mb-4">
                <label for="settings-email" class="form-label">Email address</label>
                <input id="settings-email" name="email" type="email" class="form-control form-control-lg" value="{{ old('email', auth()->user()->email) }}" autocomplete="email">
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg px-4">Save changes</button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="mire-card p-4 border-danger-subtle">
            <h2 class="h5 text-danger-emphasis">Delete account</h2>
            <p class="small text-mire-muted">Deactivating hides your profile, ratings and lists.</p>
            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deactivate-dialog">
                Deactivate account
            </button>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="deactivate-dialog" tabindex="-1" aria-labelledby="deactivate-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('account.deactivate') }}" class="modal-content border-danger">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h2 class="modal-title fs-4 text-danger" id="deactivate-title">Deactivate account</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Enter your password to confirm</label>
                    <input id="confirm_password" name="password" type="password" class="form-control form-control-lg" autocomplete="current-password" required>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="confirm" id="confirm-deactivate" required>
                    <label class="form-check-label" for="confirm-deactivate">I understand my account will be deactivated</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-bold">Deactivate account</button>
            </div>
        </form>
    </div>
</div>
@endpush
