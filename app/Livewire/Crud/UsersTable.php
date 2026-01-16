<?php

namespace App\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\User;
use App\Models\Servicio;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use App\Models\Compras;
use Illuminate\Support\Facades\Auth;

class UsersTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10'],
    ];

    public $search = '';
    public $filterServicio = '';
    public $filterContrato = '';
    public $filterPago = '';
    public $perPage = 10;

    // Cacheados (se cargan una sola vez)
    public $listaServicios = [];
    public $serviciosPlano = [];

    public function mount()
    {
        $this->loadServiciosOptimized();
    }

    /**
     * ============================================================
     *  🔵 CARGAR SERVICIOS — OPTIMIZADO CON CACHE Y UNA SOLA VEZ
     * ============================================================
     */
    protected function loadServiciosOptimized()
    {
        [
            $this->listaServicios,
            $this->serviciosPlano
        ] = Cache::remember('servicios_agrupados_opt', 3600, function () {

            // 1) Traer servicios y normalizarlos (1 sola vez)
            $serviciostabla = Servicio::query()
                ->where('tipov', 0)
                ->where('id_hubspot', 'not like', '% - Hermano%')
                ->pluck('id_hubspot')
                ->map(fn($s) => $this->normalizeServiceName($s))
                ->toArray();

            // 2) Definir grupos
            $grupos = [
                'Nacionalidad Española' => [
                    'Española LMD',
                    'Española Sefardi',
                    'Española Sefardi - Subsanación',
                    'Española - Carta de Naturaleza',
                    'Formalizacion Anticipada Ley de Memoria Democrática',
                ],
                'Nacionalidad Portuguesa' => [
                    'Portuguesa Sefardi',
                    'Portuguesa Sefardi - Subsanación',
                    'Formalizacion Anticipada Portuguesa Sefardi',
                    'Certificación de Documentos - Portugal',
                ],
                'Nacionalidad Italiana' => [
                    'Italiana',
                    'Diagnóstico Express para Plan de acción de la Nacionalidad Italiana',
                ],
                'Otros' => [
                    'Análisis por semana',
                    'Recurso de Alzada',
                    'Gestión Documental',
                    'Acumulación de linajes',
                    'Árbol genealógico de Deslinde',
                    'Procedimiento de Urgencia',
                    'Analisis Juridico Genealogico',
                ]
            ];

            // Normalizar grupos
            $gruposNorm = collect($grupos)->map(function ($items) {
                return array_map([$this, 'normalizeServiceName'], $items);
            });

            // Agrupar
            $listaAgrupada = [];
            foreach ($gruposNorm as $categoria => $items) {
                $coinciden = array_values(array_intersect($items, $serviciostabla));
                if (!empty($coinciden)) {
                    $listaAgrupada[$categoria] = $coinciden;
                }
            }

            // Servicios sin categoría
            $todos = array_merge(...array_values($gruposNorm->toArray()));
            $sinCategoria = array_diff($serviciostabla, $todos);

            if (!empty($sinCategoria)) {
                $listaAgrupada['Otros'] = array_merge(
                    $listaAgrupada['Otros'] ?? [],
                    array_values($sinCategoria)
                );
            }

            // Lista plana
            $serviciosPlano = [];
            foreach ($listaAgrupada as $categoria => $items) {
                foreach ($items as $item) {
                    $serviciosPlano[$item] = "[$categoria] $item";
                }
            }

            asort($serviciosPlano);

            return [$listaAgrupada, $serviciosPlano];
        });
    }

    /**
     * ============================================================
     *  🔵 BUSQUEDA — OPTIMIZADO
     * ============================================================
     */
    public function render()
    {
        // 1. Obtener el usuario autenticado
        $authUser = Auth::user()->load('roles');

        $query = User::query()
            ->select([
                'id', 'name', 'nombres', 'apellidos', 'email',
                'passport', 'servicio', 'contrato', 'pay', 'created_at',
                'owner_id' // Asegúrate de incluirlo en el select
            ])
            ->with(['compras:id,id_user,servicio_hs_id,pagado']);

        /**
         * 🛡️ SEGURIDAD: FILTRO POR ROL (17: Coord. Ventas, 15: Ventas)
         * Si el usuario logueado tiene uno de estos roles, solo ve sus prospectos.
         */

        $rolesIds = $authUser->roles->pluck('id')->toArray();

        $query->when(
            !empty(array_intersect($rolesIds, [15, 17])),
            function ($q) use ($authUser) {
                return $q->where('owner_id', $authUser->id);
            }
        );

        // 🔍 BUSQUEDA MULTI-CAMPO
        $query->when($this->search, function ($query) {
            $terms = preg_split('/\s+/', trim($this->search));
            foreach ($terms as $term) {
                $query->where(function ($q) use ($term) {
                    $like = "%{$term}%";
                    $q->whereRaw("CONCAT_WS(' ', name, nombres, apellidos, email, passport) LIKE ?", [$like]);
                });
            }
        });

        // 🔵 FILTRO SERVICIO
        $query->when($this->filterServicio !== '', function ($query) {
            $query->where(function ($q) {
                $q->where('servicio', $this->filterServicio)
                  ->orWhereHas('compras', function ($c) {
                      $c->where('servicio_hs_id', $this->filterServicio);
                  });
            });
        });

        // 🔵 FILTROS SIMPLES
        $query->when($this->filterContrato !== '', fn($q) => $q->where('contrato', $this->filterContrato))
              ->when($this->filterPago !== '', fn($q) => $q->where('pay', $this->filterPago));

        // 🔥 PAGINACIÓN
        $users = $query->orderBy('created_at', 'DESC')
                       ->paginate($this->perPage);

        return view('livewire.crud.users-table', [
            'users' => $users,
            'listaServicios' => $this->listaServicios,
            'serviciosPlano' => $this->serviciosPlano,
        ]);
    }

    /**
     * ============================================================
     *  🔵 NORMALIZADOR LIGERO
     * ============================================================
     */
    protected function normalizeServiceName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(["\u{A0}", "\n"], ' ', $name)));
    }

    public function clear()
    {
        $this->search = '';
        $this->perPage = '10';
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['filterServicio', 'filterContrato', 'filterPago']);
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
}
