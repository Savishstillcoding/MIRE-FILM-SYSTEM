/*
 * All the browser-side behaviour of Miré Film (everything else is rendered by Laravel on the server).
 * Loaded on every page by resources/views/layouts/app.blade.php (@vite). After editing, run `npm run build`.
 *
 * Each block below finds elements by a data-* attribute set in the Blade views:
 *   data-autoopen        partials/*-modal          reopen a pop-up after a server error
 *   data-gate            partials/header           guests: search asks them to sign in
 *   data-require-all     partials/signin-modal     faded button, warning cards, background sign-in → AuthController::login
 *   data-unlock          settings                  pencil unlocks the username field
 *   data-instant         film-show, review-card    like / watchlist / stars / review hearts without reloading
 *                                                   → FilmActionController, ReviewController::like
 *   data-share           film-show                 copy link / phone share sheet
 *   data-live            profile, profile-collection  refresh when coming back with the Back button
 */
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Reopen a modal after a failed submit (the server marks it with data-autoopen).
document.querySelectorAll('.modal[data-autoopen]').forEach(el => bootstrap.Modal.getOrCreateInstance(el).show());

// Focus the first field whenever a modal opens.
document.addEventListener('shown.bs.modal', e => e.target.querySelector('input:not([type=hidden])')?.focus());

// Guests: gated forms (e.g. search) ask them to sign in instead of submitting.
document.querySelectorAll('form[data-gate]').forEach(form =>
    form.addEventListener('submit', e => {
        e.preventDefault();
        const signin = document.getElementById('signin');
        if (signin) bootstrap.Modal.getOrCreateInstance(signin).show();
    })
);

// Sign in: the button only works once every required field is filled. Until then it looks faded,
// and pressing it shows the warning beside the box and marks the empty fields.
document.querySelectorAll('form[data-require-all]').forEach(form => {
    const fields = [...form.querySelectorAll('[required]')];                                        // ← username + password boxes
    const button = form.querySelector('[type=submit]');
    const warning = document.querySelector(form.dataset.warning);                                   // ← #signin-warning card (signin-modal.blade.php)
    const error = form.dataset.error && document.querySelector(form.dataset.error); // e.g. wrong password (from the server)
    const info = document.getElementById('signin-required');                          // ← "Sign in required" card (members-only URL)
    const empty = () => fields.filter(f => !f.value.trim());

    const refresh = () => {
        const incomplete = empty().length > 0;
        button.classList.toggle('is-incomplete', incomplete);                                       // ← faded look (app.scss .btn.is-incomplete)
        button.setAttribute('aria-disabled', String(incomplete));
        if (!incomplete && warning) warning.hidden = true;
    };

    fields.forEach(f => f.addEventListener('input', () => {
        if (f.value.trim()) f.classList.remove('is-invalid');
        if (error) error.hidden = true; // they're correcting it
        if (info) info.hidden = true;
        refresh();
    }));
    form.closest('.modal')?.addEventListener('shown.bs.modal', refresh); // catches browser autofill
    refresh();

    const showMissing = missing => {
        if (info) info.hidden = true;
        missing.forEach(f => f.classList.add('is-invalid'));
        if (error) error.hidden = true; // one card at a time
        if (warning) warning.hidden = false;
        missing[0]?.focus();
    };

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const missing = empty();
        if (missing.length) return showMissing(missing);                                            // ← empty box? show the card, don't send

        // Check the details in the background so the page (and the open sign-in box) never reloads.
        const label = button.textContent;
        const body = new FormData(form);
        button.disabled = true;
        button.textContent = 'Signing in…';                                                         // ← while AuthController::login checks the details
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            if (res.ok) return (location.href = (await res.json()).redirect); // keep "Signing in…" while leaving
            if (res.status === 419) return location.reload(); // page sat open too long (expired token)

            const errors = (await res.json()).errors ?? {};
            if (errors.credentials) {                                                               // ← wrong username/password
                if (warning) warning.hidden = true;
                if (error) error.hidden = false;                                                    // ← show the "Incorrect details" card
                const password = form.querySelector('[type=password]');
                password.value = '';                                                                // ← clear only the password, keep the username
                password.focus();
            } else {
                showMissing(fields.filter(f => errors[f.name]));
            }
        } catch {
            form.submit(); // network hiccup: fall back to a normal post
            return;
        }
        button.disabled = false;
        button.textContent = label;
        refresh();
    });
});

// Settings: pencil button unlocks the username field.
document.querySelectorAll('[data-unlock]').forEach(btn =>
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.unlock);
        input.readOnly = false;                                                                     // ← settings.blade.php username box
        input.focus();
    })
);

// Auto-dismiss flash toasts.
document.querySelectorAll('.toast').forEach(el => bootstrap.Toast.getOrCreateInstance(el).show());

function toast(message) {                                                                           // ← small pop-up message (top right)
    let box = document.querySelector('.toast-container');
    if (!box) {
        box = document.createElement('div');
        box.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.append(box);
    }
    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-primary border-0';
    el.setAttribute('role', 'status');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    el.querySelector('.toast-body').textContent = message;
    box.append(el);
    el.addEventListener('hidden.bs.toast', () => el.remove());
    bootstrap.Toast.getOrCreateInstance(el, { delay: 2500 }).show();
}

// Film page: like / watchlist / rate save instantly without reloading.
// data-instant names the JSON field holding the new state ("liked", "watchlisted" or "rating").
const applyState = {
    rating(form, n) {                                                                               // ← lights up stars 1..n
        form.querySelectorAll('button[name=rating]').forEach(b => {
            const v = Number(b.value);
            b.classList.toggle('on', v <= n);
            b.setAttribute('aria-label', `Rate ${v} out of 5${v === n ? ' (click again to clear)' : ''}`);
        });
        const label = document.querySelector('[data-rate-label]');
        if (label) label.textContent = n ? 'Your rating' : 'Rate';
    },
    toggle(form, on, data) {                                                                        // ← heart / watchlist / review-heart on or off
        const btn = form.querySelector('button');
        btn.classList.toggle('on', on);
        btn.setAttribute('aria-pressed', String(on));
        if (btn.dataset.labelOn) btn.querySelector('.label').textContent = on ? btn.dataset.labelOn : btn.dataset.labelOff;
        // Review likes also show a live count.
        const count = btn.querySelector('[data-count]');
        if (count && 'likes' in data) {
            count.textContent = data.likes.toLocaleString();
            count.nextElementSibling.textContent = data.likes === 1 ? 'like' : 'likes';
        }
    },
};

document.querySelectorAll('form[data-instant]').forEach(form =>
    form.addEventListener('submit', async e => {
        e.preventDefault();
        // Read the form (incl. which star was clicked) BEFORE disabling: disabled buttons are left out of FormData.
        const body = new FormData(form, e.submitter);                                               // ← read BEFORE disabling (disabled buttons aren't sent)
        const buttons = form.querySelectorAll('button');
        buttons.forEach(b => (b.disabled = true));
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            if (res.status === 401 || res.status === 419) return location.reload(); // signed out or stale page
            if (!res.ok) throw new Error(res.statusText);

            const data = await res.json();
            const key = form.dataset.instant;                                                       // ← "liked", "watchlisted" or "rating"
            key === 'rating' ? applyState.rating(form, data.rating) : applyState.toggle(form, data[key], data);
            toast(data.message);                                                                    // ← e.g. "Added to your likes"
        } catch {
            toast('Couldn’t save that. Please try again.');                                         // ← server error or no connection
        } finally {
            buttons.forEach(b => (b.disabled = false));
        }
    })
);

// Film page "Share": copy the link (or use the phone's share sheet).
document.querySelectorAll('[data-share]').forEach(btn =>
    btn.addEventListener('click', async () => {
        const url = btn.dataset.share;
        try {
            if (navigator.share) return await navigator.share({ url, title: document.title });
            await navigator.clipboard.writeText(url);                                               // ← desktop: copy the link
            toast('Link copied');
        } catch {
            /* share sheet dismissed */
        }
    })
);

// Pages marked data-live re-fetch when restored from the back/forward cache: the profile (so likes and
// watchlist changes always show) and EVERY members-only page (layouts/app puts data-live on <body> when signed in),
// so after signing out the Back button re-checks the login instead of showing a saved copy.
window.addEventListener('pageshow', e => {
    if (e.persisted && document.querySelector('[data-live]')) location.reload();                    // ← Back button showed an old copy? refresh it
});
