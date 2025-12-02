<?php

namespace App\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\User;
use App\Models\Compras;
use App\Models\Servicio;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;

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
        // Cache de servicios agrupados por 60 minutos
        $serviciosData = Cache::remember('servicios_agrupados', 3600, function () {
            return $this->buildServiciosData();
        });

        $users = $this->buildUsersQuery()->paginate($this->perPage);

        return view('livewire.crud.users-table', [
            'users' => $users,
            'listaServicios' => $serviciosData['listaAgrupada'],
            'serviciosPlano' => $serviciosData['serviciosParaFiltro'],
            'serviciosParaFiltro' => $serviciosData['serviciosParaFiltro']
        ]);
    }

    protected function buildUsersQuery()
    {
        $query = User::query();

        // Optimizar búsqueda con índices
        if (!empty($this->search)) {
            $terms = explode(' ', trim($this->search));
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    if (strlen($term) >= 2) { // Evitar búsquedas muy cortas
                        $q->where(function ($sub) use ($term) {
                            $sub->where('name', 'LIKE', "%{$term}%")
                                ->orWhere('nombres', 'LIKE', "%{$term}%")
                                ->orWhere('apellidos', 'LIKE', "%{$term}%")
                                ->orWhere('email', 'LIKE', "%{$term}%")
                                ->orWhere('passport', 'LIKE', "%{$term}%");
                        });
                    }
                }
            });
        }

        // Filtro de servicio optimizado
        if ($this->filterServicio !== '') {
            $query->where(function ($q) {
                $q->where('servicio', $this->filterServicio)
                  ->orWhereExists(function ($sub) {
                      $sub->selectRaw(1)
                          ->from('compras')
                          ->whereColumn('compras.id_user', 'users.id')
                          ->where('compras.servicio_hs_id', $this->filterServicio);
                  });
            });
        }

        // Otros filtros
        if ($this->filterContrato !== '') {
            $query->where('contrato', $this->filterContrato);
        }

        if ($this->filterPago !== '') {
            $query->where('pay', $this->filterPago);
        }

        return $query->orderBy('created_at', 'DESC');
    }

    protected function buildServiciosData()
    {
        // Obtener servicios de forma más eficiente
        $servicios = Servicio::select('id_hubspot')
            ->where('tipov', 0)
            ->where('id_hubspot', 'not like', '% - Hermano%')
            ->pluck('id_hubspot')
            ->map(function ($servicio) {
                return $this->normalizeServiceName($servicio);
            })
            ->unique()
            ->values()
            ->toArray();

        // Configuración estática de servicios agrupados
        $serviciosAgrupados = $this->getServiciosAgrupados();

        // Procesar agrupación
        $listaAgrupada = [];
        foreach ($serviciosAgrupados as $categoria => $serviciosCategoria) {
            $serviciosNormalizados = array_map([$this, 'normalizeServiceName'], $serviciosCategoria);
            $serviciosFiltrados = array_intersect($serviciosNormalizados, $servicios);

            if (!empty($serviciosFiltrados)) {
                $listaAgrupada[$categoria] = array_values($serviciosFiltrados);
            }
        }

        // Servicios sin categoría
        $todosServiciosAgrupados = array_merge(...array_values($serviciosAgrupados));
        $todosServiciosAgrupados = array_map([$this, 'normalizeServiceName'], $todosServiciosAgrupados);
        $serviciosSinCategoria = array_diff($servicios, $todosServiciosAgrupados);

        if (!empty($serviciosSinCategoria)) {
            $listaAgrupada['Otros'] = array_merge(
                $listaAgrupada['Otros'] ?? [],
                array_values($serviciosSinCategoria)
            );
        }

        // Crear lista plana para filtros
        $serviciosParaFiltro = [];
        foreach ($listaAgrupada as $categoria => $serviciosLista) {
            foreach ($serviciosLista as $servicio) {
                $serviciosParaFiltro[$servicio] = "[$categoria] $servicio";
            }
        }
        asort($serviciosParaFiltro);

        return [
            'listaAgrupada' => $listaAgrupada,
            'serviciosParaFiltro' => $serviciosParaFiltro
        ];
    }

    protected function getServiciosAgrupados()
    {
        return [
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
    }

    protected function normalizeServiceName(string $name): string
    {
        static $correcciones = [
            'Diagnóstico Express para Plan de acción de la Nacionalidad Italiana' => 'Diagnóstico Express para Plan de acción de la Nacionalidad Italiana',
        ];

        $normalized = trim(preg_replace('/\s+/', ' ', $name));
        $normalized = str_replace(["\u{A0}", "\n"], ' ', $normalized);

        return $correcciones[$normalized] ?? $normalized;
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
        $this->dispatch('filtersCleared');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    // Limpiar cache cuando sea necesario
    public function refreshServicios()
    {
        Cache::forget('servicios_agrupados');
        $this->dispatch('serviciosRefreshed');
    }
}
