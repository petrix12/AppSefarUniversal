<?php

namespace App\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\User;
use App\Models\Compras;
use App\Models\Servicio;
use Livewire\Component;

class UsersTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10']
    ];

    public $search = '';
    public $filterServicio = '';
    public $filterContrato = '';
    public $filterPago = '';
    public $perPage = 10;

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'LIKE', "%{$this->search}%")
                    ->orWhere('email', 'LIKE', "%{$this->search}%")
                    ->orWhere('passport', 'LIKE', "%{$this->search}%");
                });
            })
            ->when($this->filterServicio !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('servicio', $this->filterServicio)
                    ->orWhereIn('id', function ($sub) {
                        $sub->select('id_user')
                            ->from('compras')
                            ->where('servicio_hs_id', $this->filterServicio);
                    });
                });
            })
            ->when($this->filterContrato !== '', function ($query) {
                $query->where('contrato', $this->filterContrato);
            })
            ->when($this->filterPago !== '', function ($query) {
                $query->where('pay', $this->filterPago);
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($this->perPage);

        // Obtener y normalizar servicios desde la tabla de servicios
        $tablaservicios = Servicio::select("id_hubspot")
            ->where("tipov", 0)
            ->where("id_hubspot", "not like", "% - Hermano%")
            ->get()
            ->map(function ($servicio) {
                return [
                    'id_hubspot' => $this->normalizeServiceName($servicio->id_hubspot)
                ];
            })
            ->toArray();

        $serviciostabla = array_column($tablaservicios, 'id_hubspot');

        // Definir cómo agrupar los servicios (con nombres normalizados)
        $serviciosAgrupados = [
            'Nacionalidad Española' => [
                'Española LMD',
                'Española Sefardi',
                'Española Sefardi - Subsanación',
                'Española - Carta de Naturaleza',
                'Formalizacion Anticipada Ley de Memoria Democrática'
            ],
            'Nacionalidad Portuguesa' => [
                'Portuguesa Sefardi',
                'Portuguesa Sefardi - Subsanación',
                'Formalizacion Anticipada Portuguesa Sefardi',
                'Certificación de Documentos - Portugal'
            ],
            'Nacionalidad Italiana' => [
                'Italiana',
                'Diagnóstico Express para Plan de acción de la Nacionalidad Italiana'
            ],
            'Otros' => [
                'Análisis por semana',
                'Recurso de Alzada',
                'Gestión Documental',
                'Acumulación de linajes',
                'Árbol genealógico de Deslinde',
                'Procedimiento de Urgencia',
                'Analisis Juridico Genealogico'
            ]
        ];

        // Normalizar nombres en la estructura de agrupación
        $serviciosAgrupados = array_map(function($servicios) {
            return array_map([$this, 'normalizeServiceName'], $servicios);
        }, $serviciosAgrupados);

        // Filtrar y agrupar los servicios
        $listaAgrupada = [];
        foreach ($serviciosAgrupados as $categoria => $servicios) {
            $serviciosFiltrados = array_uintersect(
                $servicios,
                $serviciostabla,
                fn($a, $b) => strcmp($this->normalizeServiceName($a), $this->normalizeServiceName($b))
            );

            if (!empty($serviciosFiltrados)) {
                $listaAgrupada[$categoria] = array_values($serviciosFiltrados);
            }
        }

        // Agregar servicios no clasificados a 'Otros'
        $todosServiciosAgrupados = array_merge(...array_values($serviciosAgrupados));
        $serviciosSinCategoria = array_diff($serviciostabla, $todosServiciosAgrupados);

        if (!empty($serviciosSinCategoria)) {
            $listaAgrupada['Otros'] = array_merge(
                $listaAgrupada['Otros'] ?? [],
                array_values($serviciosSinCategoria)
            );
        }

        // Crear versión plana para el filtro select
        $serviciosParaFiltro = [];
        foreach ($listaAgrupada as $categoria => $servicios) {
            foreach ($servicios as $servicio) {
                $serviciosParaFiltro[$servicio] = "[$categoria] $servicio";
            }
        }
        asort($serviciosParaFiltro);

        return view('livewire.crud.users-table', [
            'users' => $users,
            'compras' => Compras::all(),
            'listaServicios' => $listaAgrupada,       // Para mostrar agrupados
            'serviciosPlano' => $serviciosParaFiltro, // Para el select (formato plano)
            'serviciosParaFiltro' => $serviciosParaFiltro // Mantener compatibilidad
        ]);
    }

    protected function normalizeServiceName(string $name): string
    {
        // Eliminar caracteres especiales y normalizar espacios
        $normalized = trim(preg_replace('/\s+/', ' ', $name));
        $normalized = str_replace(["\u{A0}", "\n"], ' ', $normalized);

        // Correcciones específicas para nombres conocidos
        $correcciones = [
            'Diagnóstico Express para Plan de acción de la Nacionalidad Italiana' => 'Diagnóstico Express para Plan de acción de la Nacionalidad Italiana',
        ];

        return $correcciones[$normalized] ?? $normalized;
    }

    public function clear(){
        $this->search = '';
        $this->perPage = '10';
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['filterServicio', 'filterContrato', 'filterPago']);
        $this->resetPage(); // Reset pagination
        $this->dispatch('filtersCleared'); // Optional: Dispatch an event to notify the frontend
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
}
