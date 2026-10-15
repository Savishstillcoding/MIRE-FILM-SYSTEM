{{--
    "Create account" pop-up for guests (included by layouts/app.blade.php).
    Posts to AuthController::register(); errors use the "register" bag and reopen this pop-up (data-autoopen → app.js).
--}}
<div class="modal fade" id="join" tabindex="-1" aria-labelledby="join-title" aria-hidden="true" @if ($errors->register->any()) data-autoopen @endif>  {{-- ← data-autoopen = reopen after a failed sign-up --}}
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background:#382846">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-2 fw-light" id="join-title">Join Miré Film</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('register') }}" novalidate>
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" @class(['form-control form-control-lg', 'is-invalid' => $errors->register->has('email')]) value="{{ old('email') }}" autocomplete="email" required>  {{-- ← must be unique (users table) --}}
                        @error('email', 'register') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input id="username" name="name" type="text" @class(['form-control form-control-lg', 'is-invalid' => $errors->register->has('name')]) value="{{ old('name') }}" autocomplete="username" required>  {{-- ← 3–30 characters, unique --}}
                        @error('name', 'register') <div class="invalid-feedback">{{ $message }}</div> @else <div class="form-text">3–30 characters.</div> @enderror
                    </div>
                    <div class="mb-1">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" name="password" type="password" @class(['form-control form-control-lg', 'is-invalid' => $errors->register->has('password')]) autocomplete="new-password" required aria-describedby="password-rules">  {{-- ← rules: AppServiceProvider Password::defaults() --}}
                        @error('password', 'register') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        {{-- NEW: live checklist; each rule turns green as you type (app.js "data-password-rules"). The server checks the same rules. --}}
                        <ul class="password-rules list-unstyled small mt-2 mb-0" id="password-rules" data-password-rules="password">
                            <li data-rule="length">At least 8 characters</li>
                            <li data-rule="case">An uppercase and a lowercase letter</li>
                            <li data-rule="number">A number</li>
                            <li data-rule="symbol">A symbol, e.g. ! @ # ?</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-0 flex-column align-items-stretch">
                    <button class="btn btn-primary btn-lg" type="submit">Create account</button>    {{-- ← → AuthController::register --}}
                    <p class="text-center small text-mire-muted mt-2 mb-0">
                        Already a member? <a href="#signin" data-bs-toggle="modal" data-bs-target="#signin">Sign in</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>
