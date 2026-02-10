@php
  $location = $user->city ?? '—';
  $phone    = $user->phone ?? '—';
@endphp

<div class="bg-white shadow-lg rounded-2xl overflow-hidden">

  {{-- COVER con gradiente más suave --}}
  <div class="relative h-32 sm:h-40 bg-gradient-to-br from-slate-800 via-slate-700 to-slate-800">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.1),transparent_50%)]"></div>
  </div>

  {{-- CONTENEDOR PRINCIPAL --}}
  <div class="p-5 sm:px-6 pb-6">

    {{-- SECCIÓN SUPERIOR: Avatar + Botón --}}
    <div class="flex items-end justify-between -mt-12 sm:-mt-14 mb-4">
      {{-- Avatar --}}
      <div class="flex-shrink-0">
        <img
          src="{{ $user->profile_photo_url }}"
          class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl ring-4 ring-white object-cover shadow-xl"
          alt="Foto de perfil"
        >
      </div>

      {{-- Botón Editar --}}
      <button
        type="button"
        onclick="openEditProfile()"
        class="csrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 edit-user-btn"
      >
        <i class="fas fa-edit text-sm"></i>
        <span class="hidden sm:inline">Editar perfil</span>
        <span class="sm:hidden">Editar</span>
      </button>
    </div>

    {{-- INFORMACIÓN DEL USUARIO --}}
    <div class="space-y-4">

      {{-- Nombre y Rol --}}
      <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 mb-1">
          {{ $user->name }}
        </h1>
        <p class="text-base font-medium flex items-center gap-2">
          <i class="fas fa-briefcase text-sm mr-2"></i>
          {{ $roleName == "Coord. Ventas" ? "Coordinador de Ventas" : $roleName }}
        </p>
      </div>

      {{-- Datos de contacto --}}
        <div class="mt-2 flex flex-wrap items-center text-sm text-slate-600">

            <span class="inline-flex items-center gap-1">
                <i class="fas fa-map-marker-alt text-xs"></i>
                {{ $location }}
            </span>

            <span class="mx-2 text-slate-300">•</span>

            <span class="inline-flex items-center gap-1">
                <i class="fas fa-envelope text-xs"></i>
                <span class="break-all">{{ $user->email }}</span>
            </span>

            <span class="mx-2 text-slate-300">•</span>

            <span class="inline-flex items-center gap-1">
                <i class="fas fa-phone text-xs"></i>
                {{ $phone }}
            </span>

        </div>
    </div>
  </div>
</div>
