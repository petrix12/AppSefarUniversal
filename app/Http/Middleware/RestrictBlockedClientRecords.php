<?php

namespace App\Http\Middleware;

use App\Models\Agcliente;
use App\Models\File;
use App\Models\User;
use App\Services\ClientRestrictionService;
use App\Services\ClientRestrictionTargets;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RestrictBlockedClientRecords
{
    public function handle(Request $request, Closure $next)
    {
        if (! Schema::hasTable('user_access_blocks')) {
            return $next($request);
        }
        $actor = $request->user() ?: auth('sanctum')->user();
        if (! $actor) {
            return $next($request);
        }
        $name = $request->route()?->getName() ?? '';
        if (str_starts_with($name, 'admin.client-restrictions.') || str_starts_with($name, 'api.client-blocks.')) {
            return $next($request);
        }
        $service = app(ClientRestrictionService::class);
        $targets = app(ClientRestrictionTargets::class);
        $clients = collect();
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof User) {
                $clients->push($parameter);
            } elseif ($parameter instanceof File) {
                $targets->assertFileDeliverable($parameter);
            } elseif ($parameter instanceof Agcliente) {
                $targets->assertTreeReadable($parameter->IDCliente, $actor);
            }
        }
        foreach (['IDCliente', 'clienteid'] as $key) {
            $tree = $request->route($key) ?? $request->input($key);
            if (is_string($tree) && $tree !== '') {
                $clients = $clients->merge($targets->tree($tree));
            }
        }
        if (in_array($name, ['saveuserdata', 'getuserstatus', 'getemail'], true) && is_numeric($request->id)) {
            $clients->push(User::find($request->id));
        }
        if (in_array($name, ['getGedcomCliente', 'getExcelCliente'], true)) {
            $person = Agcliente::find($request->route('id') ?? $request->id);
            if ($person) {
                $targets->assertTreeDeliverable($person->IDCliente);
            }
        }
        if (in_array($name, ['getfileedit', 'getfileupdate', 'deletefile', 'updatefiletype'], true)) {
            $file = File::find($request->input('fileId') ?? $request->input('id'));
            if ($file) {
                $targets->assertFileDeliverable($file);
            }
        }
        if ($name === 'openfile') {
            $path = $request->input('path');
            abort_unless(is_string($path), 422);
            $file = File::where('location', dirname($path))->where('file', basename($path))->first();
            // An arbitrary path must not bypass the client restriction lookup.
            abort_unless($file, 403, 'El archivo debe pertenecer a un expediente registrado.');
            $targets->assertFileDeliverable($file);
        }
        if (str_starts_with($name, 'teamleader.') && $request->route('id')) {
            $parts = explode('.', $name);
            $type = ['contacts' => 'contact', 'deals' => 'deal', 'projects' => 'project', 'invoices' => 'invoice'][$parts[1] ?? ''] ?? null;
            if ($type) {
                $clients = $clients->merge($targets->teamleader($type, (string) $request->route('id')));
            }
        }
        foreach ($clients->filter()->unique('id') as $client) {
            $service->assertReadable($client, $actor);
            if ($name === 'getclientfiles' || str_contains($name, 'download') || str_ends_with($name, '.pdf') || str_starts_with($name, 'api.mcp.')) {
                $service->assertDeliverable($client);
            }
        }

        $response = $next($request);
        foreach ($clients->filter()->unique('id') as $client) {
            $service->assertReadable($client, $actor);
            if (str_starts_with($name, 'api.mcp.')) {
                $service->assertDeliverable($client);
            }
        }

        return $response;
    }
}
