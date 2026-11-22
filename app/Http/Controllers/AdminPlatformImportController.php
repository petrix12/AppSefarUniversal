<?php

namespace App\Http\Controllers;

use App\Models\PlatformRecord;
use App\Services\{PlatformCatalog, PlatformImportPlanner};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Schema};

class AdminPlatformImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','can:platforms.manage']);
        $this->middleware(function ($request,$next) {
            abort_unless($request->user()->hasRole('Administrador') && !$request->user()->hasRole('Cliente'),403);
            return $next($request);
        });
    }

    public function overview(PlatformImportPlanner $planner, PlatformCatalog $catalog)
    {
        $connections=$planner->connections();
        $ready=collect(['platform_sync_states','platform_records','platform_events','platform_operations'])
            ->every(fn($table)=>Schema::hasTable($table));
        $states=$ready ? DB::table('platform_sync_states')->get()->keyBy(fn($row)=>implode('|',[$row->provider,$row->account,$row->mode,$row->resource])) : collect();
        $counts=$ready ? PlatformRecord::where('remote_id','not like','cus_request_%')->where('remote_id','not like','cli_request_%')
            ->select('provider','account','mode','resource')->selectRaw('count(*) as total')
            ->groupBy('provider','account','mode','resource')->get()->keyBy(fn($row)=>implode('|',[$row->provider,$row->account,$row->mode,$row->resource])) : collect();
        $lastRun=Cache::get('platforms.sync.last_run');
        return response()->view('platforms.imports',compact('connections','catalog','ready','states','counts','lastRun'))
            ->header('Cache-Control','private, no-store');
    }

    public function queue(Request $request, PlatformImportPlanner $planner)
    {
        $input=$request->validate(['provider'=>['nullable','in:stripe,kalendia,paypal'],'account'=>['nullable','in:main,spain_legacy']]);
        try { $result=$planner->queueAll($input['provider']??null,$input['account']??null); }
        catch (\InvalidArgumentException) { abort(422,'Proveedor o cuenta no admitidos.'); }
        if (isset($result['reasons']['migration_missing'])) {
            return back()->withErrors(['imports'=>'Falta aplicar la migración de administración de plataformas.']);
        }
        return redirect()->route('admin.platforms.imports.index')->with('success',
            "Recursos solicitados: {$result['requested']}. Ya en curso: {$result['already_running']}. Las conexiones sin credencial permanecen pendientes; PayPal se consulta por API.");
    }
}
