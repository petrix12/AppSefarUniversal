<style>
    #contenedorlogin{
        background-color:rgba(0, 0, 0, 1);
        background-image: url("/img/bglogin.png");
        background-repeat: no-repeat;
        background-position: 50%;
        background-size: cover;
    }
</style>

<div id="contenedorlogin" class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
    <div>
        {{ $logo }}
    </div>

    <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
        {{ $slot }}
    </div>
</div>
