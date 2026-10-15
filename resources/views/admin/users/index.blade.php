{{--
    Admin users list (route /admin/users, Admin\UserController). Search ?q= by name or email; Make admin / Remove admin button.
--}}
@extends('layouts.admin')
@section('title', 'Users')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <h1 class="display-5 mb-0">Users</h1>
    <form class="mire-search position-relative" role="search" method="GET" action="{{ route('admin.users.index') }}" style="min-width: min(100%, 20rem)">
        <label for="user-search" class="visually-hidden">Search users</label>
        <input id="user-search" name="q" type="search" class="form-control" placeholder="Search name or email…" value="{{ $q }}">
        <button type="submit" class="btn btn-link" aria-label="Search">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </button>
    </form>
</div>

@if ($q !== '')
    <p class="text-mire-muted">Results for “{{ $q }}” · <a href="{{ route('admin.users.index') }}">Clear</a></p>
@endif

@if ($users->isEmpty())
    <div class="mire-card p-5 text-center fs-5 fw-light">No users match.</div>
@else
    <div class="mire-card">
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-4">Name</th>
                        <th scope="col" class="d-none d-md-table-cell">Email</th>
                        <th scope="col" class="d-none d-sm-table-cell">Joined</th>
                        <th scope="col" class="text-center">Reviews</th>
                        <th scope="col" class="text-center d-none d-lg-table-cell">Ratings</th>
                        <th scope="col" class="text-end pe-4"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php $me = $user->is(auth()->user()); @endphp
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <x-avatar size="32" />
                                    <span class="fw-semibold">{{ $user->name }}</span>
                                    @if ($user->is_admin) <span class="badge rounded-pill text-bg-primary">Admin</span> @endif
                                    @if ($me) <span class="text-mire-muted small">(you)</span> @endif
                                </div>
                                <div class="d-md-none small text-mire-muted">{{ $user->email }}</div>
                            </td>
                            <td class="d-none d-md-table-cell text-mire-muted">{{ $user->email }}</td>
                            <td class="d-none d-sm-table-cell text-mire-muted small text-nowrap">{{ $user->created_at?->format('M j, Y') }}</td>
                            <td class="text-center">{{ $user->reviews_count }}</td>
                            <td class="text-center d-none d-lg-table-cell">{{ $user->ratings_count }}</td>
                            <td class="text-end pe-4">
                                @unless ($me)                                                       {{-- ← you can't remove your own admin access --}}
                                    <form method="POST" action="{{ route('admin.users.admin', $user) }}"
                                          data-confirm="{{ $user->is_admin ? "Remove admin access from {$user->name}?" : "Make {$user->name} an admin? They'll be able to add, edit and delete movies." }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" @class(['btn btn-sm text-nowrap', 'btn-outline-danger' => $user->is_admin, 'btn-outline-light' => ! $user->is_admin])>
                                            {{ $user->is_admin ? 'Remove admin' : 'Make admin' }}
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $users->links() }}</div>
@endif
@endsection
