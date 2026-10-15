<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin users list (search by name/email) and Make admin / Remove admin.
 * Routes: routes/web.php ( GET /admin/users, PATCH /admin/users/{user}/admin ). View: resources/views/admin/users/index.blade.php
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::withCount(['reviews', 'ratings'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->latest()->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'q' => $q]);
    }

    public function toggleAdmin(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403, 'You can’t remove your own admin access.');      // ← never lock yourself out

        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin ? "{$user->name} is now an admin" : "{$user->name} is no longer an admin");
    }
}
