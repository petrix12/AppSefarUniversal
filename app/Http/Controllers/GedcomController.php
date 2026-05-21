<?php

namespace App\Http\Controllers;

use App\Exports\TreeExport;
use App\Models\Agcliente;
use App\Services\GedcomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;
use RuntimeException;

class GedcomController extends Controller
{
    public function __construct(private GedcomService $gedcomService)
    {
    }

    public function getExcelCliente(Request $request)
    {
        $arrayGeneraciones = [
            'Cliente',
            'Padres',
            'Abuelos',
            'Bisabuelos',
            'Tatarabuelos',
            'Trastatarabuelos',
            'Cuartabuelos',
            'Quintabuelos',
            'Sextabuelos',
            'Septabuelos',
            'Octabuelos',
            'Nonabuelos',
            'Decabuelos',
        ];

        $datacliente = json_decode(json_encode(Agcliente::where('id', $request->id)->select('id', 'idPadreNew', 'idMadreNew', 'Nombres', 'Apellidos', 'NPasaporte', 'PaisPasaporte', 'NDocIdent', 'PaisDocIdent', 'Sexo', 'AnhoNac', 'MesNac', 'DiaNac', 'LugarNac', 'PaisNac', 'AnhoBtzo', 'MesBtzo', 'DiaBtzo', 'LugarBtzo', 'PaisBtzo', 'AnhoMatr', 'MesMatr', 'DiaMatr', 'LugarMatr', 'PaisMatr', 'AnhoDef', 'MesDef', 'DiaDef', 'LugarDef', 'PaisDef', 'Observaciones', 'created_at')->first()), true);
        $people = [];

        $getGenerations = function ($person, $generationIndex) use (&$getGenerations, &$people, $arrayGeneraciones) {
            if ($generationIndex >= count($arrayGeneraciones) || empty($person)) {
                return;
            }

            $person['generacion'] = $arrayGeneraciones[$generationIndex];
            $people[] = $person;

            if (!empty($person['idPadreNew'])) {
                $father = Agcliente::where('id', $person['idPadreNew'])->select('id', 'idPadreNew', 'idMadreNew', 'Nombres', 'Apellidos', 'NPasaporte', 'PaisPasaporte', 'NDocIdent', 'PaisDocIdent', 'Sexo', 'AnhoNac', 'MesNac', 'DiaNac', 'LugarNac', 'PaisNac', 'AnhoBtzo', 'MesBtzo', 'DiaBtzo', 'LugarBtzo', 'PaisBtzo', 'AnhoMatr', 'MesMatr', 'DiaMatr', 'LugarMatr', 'PaisMatr', 'AnhoDef', 'MesDef', 'DiaDef', 'LugarDef', 'PaisDef', 'Observaciones', 'created_at')->first();
                if ($father) {
                    $getGenerations($father->toArray(), $generationIndex + 1);
                }
            }

            if (!empty($person['idMadreNew'])) {
                $mother = Agcliente::where('id', $person['idMadreNew'])->select('id', 'idPadreNew', 'idMadreNew', 'Nombres', 'Apellidos', 'NPasaporte', 'PaisPasaporte', 'NDocIdent', 'PaisDocIdent', 'Sexo', 'AnhoNac', 'MesNac', 'DiaNac', 'LugarNac', 'PaisNac', 'AnhoBtzo', 'MesBtzo', 'DiaBtzo', 'LugarBtzo', 'PaisBtzo', 'AnhoMatr', 'MesMatr', 'DiaMatr', 'LugarMatr', 'PaisMatr', 'AnhoDef', 'MesDef', 'DiaDef', 'LugarDef', 'PaisDef', 'Observaciones', 'created_at')->first();
                if ($mother) {
                    $getGenerations($mother->toArray(), $generationIndex + 1);
                }
            }
        };

        $getGenerations($datacliente, 0);

        return Excel::download(new TreeExport($people), 'Arbol Genealogico - ' . $request->id . '.xlsx');
    }

    public function getGedcomCliente(Request $request, int|string $id)
    {
        $root = Agcliente::findOrFail($id);
        $gedcom = $this->gedcomService->exportClientByAgclienteId($id);

        return $this->downloadGedcom($gedcom, 'ARBOL CLIENTE ' . $root->IDCliente . '.ged');
    }

    public function gedcomexport()
    {
        return view('gedcom.global', [
            'gedcomResult' => session('gedcom_result'),
            'gedcomErrors' => session('gedcom_errors', []),
            'gedcomWarnings' => session('gedcom_warnings', []),
            'gedcomPreview' => session('gedcom_preview', []),
        ]);
    }

    public function getGedcomGlobal()
    {
        return $this->downloadGedcom($this->gedcomService->exportGlobal(), 'AppSefarGlobal.ged');
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'gedcom_file' => 'required|file|max:51200',
            'IDCliente' => 'nullable|string|max:175',
            'mode' => 'required|in:validate,import',
            'replace_existing' => 'nullable|boolean',
        ]);

        $gedcom = file_get_contents($request->file('gedcom_file')->getRealPath());
        $warnings = [];
        $preview = [];

        try {
            if ($validated['mode'] === 'validate') {
                $result = $this->gedcomService->validate($gedcom);
                $warnings = $result['warnings'] ?? [];
                $preview = $result['preview'] ?? [];

                if (!empty($result['errors'])) {
                    Alert::warning('GEDCOM revisado', 'El archivo tiene errores que debes corregir antes de importarlo.');
                } else {
                    Alert::success('GEDCOM valido', 'El archivo se puede convertir a agclientes.');
                }

                return back()->with([
                    'gedcom_result' => $result,
                    'gedcom_errors' => $result['errors'] ?? [],
                    'gedcom_warnings' => $warnings,
                    'gedcom_preview' => $preview,
                ]);
            }

            $idCliente = trim((string) ($validated['IDCliente'] ?? ''));
            $result = $this->gedcomService->import(
                $gedcom,
                $idCliente,
                (bool) $request->boolean('replace_existing')
            );

            Alert::success('GEDCOM importado', 'Se crearon ' . $result['created'] . ' persona(s) en agclientes.');

            return back()->with([
                'gedcom_result' => $result,
                'gedcom_errors' => [],
                'gedcom_warnings' => $result['warnings'] ?? [],
                'gedcom_preview' => [],
            ]);
        } catch (RuntimeException $exception) {
            Alert::error('No se pudo importar', $exception->getMessage());

            return back()
                ->withInput()
                ->with([
                    'gedcom_result' => null,
                    'gedcom_errors' => [$exception->getMessage()],
                    'gedcom_warnings' => $warnings,
                    'gedcom_preview' => $preview,
                ]);
        }
    }

    private function downloadGedcom(string $gedcom, string $filename)
    {
        return Response::make($gedcom, 200, [
            'Content-Type' => 'text/vnd.gedcom; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
