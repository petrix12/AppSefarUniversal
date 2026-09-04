@if ($googleReviewInvitation ?? false)
    <div id="googleReviewInvitation" class="google-review-invitation" role="dialog" aria-modal="true" aria-labelledby="googleReviewInvitationTitle" aria-describedby="googleReviewInvitationDescription" hidden>
        <div class="google-review-invitation__backdrop" data-review-close></div>

        <section class="google-review-invitation__card" role="document">
            <button type="button" class="google-review-invitation__close" aria-label="Cerrar" data-review-close>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

            <div class="google-review-invitation__badge" aria-hidden="true">
                <i class="fa-brands fa-google"></i>
            </div>
            <p class="google-review-invitation__eyebrow">Tu opinión nos impulsa</p>
            <h2 id="googleReviewInvitationTitle">¿Cómo fue tu experiencia con Sefar Universal?</h2>
            <p id="googleReviewInvitationDescription">
                Ya tienes cinco o más personas en tu árbol. Si nuestra atención te ha ayudado,
                nos encantaría conocer tu opinión.
            </p>

            <div class="google-review-invitation__stars" aria-label="Cinco estrellas">
                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
            </div>

            <form action="{{ route('clientes.google-review') }}" method="POST" target="_blank" class="google-review-invitation__form">
                @csrf
                <button type="submit" class="google-review-invitation__submit">
                    <i class="fa-brands fa-google" aria-hidden="true"></i>
                    Escribir una reseña
                </button>
            </form>

            <button type="button" class="google-review-invitation__later" data-review-close>Ahora no, gracias</button>
            <p class="google-review-invitation__privacy-note">
                Se abrirá directamente el formulario de Google para escribir tu reseña; no verás las reseñas de otros clientes.
            </p>
        </section>
    </div>

    <style>
        .google-review-invitation { position: fixed; inset: 0; z-index: 1080; align-items: center; justify-content: center; padding: 1.5rem; font-family: Lato, Arial, sans-serif; }
        .google-review-invitation[hidden] { display: none; }
        .google-review-invitation:not([hidden]) { display: flex; }
        .google-review-invitation__backdrop { position: absolute; inset: 0; background: rgba(9, 49, 67, .68); backdrop-filter: blur(5px); }
        .google-review-invitation__card { position: relative; width: min(100%, 490px); overflow: hidden; border: 1px solid rgba(247, 176, 52, .4); border-radius: 24px; padding: 2.5rem 2rem 1.65rem; background: linear-gradient(145deg, #fff 0%, #fffaf1 100%); box-shadow: 0 24px 70px rgba(0, 0, 0, .32); color: #093143; text-align: center; animation: google-review-invitation-enter .32s ease-out both; }
        .google-review-invitation__card::before { position: absolute; top: 0; right: 0; left: 0; height: 7px; background: linear-gradient(90deg, #093143 0 35%, #f7b034 35% 65%, #06c2cc 65%); content: ''; }
        .google-review-invitation__close { position: absolute; top: .85rem; right: .85rem; width: 2.2rem; height: 2.2rem; border: 0; border-radius: 50%; background: #edf2f3; color: #49616b; cursor: pointer; font-size: 1rem; }
        .google-review-invitation__close:hover, .google-review-invitation__close:focus { background: #dce8eb; color: #093143; }
        .google-review-invitation__badge { display: inline-flex; align-items: center; justify-content: center; width: 3.75rem; height: 3.75rem; margin-bottom: .9rem; border-radius: 1.1rem; background: #fff; box-shadow: 0 8px 22px rgba(9, 49, 67, .12); color: #4285f4; font-size: 1.85rem; }
        .google-review-invitation__eyebrow { margin: 0 0 .35rem; color: #a66e12; font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .google-review-invitation h2 { margin: 0; color: #093143; font-size: clamp(1.4rem, 4vw, 1.78rem); font-weight: 900; line-height: 1.2; }
        .google-review-invitation p { margin: .85rem auto 0; max-width: 390px; color: #52646c; font-size: 1rem; line-height: 1.55; }
        .google-review-invitation__stars { display: flex; justify-content: center; gap: .28rem; margin: 1.2rem 0; color: #f7b034; font-size: 1.35rem; }
        .google-review-invitation__form { margin: 0; }
        .google-review-invitation__submit { display: inline-flex; align-items: center; justify-content: center; gap: .65rem; width: 100%; min-height: 3.2rem; border: 0; border-radius: .72rem; background: #093143; box-shadow: 0 8px 16px rgba(9, 49, 67, .18); color: #fff; cursor: pointer; font-size: 1rem; font-weight: 800; transition: transform .16s ease, background .16s ease, box-shadow .16s ease; }
        .google-review-invitation__submit:hover, .google-review-invitation__submit:focus { background: #0d4d66; box-shadow: 0 10px 22px rgba(9, 49, 67, .26); color: #fff; transform: translateY(-1px); }
        .google-review-invitation__later { margin-top: .8rem; border: 0; background: transparent; color: #63747b; cursor: pointer; font-size: .9rem; text-decoration: underline; text-underline-offset: 3px; }
        .google-review-invitation__privacy-note { margin-top: 1rem !important; font-size: .77rem !important; line-height: 1.4 !important; }
        @keyframes google-review-invitation-enter { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @media (max-width: 480px) { .google-review-invitation { padding: 1rem; } .google-review-invitation__card { padding: 2.35rem 1.3rem 1.4rem; border-radius: 20px; } }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('googleReviewInvitation');

            if (!modal) {
                return;
            }

            const closeModal = function () {
                modal.hidden = true;
            };

            window.setTimeout(function () {
                modal.hidden = false;
                modal.querySelector('.google-review-invitation__submit').focus();
            }, 350);

            modal.querySelectorAll('[data-review-close]').forEach(function (button) {
                button.addEventListener('click', closeModal);
            });

            modal.querySelector('form').addEventListener('submit', closeModal);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });
        });
    </script>
@endif
