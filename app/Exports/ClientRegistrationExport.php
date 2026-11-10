<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientRegistrationExport extends StringValueBinder implements FromQuery, WithMapping, WithHeadings, WithCustomValueBinder, WithColumnWidths, WithStyles
{
    public const GROUPS = [
        'all' => 'Todos los clientes',
        'unpaid' => 'Sin pago de registro (PAY=0)',
        'paid' => 'Registro pagado (PAY=1)',
        'complete' => 'Información y contrato completos (PAY=2, CONTRATO=1)',
    ];

    public function __construct(public readonly string $group = 'all', public readonly string $search = '')
    {
    }

    public function query(): Builder
    {
        $query = User::role('Cliente')->select([
            'users.id', 'name', 'nombres', 'apellidos', 'email', 'phone',
            'passport', 'servicio', 'pay', 'contrato', 'users.created_at',
        ]);

        match ($this->group) {
            'unpaid' => $query->where('pay', 0),
            'paid' => $query->where('pay', 1),
            'complete' => $query->where('pay', 2)->where('contrato', 1),
            default => null,
        };

        foreach (preg_split('/\s+/u', trim($this->search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $query->where(function (Builder $query) use ($term): void {
                foreach (['name', 'nombres', 'apellidos', 'email', 'phone', 'passport'] as $field) {
                    $query->orWhere($field, 'like', '%'.$term.'%');
                }
            });
        }

        return $query->orderBy('users.id');
    }

    public function headings(): array
    {
        return ['ID', 'Nombre de cuenta', 'Nombres', 'Apellidos', 'Correo', 'Teléfono',
            'Pasaporte / documento', 'Servicio', 'PAY', 'CONTRATO', 'Estado del registro', 'Fecha de registro'];
    }

    public function map($client): array
    {
        return [
            $client->id, $client->name, $client->nombres, $client->apellidos,
            $client->email, $client->phone, $client->passport, $client->servicio,
            $client->pay, $client->getRawOriginal('contrato'), self::registrationStatus($client),
            $client->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function csvRow(User $client): array
    {
        return array_map(static fn ($value) => is_string($value) && preg_match('/^[\s\x{FEFF}]*[=+\-@]/u', $value)
            ? "'".$value : $value, $this->map($client));
    }

    public static function registrationStatus(User $client): string
    {
        if ($client->pay === null) {
            return 'Sin estado de pago';
        }

        return match ((int) $client->pay) {
            0 => 'Sin pago de registro',
            1 => 'Registro pagado, información pendiente',
            2 => $client->contrato ? 'Información y contrato completos' : 'Información completa, contrato pendiente',
            default => 'Otro estado (PAY='.$client->pay.')',
        };
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 30, 'C' => 25, 'D' => 25, 'E' => 38, 'F' => 23,
            'G' => 25, 'H' => 38, 'I' => 10, 'J' => 12, 'K' => 48, 'L' => 23];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [1 => ['font' => ['bold' => true]]];
    }
}
