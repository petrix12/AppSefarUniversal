<?php

namespace App\Http\Controllers;

use App\Exports\ClientRegistrationExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class AdminClientExportController extends Controller
{
    public function listing(Request $request)
    {
        [$filters, $export] = $this->exportFor($request);
        $counts = User::role('Cliente')
            ->when(\Schema::hasTable('user_access_blocks'), fn ($q) => $q->whereDoesntHave('accessBlock'))
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN pay = 0 THEN 1 ELSE 0 END), 0) as unpaid')
            ->selectRaw('COALESCE(SUM(CASE WHEN pay = 1 THEN 1 ELSE 0 END), 0) as paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN pay = 2 AND contrato = 1 THEN 1 ELSE 0 END), 0) as complete')
            ->first();
        $clients = $export->query()->paginate(25)->withQueryString();

        return response()->view('admin.client-exports', [
            'filters' => $filters, 'groups' => ClientRegistrationExport::GROUPS,
            'counts' => $counts, 'clients' => $clients,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request)
    {
        [$filters, $export] = $this->exportFor($request);
        $filename = 'clientes-'.$filters['group'].'-'.now()->format('Y-m-d-His');

        if ($filters['format'] === 'csv') {
            return response()->streamDownload(function () use ($export): void {
                $stream = fopen('php://output', 'w');
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, $export->headings(), ',', '"', '');
                foreach ($export->query()->lazyById(1000, 'users.id', 'id') as $client) {
                    fputcsv($stream, $export->csvRow($client), ',', '"', '');
                }
                fclose($stream);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        return Excel::download($export, $filename.'.xlsx', ExcelFormat::XLSX, ['Cache-Control' => 'private, no-store']);
    }

    private function exportFor(Request $request): array
    {
        // A broad/custom permission grant must not turn this into a general user export.
        abort_unless($request->user()?->hasRole('Administrador') && ! $request->user()->hasRole('Cliente'), 403);
        $filters = $request->validate([
            'group' => ['nullable', Rule::in(array_keys(ClientRegistrationExport::GROUPS))],
            'q' => ['nullable', 'string', 'max:200'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters = [
            'group' => $filters['group'] ?? 'all',
            'q' => trim($filters['q'] ?? ''),
            'format' => $filters['format'] ?? 'xlsx',
        ];

        return [$filters, new ClientRegistrationExport($filters['group'], $filters['q'])];
    }
}
