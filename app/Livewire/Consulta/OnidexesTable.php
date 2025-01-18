<?php

namespace App\Livewire\Consulta;

use App\Models\Onidex;
use Livewire\WithPagination;
use Livewire\Component;

class OnidexesTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10']
    ];

    public $search = '';
    public $perPage = '10';

    // Variables de búsqueda avanzada
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
        $onidexes = null;
        if(!is_null($this->search) and $this->search != ''){
            // Búsqueda simple
            $onidexes = Onidex::where('apellido1','LIKE',"%$this->search%")
                ->orWhere('apellido2','LIKE',"%$this->search%")
                ->orWhere('nombre1','LIKE',"%$this->search%")
                ->orWhere('nombre2','LIKE',"%$this->search%")
                ->orWhere('cedula','LIKE',"%$this->search%")
                ->orWhere('nacion','LIKE',"%$this->search%")
                ->paginate($this->perPage);
        }else{
            // Búsqueda avanzada
            $onidexes = Onidex::y1nombres($this->nombre1, $this->cbx_nombre1, $this->cbx_nombre)
                            ->y2nombres($this->nombre2, $this->cbx_nombre2)
                            ->y1apellidos($this->apellido1, $this->cbx_apellido1, $this->cbx_apellido)
                            ->y2apellidos($this->apellido2, $this->cbx_apellido2)
                            ->ycedulas($this->cedula, $this->cbx_cedula)
                            ->ynaciones($this->nacion)
                            ->yfechas($this->fec_nac, $this->cbx_anho, $this->cbx_mes, $this->cbx_dia, $this->rangofecha, $this->fechainicial, $this->fechafinal)
                            ->paginate($this->perPage);
        }
        return view('livewire.consulta.onidexes-table',compact('onidexes'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
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
    }
}
