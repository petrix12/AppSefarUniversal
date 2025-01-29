<?php

use App\Http\Controllers\AgclienteController;
use App\Http\Controllers\AlberoController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FormatController;
use App\Http\Controllers\LadoController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\EtiquetaGenealogiaController;
use App\Http\Controllers\MiscelaneoController;
use App\Http\Controllers\OlivoController;
use App\Http\Controllers\OnidexController;
use App\Http\Controllers\ParentescoController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TFileController;
use App\Http\Controllers\TreeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HsReferidoController;
use App\Http\Controllers\MondayController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\CorreoController;
use App\Http\Controllers\GedcomController;
use App\Http\Controllers\TeamLeaderController;
use App\Http\Controllers\HermanoController;
use App\Http\Controllers\AgClienteNewController;
use App\Http\Controllers\SolicitudCuponController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\GeneralCouponController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;


// Vista inicio
Route::get('/', [Controller::class, 'index'])->name('inicio')->middleware(['auth', 'verified']);

// Grupo de rutas CRUD
Route::group(['middleware' => ['auth'], 'as' => 'crud.'], function(){
    Route::resource('permissions', PermissionController::class)->names('permissions')
			->middleware('can:crud.permissions.index');
    Route::resource('roles', RoleController::class)->names('roles')
			->middleware('can:crud.roles.index');
    Route::resource('users', UserController::class)->names('users')
			->middleware('can:crud.users.index');
    Route::resource('countries', CountryController::class)->names('countries')
            ->middleware('can:crud.countries.index');
    Route::resource('agclientes', AgclienteController::class)
            ->names([
                'index' => 'agclientes.index',
                'store' => 'agclientes.store',
                'create' => 'agclientes.create',
                'show' => 'agclientes.show',
                'edit' => 'agclientes.edit',
                'update' => 'agclientes.update',
                'destroy' => 'agclientes.destroy',
            ])
            ->middleware('can:crud.agclientes.index');
    Route::resource('parentescos', ParentescoController::class)->names('parentescos')
            ->middleware('can:crud.parentescos.index');
    Route::resource('lados', LadoController::class)->names('lados')
            ->middleware('can:crud.lados.index');
    Route::resource('connections', ConnectionController::class)->names('connections')
            ->middleware('can:crud.connections.index');
    Route::resource('families', FamilyController::class)->names('families')
            ->middleware('can:crud.families.index');
    Route::resource('t_files', TFileController::class)->names('t_files')
            ->middleware('can:crud.t_files.index');
    Route::resource('files', FileController::class)->names('files')
            ->middleware('can:crud.files.index');
    Route::resource('libraries', LibraryController::class)->names('libraries')
            ->middleware('can:crud.libraries.index');
    Route::resource('formats', FormatController::class)->names('formats')
            ->middleware('can:crud.formats.index');
    Route::resource('books', BookController::class)->names('books')
            ->middleware('can:crud.books.index');
    Route::resource('miscelaneos', MiscelaneoController::class)->names('miscelaneos')
            ->middleware('can:crud.miscelaneos.index');
    Route::resource('coupons', CouponController::class)->names('coupons')
            ->middleware('can:crud.coupons.index');
    Route::resource('servicios', ServicioController::class)->names('servicios')
            ->middleware('can:crud.servicios.index');
    Route::resource('reports', ReportController::class)->names('reports')
            ->middleware('can:crud.reports.index');
    Route::resource('hsreferidos', HsReferidoController::class)->names('hsreferidos')
            ->middleware('can:crud.hsreferidos.index');
    Route::resource('comprobantes', FacturaController::class)->names('comprobantes')
            ->middleware('can:crud.comprobantes.index');
    Route::resource('solicitudcupones', SolicitudCuponController::class)->names('solicitudcupones')
            ->middleware('can:crud.solicitudcupones.index');
});

Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index')->middleware('can:alertasapp.index');
Route::get('alerts/create', [AlertController::class, 'create'])->name('alerts.create')->middleware('can:alertasapp.create');
Route::post('alerts', [AlertController::class, 'store'])->name('alerts.store')->middleware('can:alertasapp.create');
Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy')->middleware('can:alertasapp.delete');

Route::get('generalcoupons', [GeneralCouponController::class, 'index'])->name('generalcoupons.index')->middleware('can:generalcoupons.index');
Route::get('generalcoupons/create', [GeneralCouponController::class, 'create'])->name('generalcoupons.create')->middleware('can:generalcoupons.create');
Route::post('generalcoupons', [GeneralCouponController::class, 'store'])->name('generalcoupons.store')->middleware('can:generalcoupons.create');
Route::delete('generalcoupons/{generalCoupon}', [GeneralCouponController::class, 'destroy'])->name('generalcoupons.destroy')->middleware('can:generalcoupons.delete');

Route::post('agclientesnew', [AgClienteNewController::class, 'storeNotCliente'])->name('agclientesnew.store');
Route::post('agclientesupdate', [AgClienteNewController::class, 'updateNotCliente'])->name('agclientesnew.update');
Route::post('getclientfiles', [AgClienteNewController::class, 'getClientFiles'])->name('getclientfiles');
Route::post('updatefiletype', [AgClienteNewController::class, 'updatefiletype'])->name('updatefiletype');
Route::post('storefile', [AgClienteNewController::class, 'storefile'])->name('storefile');
Route::post('openfile', [AgClienteNewController::class, 'openfile'])->name('openfile');
Route::post('deletefile', [AgClienteNewController::class, 'deletefile'])->name('deletefile');
Route::post('getfileedit', [AgClienteNewController::class, 'getfileedit'])->name('getfileedit');
Route::post('getfileupdate', [AgClienteNewController::class, 'getfileupdate'])->name('getfileupdate');

Route::post('etiquetasgenealogiamonday', [EtiquetaGenealogiaController::class, 'update'])->name('etiquetasgenealogiamonday');

Route::get('diarioindex',[ReportController::class, 'diarioindex'])->name('diarioindex')->middleware('can:reportes.index');
Route::get('mensualindex',[ReportController::class, 'mensualindex'])->name('mensualindex')->middleware('can:reportes.index');
Route::get('anualindex',[ReportController::class, 'anualindex'])->name('anualindex')->middleware('can:reportes.index');
Route::get('semanalindex',[ReportController::class, 'semanalindex'])->name('semanalindex')->middleware('can:reportes.index');
Route::post('getreportediario',[ReportController::class, 'getreportediario'])->name('getreportediario')->middleware('can:reportes.index');
Route::post('getreportemensual',[ReportController::class, 'getreportemensual'])->name('getreportemensual')->middleware('can:reportes.index');
Route::post('getreportesemanal',[ReportController::class, 'getreportesemanal'])->name('getreportesemanal')->middleware('can:reportes.index');
Route::post('getreporteanual',[ReportController::class, 'getreporteanual'])->name('getreporteanual')->middleware('can:reportes.index');

Route::post('mypassword',[UserController::class, 'mypassword'])->name('mypassword');
Route::post('adminchangepassword',[UserController::class, 'adminchangepassword'])->name('adminchangepassword');

//TeamleaderTest

Route::middleware(['auth'])->group(function () {
    Route::get('/teamleader/redirect', [TeamleaderController::class, 'redirectToProvider'])->name('teamleader.redirect');
    Route::get('/teamleader/callback', [TeamleaderController::class, 'handleProviderCallback'])->name('teamleader.callback');
});
Route::get('/teamleader/success', [TeamleaderController::class, 'success'])->name('teamleader.success');
Route::get('/teamleader/contacts', [TeamleaderController::class, 'getContacts'])->name('teamleader.contacts');

//checkRegMondayTest
Route::get('/checkMondayTest', [ClienteController::class, 'checkMondayTest'])->name('checkMondayTest');

//Ruta Comprobantes de Pago
Route::get('/downloadTree/{id}', [GedcomController::class, 'getGedcomCliente'])->name('getGedcomCliente');

//Ruta Comprobantes de Pago
Route::get('/downloadExcel/{id}', [GedcomController::class, 'getExcelCliente'])->name('getExcelCliente');

//Ruta api GetEmail
Route::get('/api/getemail/{id}', [UserController::class, 'getemail'])->name('getemail');

Route::get('/viewfile/{id}', [FileController::class, 'viewFile'])->name('viewfile');

//Ruta Comprobantes de Pago
Route::get('/viewcomprobante/{id}', [FacturaController::class, 'viewcomprobante'])->name('viewcomprobante');
Route::get('/viewcomprobantecliente/{id}', [FacturaController::class, 'viewcomprobantecliente'])->name('viewcomprobantecliente');

//panel administrativo status
Route::get('/users/status/{id}', [UserController::class, 'getuserstatus'])->name('getuserstatus');

Route::post('/guardar-datos-personales', [UserController::class, 'savePersonalData'])->name('saveuserdata');

//panel produccion y ventas status
Route::get('/clientes/status/{agcliente}', [UserController::class, 'getuserstatus_ventas'])->name('getuserstatus_ventas');

//panel CLIENTE status
Route::get('/my_status', [UserController::class, 'my_status'])->name('my_status');

//panel Pasaportes erroneos
Route::get('/fixpassport', [UserController::class, 'fixpassport'])->name('fixpassport')->middleware('can:administrador');

//panel Pasaportes erroneos
Route::post('/fixpassport', [UserController::class, 'fixpassportprocess'])->name('fixpassportprocess');

//AJAX para activar y desactivar cupones

Route::get('cuponaceptar/{id}',[SolicitudCuponController::class, 'aprobarcupon'])->name('cuponaceptar');
Route::get('cuponrechazar/{id}',[SolicitudCuponController::class, 'rechazarcupon'])->name('rechazarcupon');

Route::post('cuponenable',[CouponController::class, 'enable'])->name('cuponenable');

//Generar Catalogos (Configurar CRON para correr a diario)
Route::get('makereport', [ReportController::class, 'makereport'])->name('makereport');

//Eliminar datos de factura en pay
Route::post('/destroypayelement', [ClienteController::class, 'destroypayelement'])->name('destroypayelement');

//GEDCOM EXPORT
Route::get('/gedcomexport', [GedcomController::class, 'gedcomexport'])->name('gedcomexport');
Route::get('/getGedcomGlobal', [GedcomController::class, 'getGedcomGlobal'])->name('getGedcomGlobal');

//Rutas para Stripe:
Route::get('stripeverify', [StripeController::class, 'stripeverify'])->name('stripeverify')
        ->middleware('can:crud.stripeverify.index');
Route::post('stripefind', [StripeController::class, 'stripefind'])->name('stripefind');
Route::post('stripegetidpago', [StripeController::class, 'stripegetidpago'])->name('stripegetidpago');
Route::post('stripeupdatedata',[StripeController::class, 'stripeupdatedata'])->name('stripeupdatedata');
Route::get('listLatestStripeData', [StripeController::class, 'listLatestStripeData'])->name('listLatestStripeData');
Route::post('/listLatestStripeData/export', [StripeController::class, 'exportdatastripeexcel'])->name('exportdatastripeexcel');
Route::post('/listLatestStripeData/getStripeAJAX', [StripeController::class, 'getStripeAJAX'])->name('getStripeAJAX');

//Rutas para Monday:
Route::get('/mondayreportes', [MondayController::class, 'mondayreportes'])->name('mondayreportes')
        ->middleware('can:crud.mondayreportes.index');

Route::get('/mondayregistrar', [MondayController::class, 'mondayregistrar'])->name('mondayregistrar')
        ->middleware('can:crud.mondayreportes.index');

Route::post('/registrarMD', [MondayController::class, 'registrarMD'])->name('registrarMD');

//Rutas para servicios de vinculaciones
Route::get('/vinculaciones', [ClienteController::class, 'vinculaciones'])->name('cliente.vinculaciones')->middleware('can:cliente');
Route::get('/vinculaciones/{id}', [ClienteController::class, 'regvinculaciones'])->name('cliente.regvinculaciones');

//rutas para firma de contrato
Route::get('/contrato', [ClienteController::class, 'contrato'])->name('cliente.contrato')->middleware('can:cliente');
Route::get('/checkContrato', [ClienteController::class, 'checkContrato'])->name('checkContrato');

//
Route::get('/hermanoscliente', [ClienteController::class, 'hermanoscliente'])->name('cliente.hermanos')->middleware('can:cliente');

Route::post('/registrarhermanoscliente', [HermanoController::class, 'registrarhermanoscliente'])->name('registrarhermanoscliente');

//Verificarcupon

Route::get('/cuponaplicado', function(){
    return redirect()->route('clientes.getinfo')->with("status","exito");
})->name('cuponaplicado');

Route::get('/fixCouponHubspot', [CouponController::class, 'fixCouponHubspot'])->name('fixCouponHubspot');

Route::get('/fixPayDataHubspot', [ClienteController::class, 'fixPayDataHubspot'])->name('fixPayDataHubspot');

//Rutas para agradecimiento:

Route::get('/gracias', function(){
    return view('clientes.gracias');
})->name('gracias');

Route::get('/noservices', function(){
    return view('clientes.noservices');
})->name('noservices');

// Grupo de rutas para Consultas a base de datos
Route::group(['middleware' => ['auth'], 'as' => 'consultas.'], function(){
    Route::get('consultaodx', [OnidexController::class, 'index'])->name('onidex.index')
        ->middleware('can:consultas.onidex.index');
    Route::post('consultaodx', [OnidexController::class, 'show'])->name('onidex.show')
        ->middleware('can:consultas.onidex.show');
});

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

// Grupo de rutas para vistas de árboles genealógicos
Route::group(['middleware' => ['auth'], 'as' => 'arboles.'], function(){
    Route::get('albero/{IDCliente}', [AlberoController::class, 'arbelo'])->name('albero.index')
        ->middleware('can:genealogista');
    Route::get('tree/{IDCliente}', [TreeController::class, 'tree'])->name('tree.index')
        ->middleware('can:genealogista');
    Route::get('tree/{IDCliente}/{id}/{gen}/{parent}', [TreeController::class, 'treepart'])->name('tree.treepart')
        ->middleware('can:genealogista');
    Route::get('olivo/{IDCliente}', [OlivoController::class, 'olivo'])->name('olivo.index')
        ->middleware('can:genealogista');
});

// Grupo de rutas para vistas de clientes
Route::group(['middleware' => ['auth'], 'as' => 'clientes.'], function(){
    Route::get('tree', [ClienteController::class, 'tree'])->name('tree')
        ->middleware('can:cliente');
    Route::get('salir', [ClienteController::class, 'salir'])->name('salir')
        ->middleware('can:cliente');
    Route::post('procesar', [ClienteController::class, 'procesar'])->name('procesar');
    Route::get('getinfo', [ClienteController::class, 'getinfo'])->name('getinfo')
        ->middleware('can:cliente');
    Route::get('pay', [ClienteController::class, 'pay'])->name('pay')
        ->middleware('can:cliente');

});

Route::get('testcorreos', [CorreoController::class, 'testcorreos'])->name('testcorreos');
Route::post('testcorreos', [CorreoController::class, 'sendcorreo'])->name('sendcorreo');


Route::post('getinfo', [ClienteController::class, 'procesargetinfo'])->name('procesargetinfo')
        ->middleware('can:cliente');

Route::get('checkRegAlzada', [ClienteController::class, 'checkRegAlzada'])->name('checkRegAlzada');

Route::post('pay', [ClienteController::class, 'procesarpay'])->name('procesarpay')
        ->middleware('can:cliente');

Route::get('/revisarcupon', [ClienteController::class, 'revisarcupon'])->name('revisarcupon')
        ->middleware('can:cliente');

// Grupo de rutas para realizar pruebas
Route::group(['middleware' => ['auth'], 'as' => 'test.'], function(){
    // Pruebas con Flex de Tailwind
    Route::get('flex', function (){
        return view('pruebas.flex');
    })->name('flex')->middleware('can:administrador');

    // Pruebas MVC Agcliente
    Route::get('agclientesp', function (){
        $agclientes = App\Models\Agcliente::all();
        return view('pruebas.agclientes', compact('agclientes'));
    })->name('agclientesp')->middleware('can:administrador');

    // Pruebas con ventanas modal
    Route::get('vmodal', function (){
        return view('pruebas.vmodal');
    })->name('vmodal')->middleware('can:administrador');

    // Generar enlaces para registrar clientes
    Route::get('registro', [App\Http\Controllers\GetController::class, 'registro'])->name('registro')->middleware('can:administrador');

    // Capturar parámetros get
    Route::get('capturar_parametros_get', [App\Http\Controllers\GetController::class, 'capturar_parametros_get'])->name('capturar_parametros_get')->middleware('can:administrador');
});


// RUTAS PARA EL MANTENIMIENTO DE LA APLICACIÓN EN PRODUCCIÓN
// Ruta para ejecutar en producción: $ php artisan key:generate
Route::get('key-generate', function(){
    Artisan::call('key:generate');
});

// Ruta para ejecutar en producción: $ php artisan storage:link
Route::get('storage-link', function(){
    Artisan::call('storage:link');
});

// Ruta para ejecutar en producción: $ php artisan config:cache
Route::get('config-cache', function(){
    Artisan::call('config:cache');
});

// Ruta para ejecutar en producción: $ php artisan cache:clear
Route::get('cache-clear', function(){
    Artisan::call('cache:clear');
});

// Ruta para ejecutar en producción: $ php artisan route:clear
Route::get('route-clear', function(){
    Artisan::call('route:clear');
});

// Ruta para ejecutar en producción: $ php artisan config:clear
Route::get('config-clear', function(){
    Artisan::call('config:clear');
});

// Ruta para ejecutar en producción: $ php artisan view:clear
Route::get('view-clear', function(){
    Artisan::call('view:clear');
});
