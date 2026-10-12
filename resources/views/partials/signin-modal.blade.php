{{--
    "Sign in" pop-up for guests (included by layouts/app.blade.php). Posts to AuthController::login().
    Warning cards beside the box: #signin-warning (empty fields) and #signin-error (wrong details).
    Behaviour (faded button, background sign-in, showing the cards): resources/js/app.js → "form[data-require-all]"
    Styles: .signin-warning / .btn.is-incomplete in app.scss. Tested in tests/Feature/SignInTest.php
--}}
@php
    // Empty fields (server-side check) vs. wrong username/password are shown differently.
    $missing = $errors->login->has('name') || $errors->login->has('password');
    $required = session('signin_required') && ! $errors->login->any(); // sent here from a members-only URL (routes/web.php GET /login)
@endphp
<div class="modal fade" id="signin" tabindex="-1" aria-labelledby="signin-title" aria-hidden="true" @if ($errors->login->any() || $required) data-autoopen @endif>
    <div class="modal-dialog modal-dialog-centered signin-dialog">
        {{-- Warning cards beside the box (above it on phones); only one shows at a time. See app.js. --}}
        <div class="signin-warning" id="signin-error" role="alert" @unless ($errors->login->has('credentials')) hidden @endunless>  {{-- ← "Incorrect details" card (wrong username/password) --}}
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 2 1 21h22L12 2zm-1 7h2v6h-2V9zm0 8h2v2h-2v-2z"/></svg>
            <div>
                <strong class="d-block">Incorrect details</strong>
                {{ $errors->login->first('credentials') ?: 'Username or password is incorrect.' }} Please try again.
            </div>
        </div>
        <div class="signin-warning info" id="signin-required" role="status" @unless ($required) hidden @endunless>  {{-- ← "Sign in required" card (tried to open a members-only page) --}}
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm-3 8V7a3 3 0 0 1 6 0v3H9z"/></svg>
            <div>
                <strong class="d-block">Sign in required</strong>
                Please sign in to view that page. You’ll be taken there right after.
            </div>
        </div>
        <div class="signin-warning" id="signin-warning" role="alert" @unless ($missing) hidden @endunless>  {{-- ← "Fields not filled out" card --}}
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 2 1 21h22L12 2zm-1 7h2v6h-2V9zm0 8h2v2h-2v-2z"/></svg>
            <div>
                <strong class="d-block">Fields not filled out</strong>
                Sign in won’t work until you fill in both your username and password.
            </div>
        </div>

        <div class="modal-content mire-card border-0 shadow-lg" style="background:#211a26">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-3 fw-light" id="signin-title">Welcome back</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('login') }}" novalidate data-require-all data-warning="#signin-warning" data-error="#signin-error">  {{-- ← app.js handles this form (no page reload) --}}
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="login-name" class="form-label">Username</label>
                        <input id="login-name" name="name" type="text" @class(['form-control form-control-lg', 'is-invalid' => $errors->login->has('name')])  {{-- ← red outline when empty --}}
                               value="{{ old('name') }}" autocomplete="username" required>
                        <div class="invalid-feedback">Enter your username.</div>
                    </div>
                    <div class="mb-3">
                        <label for="login-password" class="form-label">Password</label>
                        <input id="login-password" name="password" type="password" @class(['form-control form-control-lg', 'is-invalid' => $errors->login->has('password')])
                               autocomplete="current-password" required>
                        <div class="invalid-feedback">Enter your password.</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
                        @endif
                    </div>
                </div>
                <div class="modal-footer border-0 flex-column align-items-stretch">
                    <button class="btn btn-primary btn-lg" type="submit">Sign in</button>           {{-- ← faded until both boxes are filled (.is-incomplete) --}}
                    <p class="text-center small text-mire-muted mt-2 mb-0">
                        New here? <a href="#join" data-bs-toggle="modal" data-bs-target="#join">Create an account</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>
