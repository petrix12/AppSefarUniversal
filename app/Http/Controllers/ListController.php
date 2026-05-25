<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\Lista;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class ListController extends Controller
{

    private function isVentasUser(): bool
    {
        $u = auth()->user();
        return $u && $u->hasAnyRole(['Coord. de Nacionalidad y Genealogía', 'Ventas']);
    }

    public function get(Request $request)
    {
        $q = trim((string)$request->get('q'));
        $isVentas = $this->isVentasUser();
        $userId = auth()->id();

        $lists = Lista::query()
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))

            // ✅ FILTRO POR ROL VENTAS (qué listas ve)
            ->when($isVentas, function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('owner_id', $userId)
                    ->orWhereHas('users', function ($sub) use ($userId) {
                        $sub->where('users.owner_id', $userId);
                    });
                });
            })

            // ✅ COUNT condicionado (cuántos miembros ve en esa lista)
            ->when($isVentas, function ($query) use ($userId) {
                $query->withCount([
                    'users as users_count' => function ($sub) use ($userId) {
                        $sub->where('users.owner_id', $userId);
                    }
                ]);
            }, function ($query) {
                // para admins / otros roles: count total normal
                $query->withCount('users');
            })

            ->latest()
            ->paginate(15);

        return view('crud.lists.index', compact('lists', 'q'));
    }

    public function create()
    {
        $owners = User::query()->orderBy('name')->get(['id','name','email']);
        return view('crud.lists.create', compact('owners'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'include_in_task_pool' => 'nullable|boolean',
            'disable_hubspot_reassignment' => 'nullable|boolean',
        ]);

        $data['created_by'] = auth()->id();
        $data['include_in_task_pool'] = $request->boolean('include_in_task_pool', true);
        $data['disable_hubspot_reassignment'] = $request->boolean('disable_hubspot_reassignment');

        $lista = Lista::create($data);

        return redirect()->route('crud.lists.show', $lista)->with('success', 'Lista creada.');
    }

    public function show(Request $request, Lista $lista)
    {
        $filter = $request->get('filter');
        $q = trim((string)$request->get('q'));

        $members = $lista->users()
            ->with([
                'compras:id,id_user,servicio_hs_id',
            ])
            ->when($this->isVentasUser(), function ($qq) {
                $qq->where('users.owner_id', auth()->id());
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('passport', 'like', "%{$q}%");
                });
            })
            ->when($filter === 'contacted', fn($qq) => $qq->wherePivot('contacted', true))
            ->when($filter === 'not_contacted', fn($qq) => $qq->wherePivot('contacted', false))
            ->orderBy('name')
            ->paginate(20);

        return view('crud.lists.show', compact('lista', 'members', 'filter', 'q'));
    }

    private function authorizeVentasMember(User $user): void
    {
        if ($this->isVentasUser() && (int)$user->owner_id !== (int)auth()->id()) {
            abort(403);
        }
    }

    public function edit(Lista $lista)
    {
        $owners = User::query()->orderBy('name')->get(['id','name','email']);
        return view('crud.lists.edit', compact('lista', 'owners'));
    }

    public function update(Request $request, Lista $lista)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'include_in_task_pool' => 'nullable|boolean',
            'disable_hubspot_reassignment' => 'nullable|boolean',
        ]);

        $data['include_in_task_pool'] = $request->boolean('include_in_task_pool');
        $data['disable_hubspot_reassignment'] = $request->boolean('disable_hubspot_reassignment');

        $lista->update($data);

        return redirect()->route('crud.lists.show', $lista)->with('success', 'Lista actualizada.');
    }

    public function destroy(Lista $lista)
    {
        $lista->delete();
        return redirect()->route('crud.lists.index')->with('success', 'Lista eliminada.');
    }

    /**
     * Agregar miembros por IDs.
     * body: user_ids[] (array)
     */
    public function addMembers(Request $request, Lista $lista)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        // attach sin duplicar: syncWithoutDetaching
        $attach = [];
        foreach ($data['user_ids'] as $uid) {
            $attach[$uid] = [
                'contacted' => false,
                'contacted_at' => null,
                'contact_note' => null,
            ];
        }

        $lista->users()->syncWithoutDetaching($attach);

        return back()->with('success', 'Usuarios añadidos a la lista.');
    }

    public function previewMembersImport(Request $request, Lista $lista)
    {
        $request->validate([
            'contacts_file' => ['required', 'file', 'max:5120'],
        ]);

        try {
            $parsed = $this->parseContactImportFile($request->file('contacts_file'));
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        if (empty($parsed['rows']) || empty($parsed['columns'])) {
            return back()
                ->withInput()
                ->with('error', 'No se detectaron contactos validos en el archivo.');
        }

        $payload = [
            'filename' => $request->file('contacts_file')->getClientOriginalName(),
            'columns' => $parsed['columns'],
            'rows' => $parsed['rows'],
            'created_at' => now()->toIso8601String(),
            'created_by' => auth()->id(),
        ];

        $token = $this->storeImportPayload($payload);

        return back()
            ->with('success', 'Archivo detectado. Revisa el mapeo de campos antes de importar.')
            ->with('import_preview', $this->makeImportPreview($token, $payload));
    }

    public function processMembersImport(Request $request, Lista $lista)
    {
        $data = $request->validate([
            'import_token' => ['required', 'string', 'max:100'],
            'mapping' => ['required', 'array'],
            'mapping.passport' => ['nullable', 'string', 'max:255'],
            'mapping.nombres' => ['nullable', 'string', 'max:255'],
            'mapping.apellidos' => ['nullable', 'string', 'max:255'],
            'mapping.servicio' => ['nullable', 'string', 'max:255'],
            'mapping.phone' => ['nullable', 'string', 'max:255'],
            'mapping.email' => ['nullable', 'string', 'max:255'],
            'create_missing' => ['nullable', 'boolean'],
        ]);

        try {
            $payload = $this->loadImportPayload($data['import_token']);
        } catch (\Throwable $e) {
            return back()->with('error', 'La previsualizacion del archivo expiro. Vuelve a cargar el CSV o JSON.');
        }

        $columns = $payload['columns'] ?? [];
        $mapping = $this->cleanImportMapping($data['mapping'] ?? [], $columns);

        if (empty($mapping['passport'])) {
            return back()
                ->with('error', 'Selecciona la columna que contiene el pasaporte.')
                ->with('import_preview', $this->makeImportPreview($data['import_token'], $payload, $mapping));
        }

        $rows = $payload['rows'] ?? [];
        $createMissing = $request->boolean('create_missing');
        $report = [
            'total_input' => count($rows),
            'with_passport' => 0,
            'unique_passports' => 0,
            'duplicates' => 0,
            'found' => 0,
            'created' => 0,
            'added' => 0,
            'already' => 0,
            'not_found' => 0,
            'missing_required' => 0,
            'email_conflicts' => 0,
            'invalid_email' => 0,
            'empty_passport' => 0,
            'import_errors' => 0,
            'missing' => 0,
            'missing_preview' => [],
            'issues_preview' => [],
        ];

        $candidates = [];
        foreach ($rows as $index => $row) {
            $passport = $this->normalizePassportInput($this->mappedRowValue($row, $mapping, 'passport'));
            $passportKey = $this->normalizePassportForLookup($passport);

            if ($passportKey === '') {
                $report['empty_passport']++;
                continue;
            }

            $report['with_passport']++;

            if (isset($candidates[$passportKey])) {
                $report['duplicates']++;
                continue;
            }

            $candidates[$passportKey] = [
                'row_number' => $index + 1,
                'row' => $row,
                'passport' => $passport,
            ];
        }

        $report['unique_passports'] = count($candidates);
        $passportKeys = array_keys($candidates);

        $usersByPassport = empty($passportKeys)
            ? collect()
            : User::query()
                ->select(['id', 'name', 'email', 'passport'])
                ->whereNotNull('passport')
                ->whereIn(DB::raw('LOWER(TRIM(passport))'), $passportKeys)
                ->get()
                ->keyBy(fn ($user) => $this->normalizePassportForLookup((string) $user->passport));

        $emailKeys = [];
        if ($createMissing) {
            foreach ($candidates as $passportKey => $candidate) {
                if ($usersByPassport->has($passportKey)) {
                    continue;
                }

                $email = $this->normalizeEmailInput($this->mappedRowValue($candidate['row'], $mapping, 'email'));
                if ($email !== '') {
                    $emailKeys[] = mb_strtolower($email);
                }
            }
        }

        $usersByEmail = empty($emailKeys)
            ? collect()
            : User::query()
                ->select(['id', 'name', 'email', 'passport'])
                ->whereIn(DB::raw('LOWER(TRIM(email))'), array_values(array_unique($emailKeys)))
                ->get()
                ->keyBy(fn ($user) => mb_strtolower(trim((string) $user->email)));

        $usersToAttach = collect();
        $notAddedPassports = [];

        foreach ($candidates as $passportKey => $candidate) {
            $user = $usersByPassport->get($passportKey);

            if ($user) {
                $report['found']++;
                $usersToAttach->put((int) $user->id, $user);
                continue;
            }

            if (! $createMissing) {
                $report['not_found']++;
                $notAddedPassports[] = $candidate['passport'];
                continue;
            }

            $newUserData = $this->newImportedUserData($candidate['row'], $mapping, $candidate['passport']);
            $missingFields = $this->missingRequiredImportFields($newUserData);

            if (! empty($missingFields)) {
                $report['missing_required']++;
                $notAddedPassports[] = $candidate['passport'];
                $this->pushImportIssue($report, "Fila {$candidate['row_number']} ({$candidate['passport']}): faltan " . implode(', ', $missingFields) . '.');
                continue;
            }

            if (! filter_var($newUserData['email'], FILTER_VALIDATE_EMAIL)) {
                $report['invalid_email']++;
                $notAddedPassports[] = $candidate['passport'];
                $this->pushImportIssue($report, "Fila {$candidate['row_number']} ({$candidate['passport']}): correo invalido.");
                continue;
            }

            $emailKey = mb_strtolower($newUserData['email']);
            if ($usersByEmail->has($emailKey)) {
                $report['email_conflicts']++;
                $notAddedPassports[] = $candidate['passport'];
                $this->pushImportIssue($report, "Fila {$candidate['row_number']} ({$candidate['passport']}): el correo ya existe en otro usuario.");
                continue;
            }

            try {
                $user = $this->createImportedClient($newUserData);
                $this->ensureImportedClientAccess($user);
                $this->ensureImportedAgclienteRecord($user);

                $usersByEmail->put($emailKey, $user);
                $usersToAttach->put((int) $user->id, $user);
                $report['created']++;
            } catch (\Throwable $e) {
                $report['import_errors']++;
                $notAddedPassports[] = $candidate['passport'];
                $this->pushImportIssue($report, "Fila {$candidate['row_number']} ({$candidate['passport']}): no se pudo crear el cliente.");
            }
        }

        $matchedIds = $usersToAttach->keys()->map(fn ($id) => (int) $id)->values()->all();
        $alreadyAttachedIds = empty($matchedIds)
            ? collect()
            : $lista->users()
                ->whereIn('users.id', $matchedIds)
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id);

        $attach = [];
        foreach ($usersToAttach as $user) {
            if ($alreadyAttachedIds->contains((int) $user->id)) {
                continue;
            }

            $attach[$user->id] = [
                'contacted' => false,
                'contacted_at' => null,
                'contact_note' => null,
            ];
        }

        if (! empty($attach)) {
            $lista->users()->syncWithoutDetaching($attach);
        }

        $report['added'] = count($attach);
        $report['already'] = $alreadyAttachedIds->count();
        $report['missing'] = $report['not_found']
            + $report['missing_required']
            + $report['email_conflicts']
            + $report['invalid_email']
            + $report['import_errors'];
        $report['missing_preview'] = array_slice(array_values(array_unique($notAddedPassports)), 0, 20);

        $this->deleteImportPayload($data['import_token']);

        $message = "Importacion lista. Agregados: {$report['added']}. Creados: {$report['created']}. Ya estaban: {$report['already']}. No agregados: {$report['missing']}.";

        return back()
            ->with('success', $message)
            ->with('import_report', $report);
    }

    public function removeMember(Lista $lista, User $user)
    {
        $this->authorizeVentasMember($user);

        $lista->users()->detach($user->id);
        return back()->with('success', 'Usuario removido de la lista.');
    }

    /**
     * PATCH contacted
     * body: contacted (0/1), contact_note (optional)
     */
    public function setContacted(Request $request, Lista $lista, User $user)
    {
        $this->authorizeVentasMember($user);

        $data = $request->validate([
            'contacted' => ['required', 'boolean'],
            // ✅ obligatorio si contacted=1
            'contact_note' => ['required_if:contacted,1', 'nullable', 'string', 'max:2000'],
        ]);

        $lista->users()->updateExistingPivot($user->id, [
            'contacted'     => (bool)$data['contacted'],
            'contacted_at'  => $data['contacted'] ? now() : null,
            // si desmarcan, puedes limpiar nota o mantenerla (yo la limpio)
            'contact_note'  => $data['contacted'] ? ($data['contact_note'] ?? null) : null,
        ]);

        return back()->with('success', 'Estado de contacto actualizado.');
    }

    private function parseContactImportFile(UploadedFile $file): array
    {
        $raw = file_get_contents($file->getRealPath());
        $raw = trim(str_replace("\xEF\xBB\xBF", '', (string) $raw));

        if ($raw === '') {
            throw new \RuntimeException('El archivo esta vacio.');
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $firstChar = substr(ltrim($raw), 0, 1);

        if ($extension === 'json' || in_array($firstChar, ['[', '{'], true)) {
            return $this->parseJsonContactRows($raw);
        }

        if (! in_array($extension, ['csv', 'txt'], true)) {
            throw new \RuntimeException('Formato no soportado por ahora. Usa CSV o JSON.');
        }

        return $this->parseCsvContactRows($raw);
    }

    private function parseCsvContactRows(string $raw): array
    {
        $lines = preg_split('/\R/u', trim($raw)) ?: [];
        $lines = array_values(array_filter($lines, fn ($line) => trim((string) $line) !== ''));

        if (empty($lines)) {
            return ['columns' => [], 'rows' => []];
        }

        $delimiter = $this->detectCsvDelimiter($lines[0]);
        $parsedRows = array_map(fn ($line) => str_getcsv($line, $delimiter), $lines);
        $firstRow = array_map(fn ($value) => $this->normalizeImportCell($value), $parsedRows[0] ?? []);
        $hasHeader = $this->looksLikeHeaderRow($firstRow);

        if ($hasHeader) {
            $columns = $this->uniqueImportColumns($firstRow);
            $dataRows = array_slice($parsedRows, 1);
        } else {
            $maxColumns = max(array_map(fn ($row) => count($row), $parsedRows));
            $columns = collect(range(1, $maxColumns))
                ->map(fn ($number) => 'columna_' . $number)
                ->all();
            $dataRows = $parsedRows;
        }

        return [
            'columns' => $columns,
            'rows' => $this->rowsToAssociativeArray($dataRows, $columns),
        ];
    }

    private function parseJsonContactRows(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('El JSON no es valido: ' . json_last_error_msg());
        }

        $items = $this->extractJsonContactItems($decoded);

        if (empty($items)) {
            return ['columns' => [], 'rows' => []];
        }

        $columns = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_is_list($item)) {
                foreach (array_keys($item) as $index) {
                    $columns[] = 'columna_' . ((int) $index + 1);
                }
            } else {
                $columns = array_merge($columns, array_keys($item));
            }
        }

        $columns = $this->uniqueImportColumns(array_values(array_unique($columns)));
        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = [];
            foreach ($columns as $index => $column) {
                $value = array_is_list($item)
                    ? ($item[$index] ?? null)
                    : ($item[$column] ?? null);

                $row[$column] = $this->normalizeImportCell($value);
            }

            if ($this->rowHasAnyValue($row)) {
                $rows[] = $row;
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private function extractJsonContactItems(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        foreach (['contacts', 'contactos', 'clientes', 'clients', 'data', 'rows', 'items'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                if (array_is_list($decoded[$key])) {
                    return $decoded[$key];
                }

                return $this->arrayValuesAreArrays($decoded[$key])
                    ? array_values($decoded[$key])
                    : [$decoded[$key]];
            }
        }

        return $this->arrayValuesAreArrays($decoded) ? array_values($decoded) : [$decoded];
    }

    private function arrayValuesAreArrays(array $value): bool
    {
        return ! empty($value) && collect($value)->every(fn ($item) => is_array($item));
    }

    private function detectCsvDelimiter(string $line): string
    {
        $bestDelimiter = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $count = count(str_getcsv($line, $delimiter));

            if ($count > $bestCount) {
                $bestDelimiter = $delimiter;
                $bestCount = $count;
            }
        }

        return $bestDelimiter;
    }

    private function looksLikeHeaderRow(array $row): bool
    {
        $aliases = collect($this->importFieldAliases())->flatten()->all();

        foreach ($row as $value) {
            $normalized = $this->normalizeColumnName((string) $value);

            if (in_array($normalized, $aliases, true)) {
                return true;
            }
        }

        return false;
    }

    private function uniqueImportColumns(array $columns): array
    {
        $seen = [];

        return collect($columns)
            ->map(function ($column, $index) use (&$seen) {
                $base = trim((string) $column);
                $base = $base !== '' ? $base : 'columna_' . ((int) $index + 1);

                if (! isset($seen[$base])) {
                    $seen[$base] = 1;
                    return $base;
                }

                $seen[$base]++;
                return $base . '_' . $seen[$base];
            })
            ->values()
            ->all();
    }

    private function rowsToAssociativeArray(array $rows, array $columns): array
    {
        $normalizedRows = [];

        foreach ($rows as $rawRow) {
            $row = [];
            foreach ($columns as $index => $column) {
                $row[$column] = $this->normalizeImportCell($rawRow[$index] ?? null);
            }

            if ($this->rowHasAnyValue($row)) {
                $normalizedRows[] = $row;
            }
        }

        return $normalizedRows;
    }

    private function rowHasAnyValue(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function storeImportPayload(array $payload): string
    {
        $token = Str::random(40);
        file_put_contents($this->importPayloadPath($token), json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $token;
    }

    private function loadImportPayload(string $token): array
    {
        $path = $this->importPayloadPath($token);

        if (! is_file($path)) {
            throw new \RuntimeException('Import payload not found.');
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            throw new \RuntimeException('Import payload invalid.');
        }

        return $payload;
    }

    private function deleteImportPayload(string $token): void
    {
        $path = $this->importPayloadPath($token);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function importPayloadPath(string $token): string
    {
        if (! preg_match('/^[A-Za-z0-9]{20,100}$/', $token)) {
            throw new \RuntimeException('Invalid import token.');
        }

        $directory = storage_path('app/list-imports');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory . DIRECTORY_SEPARATOR . $token . '.json';
    }

    private function makeImportPreview(string $token, array $payload, array $selectedMapping = []): array
    {
        $columns = $payload['columns'] ?? [];

        return [
            'token' => $token,
            'filename' => $payload['filename'] ?? 'archivo',
            'columns' => $columns,
            'total_rows' => count($payload['rows'] ?? []),
            'sample_rows' => array_slice($payload['rows'] ?? [], 0, 5),
            'guesses' => array_replace($this->guessImportMappings($columns), $selectedMapping),
        ];
    }

    private function guessImportMappings(array $columns): array
    {
        $mapping = [
            'passport' => '',
            'nombres' => '',
            'apellidos' => '',
            'servicio' => '',
            'phone' => '',
            'email' => '',
        ];
        $aliases = $this->importFieldAliases();
        $normalizedColumns = [];

        foreach ($columns as $column) {
            $normalizedColumns[$column] = $this->normalizeColumnName((string) $column);
        }

        foreach ($mapping as $field => $value) {
            foreach ($normalizedColumns as $column => $normalized) {
                if (in_array($normalized, $aliases[$field] ?? [], true)) {
                    $mapping[$field] = $column;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function importFieldAliases(): array
    {
        return [
            'passport' => [
                'passport',
                'pasaporte',
                'numero_de_pasaporte',
                'numero_pasaporte',
                'n_pasaporte',
                'npasaporte',
                'idcliente',
                'id_cliente',
                'dni',
                'documento',
                'cedula',
            ],
            'nombres' => [
                'nombres',
                'nombre',
                'firstname',
                'first_name',
                'primer_nombre',
                'name',
            ],
            'apellidos' => [
                'apellidos',
                'apellido',
                'lastname',
                'last_name',
                'surname',
            ],
            'servicio' => [
                'servicio',
                'servicio_contratado',
                'servicio_solicitado',
                'producto',
                'plan',
                'service',
            ],
            'phone' => [
                'phone',
                'telefono',
                'telefono_movil',
                'mobilephone',
                'mobile',
                'celular',
                'whatsapp',
            ],
            'email' => [
                'email',
                'correo',
                'correo_electronico',
                'mail',
                'e_mail',
            ],
        ];
    }

    private function cleanImportMapping(array $mapping, array $columns): array
    {
        $allowed = array_flip($columns);
        $clean = [];

        foreach (['passport', 'nombres', 'apellidos', 'servicio', 'phone', 'email'] as $field) {
            $column = trim((string) ($mapping[$field] ?? ''));
            $clean[$field] = $column !== '' && isset($allowed[$column]) ? $column : '';
        }

        return $clean;
    }

    private function mappedRowValue(array $row, array $mapping, string $field): string
    {
        $column = $mapping[$field] ?? '';

        return $column !== '' ? $this->normalizeImportCell($row[$column] ?? '') : '';
    }

    private function newImportedUserData(array $row, array $mapping, string $passport): array
    {
        $nombres = $this->mappedRowValue($row, $mapping, 'nombres');
        $apellidos = $this->mappedRowValue($row, $mapping, 'apellidos');
        $servicio = $this->resolveImportedServiceValue($this->mappedRowValue($row, $mapping, 'servicio'));

        return [
            'name' => trim($nombres . ' ' . $apellidos),
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'servicio' => $servicio,
            'passport' => $passport,
            'phone' => $this->mappedRowValue($row, $mapping, 'phone'),
            'email' => $this->normalizeEmailInput($this->mappedRowValue($row, $mapping, 'email')),
        ];
    }

    private function missingRequiredImportFields(array $data): array
    {
        $labels = [
            'nombres' => 'nombre',
            'apellidos' => 'apellido',
            'passport' => 'pasaporte',
            'phone' => 'telefono',
            'email' => 'correo electronico',
        ];

        return collect($labels)
            ->filter(fn ($label, $field) => trim((string) ($data[$field] ?? '')) === '')
            ->values()
            ->all();
    }

    private function createImportedClient(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
            'nombres' => $data['nombres'],
            'apellidos' => $data['apellidos'],
            'passport' => $data['passport'],
            'phone' => $data['phone'],
            'servicio' => $data['servicio'],
            'pay' => 0,
            'cosready' => 1,
        ]);
    }

    private function ensureImportedClientAccess(User $user): void
    {
        if (! $user->hasRole('Cliente')) {
            $user->assignRole('Cliente');
        }

        $permissions = Permission::query()
            ->whereIn('name', ['pay.services', 'finish.register'])
            ->pluck('name')
            ->all();

        if (! empty($permissions)) {
            $user->givePermissionTo($permissions);
        }
    }

    private function ensureImportedAgclienteRecord(User $user): void
    {
        if (blank($user->passport)) {
            return;
        }

        Agcliente::firstOrCreate(
            [
                'IDCliente' => trim((string) $user->passport),
                'IDPersona' => 1,
            ],
            [
                'Nombres' => trim((string) $user->nombres),
                'Apellidos' => trim((string) $user->apellidos),
                'NPasaporte' => trim((string) $user->passport),
                'FRegistro' => now(),
                'FUpdate' => now(),
                'Usuario' => trim((string) $user->email),
            ]
        );
    }

    private function resolveImportedServiceValue(string $service): string
    {
        $service = trim($service);

        if ($service === '') {
            return '';
        }

        static $cache = [];
        $cacheKey = mb_strtolower($service);

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $servicio = Servicio::query()
            ->where('id_hubspot', $service)
            ->orWhere('id_hubspot', 'like', $service . '%')
            ->orWhere('nombre', $service)
            ->orWhere('nombre', 'like', $service . '%')
            ->first();

        return $cache[$cacheKey] = (string) ($servicio?->id_hubspot ?: $service);
    }

    private function pushImportIssue(array &$report, string $issue): void
    {
        if (count($report['issues_preview']) < 10) {
            $report['issues_preview'][] = $issue;
        }
    }

    private function normalizeImportCell(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return trim(str_replace("\xEF\xBB\xBF", '', (string) $value));
    }

    private function normalizePassportInput(string $passport): string
    {
        $passport = trim(str_replace("\xEF\xBB\xBF", '', $passport));
        $passport = preg_replace('/^[\'"]+|[\'"]+$/', '', $passport) ?? $passport;

        return trim($passport);
    }

    private function normalizePassportForLookup(string $passport): string
    {
        return mb_strtolower(trim($this->normalizePassportInput($passport)));
    }

    private function normalizeEmailInput(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizeColumnName(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
