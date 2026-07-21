@once
    <script src="{{ asset('/js/gsap.js') }}?v={{ filemtime(public_path('js/gsap.js')) }}"></script>
    <script>
        window.sefarThreeReady = window.sefarThreeReady || new Promise(function (resolve) {
            if (window.THREE) {
                resolve(window.THREE);
                return;
            }

            window.addEventListener('sefar:three-ready', function (event) {
                resolve(event.detail.THREE);
            }, { once: true });
        });
    </script>
    <script type="module" src="{{ asset('/js/three-global.js') }}?v={{ filemtime(public_path('js/three-global.js')) }}"></script>
@endonce
