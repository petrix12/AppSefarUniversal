<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictClientRoutes
{
    // Client screens from AdminLTE and the requests those screens actually use.
    // New internal routes are denied to Cliente unless deliberately added here.
    public const ALLOWED_ROUTES = [
        'inicio', 'client-access-blocked', 'adminlte.darkmode.toggle',
        'clientes.tree', 'clientes.google-review', 'clientes.salir', 'clientes.procesar',
        'clientes.getinfo', 'clientes.pay', 'clientes.pagospendientes', 'clientes.status',
        'clientes.support-ticket.create', 'clientes.support-ticket.store',
        'cliente.vinculaciones', 'cliente.regvinculaciones', 'cliente.contrato', 'cliente.hermanos',
        'service-store.index', 'service-store.show', 'service-store.purchase',
        'notifications.index', 'notifications.read', 'notifications.read-all',
        'procesargetinfo', 'gotopayfases', 'procesarpay', 'procesarpayfases',
        'procesarpaypalfases', 'procesarpaypal', 'procesar-pago-stripe',
        'revisarcupon', 'destroypayelement', 'cuponaplicado', 'referral-codes.validate',
        'registrarhermanoscliente', 'checkContrato', 'changepassword', 'saveuserdata',
        'my_status', 'viewcomprobantecliente', 'upload', 'no_doc',
        'agclientesnew.store', 'agclientesnew.update',
        'getclientfiles', 'updatefiletype', 'storefile', 'openfile',
    ];

    // A Genealogista may also be a client. Only their tree work gets this exception;
    // each route still enforces its existing genealogy and document permissions.
    public const GENEALOGIST_ROUTES = [
        'arboles.albero.index', 'arboles.tree.user', 'arboles.tree.index',
        'arboles.tree.branch', 'arboles.tree.line-color', 'arboles.tree.person-detail',
        'arboles.tree.treepart', 'arboles.olivo.index',
        'getfileedit', 'getfileupdate', 'deletefile',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->hasRole('Cliente')) {
            $route = $request->route()?->getName();
            $genealogistTreeAccess = $request->user()->hasRole('Genealogista')
                && in_array($route, self::GENEALOGIST_ROUTES, true);

            abort_unless($genealogistTreeAccess || in_array($route, self::ALLOWED_ROUTES, true), 403,
                'Esta ruta es de uso interno y no está disponible para clientes.');
        }

        return $next($request);
    }
}
