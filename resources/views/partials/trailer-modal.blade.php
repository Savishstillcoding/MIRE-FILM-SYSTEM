{{--
    NEW: the pop-up trailer player (Example 2). One per page, added by layouts/app.blade.php for members.
    Any link with data-trailer="YOUTUBE_KEY" opens it (app.js → "data-trailer"); closing it stops the video.
    youtube-nocookie.com = YouTube's privacy-friendly player (no tracking cookies until you press play).
--}}
<div class="modal fade" id="trailer-modal" tabindex="-1" aria-labelledby="trailer-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content trailer-dialog border-0 shadow-lg">
            <div class="modal-header border-0">
                <h2 class="modal-title fs-3" id="trailer-title" data-trailer-heading>Trailer</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="ratio ratio-16x9 rounded overflow-hidden bg-black">                     {{-- ← keeps the player 16:9 at any width --}}
                    <iframe data-trailer-frame title="Trailer player" allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                            allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <a href="#" class="btn btn-outline-light" data-trailer-film hidden>View film page →</a>  {{-- ← hidden when you're already on the film page --}}
            </div>
        </div>
    </div>
</div>
