<?php

namespace App\Livewire\Consulta;

use App\Models\Onidex;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Component;
use Livewire\WithPagination;

class OnidexesTable extends Component
{
    use WithPagination;

    private const PER_PAGE_OPTIONS = [5, 10, 15, 25, 50, 100];

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '100'],
    ];

    public $search = '';
    public $perPage = '100';

    // Variables de busqueda avanzada
    public $nombre1;
    public $nombre2;
    public $apellido1;
    public $apellido2;
    public $cedula;
    public $nacion;
    public $cbx_nombre1;
    public $cbx_nombre2;
    public $cbx_apellido1;
    public $cbx_apellido2;
    public $cbx_nombre;
    public $cbx_apellido;
    public $cbx_cedula;
    public $fec_nac;
    public $cbx_anho;
    public $cbx_mes;
    public $cbx_dia;
    public $rangofecha;
    public $fechainicial;
    public $fechafinal;

    public function render()
    {
        $hasSearchCriteria = $this->hasSearchCriteria();
        $onidexes = $hasSearchCriteria
            ? $this->resultsQuery()->simplePaginate($this->perPageValue())
            : $this->emptyResults();

        return view('livewire.consulta.onidexes-table', [
            'onidexes' => $onidexes,
            'currentPage' => $onidexes->currentPage(),
            'hasSearchCriteria' => $hasSearchCriteria,
        ]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    private function resultsQuery()
    {
        $search = trim((string) $this->search);

        if ($search !== '') {
            return Onidex::query()
                ->where(function ($query) use ($search) {
                    $like = $this->prefixLike($search);

                    $query->where('apellido1', 'LIKE', $like)
                        ->orWhere('apellido2', 'LIKE', $like)
                        ->orWhere('nombre1', 'LIKE', $like)
                        ->orWhere('nombre2', 'LIKE', $like)
                        ->orWhere('nacion', 'LIKE', $like);

                    if (ctype_digit($search)) {
                        $query->orWhere('cedula', (int) $search);
                    }
                });
        }

        return Onidex::query()
            ->y1nombres($this->nombre1, $this->cbx_nombre1, $this->cbx_nombre)
            ->y2nombres($this->nombre2, $this->cbx_nombre2)
            ->y1apellidos($this->apellido1, $this->cbx_apellido1, $this->cbx_apellido)
            ->y2apellidos($this->apellido2, $this->cbx_apellido2)
            ->ycedulas($this->cedula, $this->cbx_cedula)
            ->ynaciones($this->nacion)
            ->yfechas($this->fec_nac, $this->cbx_anho, $this->cbx_mes, $this->cbx_dia, $this->rangofecha, $this->fechainicial, $this->fechafinal);
    }

    private function hasSearchCriteria(): bool
    {
        foreach ([
            $this->search,
            $this->nombre1,
            $this->nombre2,
            $this->apellido1,
            $this->apellido2,
            $this->cedula,
            $this->nacion,
            $this->fec_nac,
        ] as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return (bool) $this->rangofecha
            && trim((string) $this->fechainicial) !== ''
            && trim((string) $this->fechafinal) !== '';
    }

    private function emptyResults(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            $this->perPageValue(),
            Paginator::resolveCurrentPage(),
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    private function perPageValue(): int
    {
        $perPage = (int) $this->perPage;

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 100;
    }

    private function prefixLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value)) . '%';
    }

    public function clear()
    {
        $this->search = '';
        $this->perPage = '100';
        $this->nombre1 = '';
        $this->nombre2 = '';
        $this->apellido1 = '';
        $this->apellido2 = '';
        $this->cedula = '';
        $this->nacion = '';
        $this->cbx_nombre1 = '';
        $this->cbx_nombre2 = '';
        $this->cbx_apellido1 = '';
        $this->cbx_apellido2 = '';
        $this->cbx_nombre = '';
        $this->cbx_apellido = '';
        $this->cbx_cedula = '';
        $this->fec_nac = '';
        $this->cbx_anho = '';
        $this->cbx_mes = '';
        $this->cbx_dia = '';
        $this->rangofecha = '';
        $this->fechainicial = '';
        $this->fechafinal = '';

        $this->resetPage();
    }
}
