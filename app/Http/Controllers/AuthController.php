<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Create account, sign in and sign out. Uses the default users table: `name` is the username.
 *
 * Routes:  routes/web.php ( POST /register, POST /login, POST /logout )
 * Forms:   resources/views/partials/join-modal.blade.php (Create account)
 *          resources/views/partials/signin-modal.blade.php (Sign in + the warning cards beside it)
 * Sign-in JS: resources/js/app.js → "form[data-require-all]" (empty-field check + background sign-in)
 */
class AuthController extends Controller
{
    /** Validation errors go to the "register" error bag so only the Create account box shows them. */
    public function register(Request $request)
    {
        $data = $request->validateWithBag('register', [                                             // ← errors reopen the Create account pop-up
            'email'    => 'required|email|unique:users,email',
            'name'     => 'required|string|min:3|max:30|unique:users,name',
            'password' => ['required', 'string', Password::defaults()],                             // ← NEW: 8+ chars, upper + lower, number, symbol (AppServiceProvider)
        ]);

        $user = User::create($data); // hashed via the User model's 'hashed' cast                   // ← new row in users
        Auth::login($user);                                                                         // ← signed in straight away
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Welcome to Miré Film!');
    }

    /**
     * Errors go to the "login" bag: "name"/"password" = empty field, "credentials" = wrong details.
     * signin-modal.blade.php shows a different warning card for each.
     */
    public function login(Request $request)
    {
        $creds = $request->validateWithBag('login', [                                               // ← empty fields → "Fields not filled out" card
            'name' => 'required|string', 'password' => 'required|string',
        ], [
            'name.required' => 'Enter your username.',
            'password.required' => 'Enter your password.',
        ]);

        // The sign-in box submits in the background (JSON) so the page doesn't reload; plain posts still work.
        if (! Auth::attempt($creds, $request->boolean('remember'))) {                               // ← wrong username/password?
            $message = 'Username or password is incorrect.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => ['credentials' => [$message]]], 422)  // ← app.js shows the "Incorrect details" card
                : back()->withErrors(['credentials' => $message], 'login')->withInput($request->only('name'));  // ← no JavaScript: reload with the card shown
        }

        $request->session()->regenerate();
        $to = redirect()->intended(route('home'))->getTargetUrl();                                  // ← the page they were trying to open, else Home

        return $request->expectsJson() ? response()->json(['redirect' => $to]) : redirect($to);     // ← app.js then goes to 'redirect'
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
