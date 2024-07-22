<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class TreeExport implements FromCollection, WithHeadings, WithMapping
{
    protected $people;

    public function __construct(array $people)
    {
        $this->people = $people;
    }

    public function collection()
    {
        return collect($this->people); 
    }

    public function headings(): array
    {
        return [
            'Nombres', 'Apellidos', 'Número de Pasaporte', 'Fecha de Registro', 'Generación',
            'País de Emisión de Pasaporte', 'Número de Documento de Identificación', 
            'País de Emisión de Documento de Identificación', 'Sexo', 'Fecha de Nacimiento', 
            'Lugar de Nacimiento', 'País de Nacimiento', 'Fecha de Bautizo', 'Lugar de Bautizo',
            'País de Bautizo', 'Fecha de Matrimonio', 'Lugar de Matrimonio', 'País de Matrimonio',
            'Fecha de Defunción', 'Lugar de Defunción', 'País de Defunción', 'Observaciones'
        ];
    }

    public function map($person): array
    {
        return [
            $person['Nombres'],
            $person['Apellidos'],
            $person['NPasaporte'],
            Carbon::parse($person['created_at'])->format('d/m/Y'), // Formateo de fecha
            $person['generacion'],
            $person['PaisPasaporte'],
            $person['NDocIdent'],
            $person['PaisDocIdent'],
            $person['Sexo'],
            $person['DiaNac'] . '/' . $person['MesNac'] . '/' . $person['AnhoNac'],
            $person['LugarNac'],
            $person['PaisNac'],
            $person['DiaBtzo'] . '/' . $person['MesBtzo'] . '/' . $person['AnhoBtzo'],
            $person['LugarBtzo'],
            $person['PaisBtzo'],
            $person['DiaMatr'] . '/' . $person['MesMatr'] . '/' . $person['AnhoMatr'],
            $person['LugarMatr'],
            $person['PaisMatr'],
            $person['DiaDef'] . '/' . $person['MesDef'] . '/' . $person['AnhoDef'],
            $person['LugarDef'],
            $person['PaisDef'],
            $person['Observaciones']
        ];
    }
}