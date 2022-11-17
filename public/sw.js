const CACHE_ELEMENTS = [
    // Incluir todas las rutas que usa la aplicación, incluyendo los CDN's
    "./",
    "./agclientes",
    "./albero",
    "./books",
    "./consultaodx",
    "./countries",
    "./dashboard",
    "./families",
    "./files",
    "./formats",
    "./lados",
    "./libraries",
    "./login",
    "./logout",
    "./miscelaneos",
    "./olivo",
    "./parentescos",
    "./permissions",
    "./procesar",
    "./register",
    "./registro",
    "./roles",
    "./salir",
    "./t_files",
    "./tree",
    "./user/password",
    "./profile",
    "./users",
    "./css/app.css",
    "./css/sefar.css",
    "./js/app.js",
    "https://cdn.jsdelivr.net/npm/sweetalert2@9",
    "https://app.universalsefar.com/vendor/adminlte/dist/img/LogoSefar.png",
    "https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap",
    "http://www.w3.org/2000/svg",
    "https://laravel.com/docs",
    "https://laracasts.com",
    "https://laravel-news.com",
    "https://forge.laravel.com",
    "https://laravel.bigcartel.com",
    "https://github.com/sponsors/taylorotwell"
]

const CACHE_NAME = "v1_cache_app_sefar_old"

self.addEventListener("install", (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                cache.addAll(CACHE_ELEMENTS)
                    .then(() => {
                        self.skipWaiting()
                    }).catch(console.log)
            })
    )
})

self.addEventListener("activate", (e) => {
    const cacheWhiteList = [CACHE_NAME]

    e.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    return (
                        cacheWhiteList.indexOf(cacheName) === -1 && caches.delete(cacheName)
                    )
                })
            )
        }).then(() => self.clients.claim())
    )
})

self.addEventListener("fetch", (e) => {
    e.respondWith(
        caches.match(e.request).then((res) => {
            if(res){
                return res
            }
            return fetch(e.request)
        })
    )
})
