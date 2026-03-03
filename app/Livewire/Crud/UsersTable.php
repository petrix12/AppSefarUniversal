<?php

namespace App\Livewire\Crud;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Servicio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UsersTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10'],
        'filterProveedor' => ['except' => ''],
        'filterOwner' => ['except' => ''],
        'filterServicio' => ['except' => ''],
        'filterContrato' => ['except' => ''],
        'filterPago' => ['except' => ''],
        'filterRoles' => ['except' => []], // ✅ multi
    ];

    public $search = '';
    public $filterServicio = '';
    public $filterContrato = '';
    public $filterPago = '';
    public $perPage = 10;
    public $filterProveedor = '';
    public $filterOwner = '';

    /** ✅ Multi-select roles */
    public array $filterRoles = [];

    /** Cacheados */
    public $listaServicios = [];
    public $serviciosPlano = [];

    public $owners = [];
    public $rolesList = [];

    /** ✅ Modal */
    public bool $proveedorModalOpen = false;
    public array $proveedorModalData = [];

    public function mount()
    {
        $this->loadServiciosOptimized();

        $this->owners = User::whereHas('roles', function ($q) {
            $q->whereIn('id', [15, 17]);
        })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $this->rolesList = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function render()
    {
        $authUser = Auth::user()->load('roles');
        $rolesIds = $authUser->roles->pluck('id')->toArray();

        $query = User::query()
            ->select([
                'id', 'name', 'nombres', 'apellidos', 'email',
                'passport', 'servicio', 'contrato', 'pay', 'created_at',
                'owner_id',
                'estado_vendedor',
                'fecha_activacion_proveedor',

                // ✅ Modal (solo si existen en users)
                'email_2', 'phone', 'pais_de_residencia', 'city', 'address',
                'metodo_pago_preferido', 'motivo_coordinador', 'tiene_contactos_sociales',
            ])
            ->with([
                'compras:id,id_user,servicio_hs_id,pagado',
                'roles:id,name', // ✅ necesario para decidir botones sin queries extra
            ]);

        $isAdmin = in_array(1, $rolesIds);
        $isVentasOrCoord = !empty(array_intersect($rolesIds, [15, 17]));

        /** 🛡️ Si es 15/17 PERO NO es admin => solo ve sus prospectos */
        $query->when(
            $isVentasOrCoord && !$isAdmin,
            fn ($q) => $q->where('owner_id', $authUser->id)
        );

        /** Si NO es 15/17, puede filtrar por owner */
        $query->when(
            empty(array_intersect($rolesIds, [15, 17])) && $this->filterOwner !== '',
            fn ($q) => $q->where('owner_id', $this->filterOwner)
        );

        /** 🔍 búsqueda multi-campo */
        $query->when($this->search, function ($query) {
            $terms = preg_split('/\s+/', trim($this->search));
            foreach ($terms as $term) {
                $query->where(function ($q) use ($term) {
                    $like = "%{$term}%";
                    $q->whereRaw("CONCAT_WS(' ', name, nombres, apellidos, email, passport) LIKE ?", [$like]);
                });
            }
        });

        /** ✅ filtro multi-rol */
        $query->when(!empty($this->filterRoles), function ($q) {
            $roleIds = array_values(array_filter($this->filterRoles, fn ($v) => $v !== '' && $v !== null));
            if (!empty($roleIds)) {
                $q->whereHas('roles', fn ($rq) => $rq->whereIn('id', $roleIds));
            }
        });

        /** filtro servicio */
        $query->when($this->filterServicio !== '', function ($query) {
            $query->where(function ($q) {
                $q->where('servicio', $this->filterServicio)
                    ->orWhereHas('compras', function ($c) {
                        $c->where('servicio_hs_id', $this->filterServicio);
                    });
            });
        });

        /** filtro proveedor */
        $query->when($this->filterProveedor !== '', function ($q) {
            if ($this->filterProveedor === 'pendiente') {
                $q->where('estado_vendedor', 'Pendiente');
            }
        });

        /** filtros simples */
        $query->when($this->filterContrato !== '', fn ($q) => $q->where('contrato', $this->filterContrato))
              ->when($this->filterPago !== '', fn ($q) => $q->where('pay', $this->filterPago));

        logger()->info('AUTH roles', $rolesIds);

        logger()->info('PENDIENTES total', [
        'count' => User::where('estado_vendedor','Pendiente')->count()
        ]);

        logger()->info('PENDIENTES visibles con query', [
        'count' => (clone $query)->where('estado_vendedor','Pendiente')->count()
        ]);

        $users = $query->orderBy('created_at', 'DESC')
            ->paginate($this->perPage);

        return view('livewire.crud.users-table', [
            'users' => $users,
            'listaServicios' => $this->listaServicios,
            'serviciosPlano' => $this->serviciosPlano,
            'rolesList' => $this->rolesList,
            'owners' => $this->owners,
        ]);
    }

    /** ✅ Modal */
    public function showProveedorModal(int $userId)
    {
        // define quién puede ver el modal (admin o coord ventas, ajusta si quieres)
        $auth = Auth::user()->load('roles');
        $rolesIds = $auth->roles->pluck('id')->toArray();
        if (!in_array(1, $rolesIds) && !in_array(17, $rolesIds)) abort(403);

        $u = User::query()
            ->select([
                'id', 'name', 'email', 'email_2', 'phone',
                'pais_de_residencia', 'city', 'address',
                'metodo_pago_preferido', 'motivo_coordinador', 'tiene_contactos_sociales',
                'estado_vendedor', 'fecha_activacion_proveedor', 'created_at',
            ])
            ->findOrFail($userId);

        $this->proveedorModalData = [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'email_2' => $u->email_2,
            'phone' => $u->phone,
            'pais' => $u->pais_de_residencia,
            'city' => $u->city,
            'address' => $u->address,
            'metodo_pago' => $u->metodo_pago_preferido,
            'motivo' => $u->motivo_coordinador,
            'social' => (int) $u->tiene_contactos_sociales,
            'estado' => $u->estado_vendedor,
            'created_at' => optional($u->created_at)->format('Y-m-d H:i'),
        ];

        $this->proveedorModalOpen = true;
    }

    public function closeProveedorModal()
    {
        $this->proveedorModalOpen = false;
        $this->proveedorModalData = [];
    }

    /** ✅ Aprobación / rechazo (solo admin por ahora) */
    public function approveProveedor(int $userId)
    {
        $auth = Auth::user()->load('roles');
        if (!$auth->roles->pluck('id')->contains(1)) abort(403);

        $user = User::findOrFail($userId);
        if ($user->estado_vendedor !== 'Pendiente') return;

        DB::transaction(function () use ($user) {
            $user->estado_vendedor = null;
            $user->fecha_activacion_proveedor = now();
            $user->save();
        });

        $this->dispatch('swal:toast', [
            'icon' => 'success',
            'title' => 'Proveedor aprobado.',
        ]);
    }

    public function rejectProveedor(int $userId)
    {
        $auth = Auth::user()->load('roles');
        if (!$auth->roles->pluck('id')->contains(1)) abort(403);

        $user = User::findOrFail($userId);
        if ($user->estado_vendedor !== 'Pendiente') return;

        DB::transaction(function () use ($user) {
            if (method_exists($user, 'forceDelete')) $user->forceDelete();
            else $user->delete();
        });

        $this->dispatch('swal:toast', [
            'icon' => 'success',
            'title' => 'Proveedor rechazado y eliminado.',
        ]);
    }

    protected function loadServiciosOptimized()
    {
        [$this->listaServicios, $this->serviciosPlano] = Cache::remember('servicios_agrupados_opt', 3600, function () {
            $serviciostabla = Servicio::query()
                ->where('tipov', 0)
                ->where('id_hubspot', 'not like', '% - Hermano%')
                ->pluck('id_hubspot')
                ->map(fn($s) => $this->normalizeServiceName($s))
                ->toArray();

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

            $gruposNorm = collect($grupos)->map(function ($items) {
                return array_map([$this, 'normalizeServiceName'], $items);
            });

            $listaAgrupada = [];
            foreach ($gruposNorm as $categoria => $items) {
                $coinciden = array_values(array_intersect($items, $serviciostabla));
                if (!empty($coinciden)) {
                    $listaAgrupada[$categoria] = $coinciden;
                }
            }

            $todos = array_merge(...array_values($gruposNorm->toArray()));
            $sinCategoria = array_diff($serviciostabla, $todos);

            if (!empty($sinCategoria)) {
                $listaAgrupada['Otros'] = array_merge(
                    $listaAgrupada['Otros'] ?? [],
                    array_values($sinCategoria)
                );
            }

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
        $this->reset([
            'filterServicio',
            'filterContrato',
            'filterPago',
            'filterProveedor',
            'filterOwner',
            'filterRoles',
        ]);
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
}
