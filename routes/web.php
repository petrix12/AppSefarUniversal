<?php

use App\Http\Controllers\AgclienteController;
use App\Http\Controllers\AdminConsultationCalendarController;
use App\Http\Controllers\AlberoController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\CoordinatorReferralCodeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FamilyGroupController;
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
use App\Http\Controllers\ServiceStoreController;
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
use App\Http\Controllers\AdminBancaOnlineController;
use App\Http\Controllers\GeneralCouponController;
use App\Http\Controllers\BancaOnlineController;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\TreenaController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\RegisterV2Controller;
use App\Http\Controllers\CosVisitController;
use App\Http\Controllers\WhatsappBotURLController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\ReportPhoneNumbersController;
use App\Http\Controllers\CosPasoEditorController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ProveedorRegisterController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\HubspotOwnerController;
use App\Http\Controllers\StrategicSuggestionAttachmentController;
use App\Http\Controllers\StrategicSuggestionController;
use App\Http\Controllers\Teamleader\TlProjectController;
use App\Http\Controllers\TlContactController;
use App\Http\Controllers\TlInvoiceController;
use App\Http\Controllers\Teamleader\InvoicePdfController as TlInvoicePdfController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AdminTaskController;
use App\Http\Controllers\ContratoCoordinadorController;
use App\Http\Controllers\RequestAuditController;
use App\Http\Controllers\UserSyncController;
use App\Http\Controllers\InternalTaskWorkflowController;
use App\Http\Controllers\ClientChatController;
use App\Http\Controllers\ClientNotificationController;
use App\Http\Controllers\ExternalClientImportController;
use App\Http\Controllers\TeamleaderCronController;
use App\Http\Controllers\TeamleaderJobController;
use App\Http\Controllers\TaskCronController;
use App\Http\Controllers\JotformCouponCronController;

Route::get('/internal/tasks/daily-workflow', InternalTaskWorkflowController::class)
    ->name('internal.tasks.daily-workflow');

Route::prefix('cron/teamleader')
    ->name('cron.teamleader.')
    ->group(function () {
        Route::get('/sync', [TeamleaderCronController::class, 'sync'])
            ->name('sync');

        Route::get('/work', [TeamleaderCronController::class, 'work'])
            ->name('work');
    });

Route::prefix('cron/tasks')
    ->name('cron.tasks.')
    ->group(function () {
        Route::get('/work', [TaskCronController::class, 'work'])
            ->name('work');
    });

Route::get('/cron/jotform/coupons', JotformCouponCronController::class)
    ->name('cron.jotform.coupons');

Route::post('/users/{user}/sync-deals', [UserSyncController::class, 'sync'])
    ->name('users.sync-deals')
    ->middleware(['auth']);

Route::middleware(['auth'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function () {
        Route::get('/', [ClientNotificationController::class, 'index'])->name('index');
        Route::patch('/{notification}/read', [ClientNotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [ClientNotificationController::class, 'markAllAsRead'])->name('read-all');
    });

Route::get('/api/contacts/search', [App\Http\Controllers\Api\ContactSearchController::class, 'search'])
    ->name('api.contacts.search')
    ->middleware('auth');


Route::get('/request-audits', [RequestAuditController::class, 'view'])
    ->name('request-audits.index');

Route::middleware(['auth', 'can:administrador'])
    ->prefix('client-import')
    ->name('client-import.')
    ->group(function () {
        Route::get('/', [ExternalClientImportController::class, 'showImportForm'])->name('index');
        Route::post('/', [ExternalClientImportController::class, 'importClient'])->name('store');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('/contrato-coordinador', [ContratoCoordinadorController::class, 'form'])
        ->name('contrato.coordinador.form');

    Route::get('/contrato-coordinador/confirmar', [ContratoCoordinadorController::class, 'confirm'])
        ->name('contrato.coordinador.confirm');
});

// ─── TAREAS ───────────────────────────────────────────────
Route::middleware(['auth'])->prefix('tasks')->name('tasks.')->group(function () {

    Route::middleware('can:administrador')
     ->prefix('admin')
     ->name('admin.')
     ->group(function () {

         Route::get('/',                [AdminTaskController::class, 'table'])->name('index');
         Route::get('/create',          [AdminTaskController::class, 'create'])->name('create');
         Route::get('/summary',         [AdminTaskController::class, 'summary'])->name('summary');
         Route::get('/reports',         [AdminTaskController::class, 'reports'])->name('reports');
         Route::get('/reports/export',  [AdminTaskController::class, 'exportReport'])->name('reports.export');
         Route::post('/generate-daily', [AdminTaskController::class, 'generateDaily'])->name('generate-daily');
         Route::post('/daily-workflow/force', [AdminTaskController::class, 'forceDailyWorkflow'])->name('daily-workflow.force');
         Route::post('/bulk-reassign-contacts', [AdminTaskController::class, 'bulkReassignContacts'])->name('bulk-reassign-contacts');
         Route::post('/',               [AdminTaskController::class, 'store'])->name('store');
         Route::delete('/bulk',          [AdminTaskController::class, 'bulkDestroy'])->name('bulk-destroy');
         Route::delete('/bulk-filtered', [AdminTaskController::class, 'bulkDestroyFiltered'])->name('bulk-destroy-filtered');

         Route::get('/{task}/edit',     [AdminTaskController::class, 'edit'])->name('edit');
         Route::put('/{task}',          [AdminTaskController::class, 'update'])->name('update');
         Route::delete('/{task}',       [AdminTaskController::class, 'destroy'])->name('destroy');
     });

    // ── Asesor DESPUÉS ────────────────────────────
    Route::get('/',             [TaskController::class, 'table'])->name('index');
    Route::get('/{task}',       [TaskController::class, 'show'])->name('show');
    Route::put('/{task}/complete-internal', [TaskController::class, 'completeInternal'])->name('completeInternal');
    Route::put('/{task}/sales-tracking', [TaskController::class, 'updateSalesTracking'])->name('updateSalesTracking');
    Route::post('/{task}/flow', [TaskController::class, 'submitFlow'])->name('submitFlow');
    Route::post('/{task}/sync-contact', [TaskController::class, 'syncContact'])->name('syncContact');
});

// ── Facturas propias (auth + admin) ──────────────────────────────────
Route::middleware(['auth', 'can:administrador'])->group(function () {

    Route::resource('invoices', InvoiceController::class);

    Route::get('invoices/{invoice}/pdf', InvoicePdfController::class)
        ->name('invoices.pdf');

    // AJAX
    Route::get('invoices-user-data/{user}', [InvoiceController::class, 'getUserData'])
        ->name('invoices.user-data');
    Route::get('invoices-user-search', [InvoiceController::class, 'searchUsers'])
        ->name('invoices.user-search');

});

Route::resource('admin/consultation-calendars', AdminConsultationCalendarController::class)
    ->names('admin.consultation-calendars')
    ->except(['show'])
    ->middleware(['auth', 'can:administrador']);

Route::get('referral-codes/validate', [CoordinatorReferralCodeController::class, 'validateCode'])
    ->name('referral-codes.validate')
    ->middleware(['auth']);

Route::middleware(['auth', 'can:administrador'])
    ->prefix('admin/referral-codes')
    ->name('admin.referral-codes.')
    ->group(function () {
        Route::get('/', [CoordinatorReferralCodeController::class, 'index'])->name('index');
        Route::post('/sync', [CoordinatorReferralCodeController::class, 'sync'])->name('sync');
        Route::post('/send-all', [CoordinatorReferralCodeController::class, 'sendAll'])->name('send-all');
    });

// ── Teamleader (auth + admin) ─────────────────────────────────────────
Route::middleware(['auth', 'can:administrador'])
    ->prefix('teamleader/jobs')
    ->name('teamleader.jobs.')
    ->group(function () {
        Route::get('/', [TeamleaderJobController::class, 'index'])->name('index');
        Route::post('/work', [TeamleaderJobController::class, 'work'])->name('work');
        Route::post('/failed/retry', [TeamleaderJobController::class, 'retryFailed'])->name('failed.retry');
        Route::post('/failed/{failedJob}/retry', [TeamleaderJobController::class, 'retryFailedJob'])->name('failed.retry-one');
        Route::delete('/failed', [TeamleaderJobController::class, 'clearFailed'])->name('failed.clear');
        Route::delete('/failed/{failedJob}', [TeamleaderJobController::class, 'clearFailedJob'])->name('failed.clear-one');
    });

Route::middleware(['auth', 'can:tl.view'])
    ->prefix('teamleader')
    ->name('teamleader.')
    ->group(function () {

        Route::get('contacts',        [TlContactController::class, 'table'])->name('contacts.index');
        Route::get('contacts/{id}',   [TlContactController::class, 'show'])->name('contacts.show');

        Route::get('projects',        [TlProjectController::class, 'table'])->name('projects.index');
        Route::get('projects/{id}',   [TlProjectController::class, 'show'])->name('projects.show');

        Route::get('invoices',        [TlInvoiceController::class, 'table'])->name('invoices.index');
        Route::get('invoices/{id}',   [TlInvoiceController::class, 'show'])->name('invoices.show');

        Route::get('invoices/{id}/pdf', TlInvoicePdfController::class)->name('invoices.pdf');

    });

Route::middleware(['auth'])->group(function () {
    Route::get('/strategic-suggestions', [StrategicSuggestionController::class, 'main'])
        ->name('strategic-suggestions.index');

    Route::get('/strategic-suggestions/create', [StrategicSuggestionController::class, 'create'])
        ->name('strategic-suggestions.create');

    Route::post('/strategic-suggestions', [StrategicSuggestionController::class, 'store'])
        ->name('strategic-suggestions.store');

    Route::get('/strategic-suggestions/{strategic_suggestion}', [StrategicSuggestionController::class, 'show'])
        ->name('strategic-suggestions.show');

    Route::put('/strategic-suggestions/{strategic_suggestion}', [StrategicSuggestionController::class, 'update'])
        ->name('strategic-suggestions.update');

    Route::delete('/strategic-suggestions/{strategic_suggestion}', [StrategicSuggestionController::class, 'destroy'])
        ->name('strategic-suggestions.destroy');

    Route::post('/strategic-suggestions/{suggestion}/reply', [StrategicSuggestionController::class, 'reply'])
        ->name('strategic-suggestions.reply');

    Route::get('/strategic-suggestion-attachments/{attachment}/download', [StrategicSuggestionAttachmentController::class, 'download'])
        ->name('strategic-suggestions.attachments.download');
});

Route::middleware(['auth'])->group(function () {
    Route::resource('hubspot-owners', HubspotOwnerController::class)
        ->except(['show']);

    Route::get('/ajax/users', [HubspotOwnerController::class, 'searchUsers'])
        ->name('ajax.users.search');

    Route::post('/hubspot-owners/{owner}/assign-user', [HubspotOwnerController::class, 'assign'])
        ->name('hubspot_owners.assign_user');

    Route::post('/hubspot-owners/users/{user}/task-assignment', [HubspotOwnerController::class, 'toggleTaskAssignment'])
        ->name('hubspot_owners.task_assignment');
});

Route::prefix('crud/lists')->name('crud.lists.')->group(function () {
    Route::get('/', [ListController::class, 'get'])->name('index')->middleware('can:lists.view');
    Route::get('/create', [ListController::class, 'create'])->name('create')->middleware('can:lists.create');
    Route::post('/', [ListController::class, 'store'])->name('store')->middleware('can:lists.create');

    Route::get('/{lista}', [ListController::class, 'show'])->name('show')->middleware('can:lists.view');
    Route::get('/{lista}/edit', [ListController::class, 'edit'])->name('edit')->middleware('can:lists.edit');
    Route::put('/{lista}', [ListController::class, 'update'])->name('update')->middleware('can:lists.edit');
    Route::delete('/{lista}', [ListController::class, 'destroy'])->name('destroy')->middleware('can:lists.delete');

    // miembros
    Route::post('/{lista}/members/add', [ListController::class, 'addMembers'])
        ->name('members.add')->middleware('can:administrador');

    Route::post('/{lista}/members/import/preview', [ListController::class, 'previewMembersImport'])
        ->name('members.import.preview')->middleware('can:administrador');

    Route::post('/{lista}/members/import/process', [ListController::class, 'processMembersImport'])
        ->name('members.import.process')->middleware('can:administrador');

    Route::delete('/{lista}/members/{user}', [ListController::class, 'removeMember'])
        ->name('members.remove')->middleware('can:administrador');

    // marcar contactado
    Route::patch('/{lista}/members/{user}/contacted', [ListController::class, 'setContacted'])
        ->name('members.contacted')->middleware('can:lists.manage_members');
});

Route::get('/crud/users/{user}/edit-basic', [UserController::class, 'editBasic'])
    ->name('crud.users.editBasic');

Route::put('/crud/users/{user}/update-basic', [UserController::class, 'updateBasic'])
    ->name('crud.users.updateBasic');

Route::get('/registerv2', [RegisterV2Controller::class, 'index'])->name('register.v2.form');

Route::post('/registerv2', [RegisterV2Controller::class, 'store'])->name('register.v2');

Route::get('/registro-coordinador', [ProveedorRegisterController::class, 'create'])->name('proveedor.register');
Route::post('/registro-coordinador', [ProveedorRegisterController::class, 'store'])->name('proveedor.register.store');

Route::middleware(['auth', 'estado.vendedor'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});

Route::middleware(['auth', 'can:news.admin'])->group(function () {
    Route::get('news/admin', [NewsController::class, 'admin'])->name('news.admin');
    Route::post('news', [NewsController::class, 'store'])->name('news.store');
    Route::put('news/{news}', [NewsController::class, 'update'])->name('news.update');
    Route::delete('news/{news}', [NewsController::class, 'destroy'])->name('news.destroy');
});

Route::middleware(['auth', 'can:docs.view'])
    ->get('/docs', [DocumentController::class, 'library'])
    ->name('docs.index');

Route::middleware(['auth', 'can:docs.view'])
    ->get('/docs/{id}/download', [DocumentController::class, 'download'])
    ->name('docs.download');

// Admin (opcional)
Route::middleware(['auth', 'can:docs.upload'])
    ->get('/admin/docs', [DocumentController::class, 'admin'])
    ->name('docs.admin');

Route::middleware(['auth', 'can:docs.upload'])
    ->post('/admin/docs', [DocumentController::class, 'store'])
    ->name('docs.store');

Route::middleware(['auth', 'can:docs.upload'])
    ->put('/admin/docs/{id}', [DocumentController::class, 'update'])
    ->name('docs.update');

Route::middleware(['auth', 'can:docs.delete'])
    ->delete('/admin/docs/{id}', [DocumentController::class, 'destroy'])
    ->name('docs.destroy');

// Vista inicio
Route::get('/', [Controller::class, 'index'])->name('inicio')->middleware(['auth', 'verified']);

Route::get('listProjectsWithProductoField', [ClienteController::class, 'listProjectsWithProductoField'])->name('listProjectsWithProductoField');

Route::prefix('admin/procesos')->middleware(['auth'])->group(function () {
    Route::get('/', [CosPasoEditorController::class, 'index'])->name('admin.procesos.index');
    Route::get('/{cos}', [CosPasoEditorController::class, 'show'])->name('admin.procesos.show');
    Route::put('/pasos/{paso}', [CosPasoEditorController::class, 'updatePasoFull'])->name('admin.procesos.pasos.updateFull');
});

// Grupo de rutas CRUD
Route::group(['middleware' => ['auth'], 'as' => 'crud.'], function(){
    Route::resource('permissions', PermissionController::class)->names('permissions')
			->middleware('can:crud.permissions.index');
    Route::resource('roles', RoleController::class)->names('roles')
			->middleware('can:crud.roles.index');
    Route::resource('users', UserController::class)->names('users')
			->middleware('can:crud.users.index');
    Route::post('users/{user}/cos-review-task', [UserController::class, 'requestCosReviewTask'])
            ->name('users.cos-review-task')
            ->middleware('can:crud.users.index');
    Route::post('users/{user}/notify-cos-status', [UserController::class, 'notifyCosStatusUpdate'])
            ->name('users.notify-cos-status')
            ->middleware('can:crud.users.index');
    Route::get('users/{user}/internal-chat', [ClientChatController::class, 'messages'])
            ->name('users.internal-chat.index')
            ->middleware('can:crud.users.index');
    Route::get('users/{user}/internal-chat/mentions', [ClientChatController::class, 'mentionableUsers'])
            ->name('users.internal-chat.mentions')
            ->middleware('can:crud.users.index');
    Route::post('users/{user}/internal-chat', [ClientChatController::class, 'storeMessage'])
            ->name('users.internal-chat.store')
            ->middleware('can:crud.users.index');
    Route::get('users/{user}/internal-chat/attachments/{attachment}', [ClientChatController::class, 'downloadChatAttachment'])
            ->name('users.internal-chat.attachments.download')
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

Route::post('/sincronizarhsytl', [NegocioController::class, 'sincronizarhsytl'])->name('sincronizarhsytl');
Route::post('/guardarfase1', [NegocioController::class, 'guardarfase1'])->name('guardarfase1');
Route::post('/guardarfase2', [NegocioController::class, 'guardarfase2'])->name('guardarfase2');
Route::post('/guardarfase3', [NegocioController::class, 'guardarfase3'])->name('guardarfase3');
Route::post('/guardarcartanat', [NegocioController::class, 'guardarcartanat'])->name('guardarcartanat');
Route::post('/guardarfcjecil', [NegocioController::class, 'guardarfcjecil'])->name('guardarfcjecil');
Route::post('/deals/{id}/update', [NegocioController::class, 'update'])->name('deals.update');

Route::post('/exonerarfase1', [NegocioController::class, 'exonerarfase1'])->name('exonerarfase1');
Route::post('/exonerarfase2', [NegocioController::class, 'exonerarfase2'])->name('exonerarfase2');
Route::post('/exonerarfase3', [NegocioController::class, 'exonerarfase3'])->name('exonerarfase3');
Route::post('/exonerarcartanat', [NegocioController::class, 'exonerarcartanat'])->name('exonerarcartanat');
Route::post('/exonerarfcjecil', [NegocioController::class, 'exonerarcilfcje'])->name('exonerarcilfcje');
Route::post('/incluidofase1cilfcje', [NegocioController::class, 'incluidofase1cilfcje'])->name('incluidofase1cilfcje');

Route::post('etiquetasgenealogiamonday', [EtiquetaGenealogiaController::class, 'update'])->name('etiquetasgenealogiamonday');

Route::get('diarioindex',[ReportController::class, 'diarioindex'])->name('diarioindex')->middleware('can:reportes.index');
Route::get('reportes/dashboard',[ReportController::class, 'dashboard'])->name('reportes.dashboard')->middleware('can:reportes.index');
Route::get('mensualindex',[ReportController::class, 'mensualindex'])->name('mensualindex')->middleware('can:reportes.index');
Route::get('anualindex',[ReportController::class, 'anualindex'])->name('anualindex')->middleware('can:reportes.index');
Route::get('semanalindex',[ReportController::class, 'semanalindex'])->name('semanalindex')->middleware('can:reportes.index');
Route::post('getreportediario',[ReportController::class, 'getreportediario'])->name('getreportediario')->middleware('can:reportes.index');
Route::post('getreportemensual',[ReportController::class, 'getreportemensual'])->name('getreportemensual')->middleware('can:reportes.index');
Route::post('getreportesemanal',[ReportController::class, 'getreportesemanal'])->name('getreportesemanal')->middleware('can:reportes.index');
Route::post('getreporteanual',[ReportController::class, 'getreporteanual'])->name('getreporteanual')->middleware('can:reportes.index');

Route::post('changepassword',[UserController::class, 'mypassword'])->name('changepassword');
Route::post('adminchangepassword',[UserController::class, 'adminchangepassword'])->name('adminchangepassword');

//TeamleaderTest

Route::middleware(['auth'])->group(function () {
    Route::get('/teamleader/redirect', [TeamleaderController::class, 'redirectToProvider'])->name('teamleader.redirect');
    Route::get('/teamleader/callback', [TeamleaderController::class, 'handleProviderCallback'])->name('teamleader.callback');
});
Route::get('/teamleader/success', [TeamleaderController::class, 'success'])->name('teamleader.success');
//Route::get('/teamleader/contacts', [TeamleaderController::class, 'getContacts'])->name('teamleader.contacts');

//checkRegMondayTest
Route::get('/checkMondayTest', [ClienteController::class, 'checkMondayTest'])->name('checkMondayTest');

Route::get('/deal/{id}/edit', [NegocioController::class, 'edit'])->name('deals.edit');

Route::get('/prompttreena', [TreenaController::class, 'index'])->name('treena.index');

Route::post('/updatetreena', [TreenaController::class, 'update'])->name('treena.update');

//Ruta GEDCOM por cliente
Route::get('/downloadTree/{id}', [GedcomController::class, 'getGedcomCliente'])
    ->name('getGedcomCliente')
    ->middleware(['auth', 'can:administrador']);

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

Route::prefix('banca-online-2026')
    ->name('banca-online.')
    ->group(function () {
        Route::get('/', [BancaOnlineController::class, 'landing'])->name('index');
        Route::get('/cliente', [BancaOnlineController::class, 'lookupClient'])->name('client.lookup');
        Route::get('/pago/{token}', [BancaOnlineController::class, 'payment'])->name('payment');
        Route::post('/pago/{token}/stripe', [BancaOnlineController::class, 'processPayment'])->name('payment.process');
        Route::get('/gracias/{token}', [BancaOnlineController::class, 'thankYou'])->name('thank-you');
        Route::get('/{country}', [BancaOnlineController::class, 'landingForCountry'])
            ->whereIn('country', ['espana', 'portugal', 'italia'])
            ->name('country');
        Route::get('/{country}/{plan}', [BancaOnlineController::class, 'configureForCountry'])->name('configure.country');
        Route::post('/{country}/{plan}', [BancaOnlineController::class, 'checkoutForCountry'])->name('checkout.country');
        Route::get('/{plan}', [BancaOnlineController::class, 'configure'])->name('configure');
        Route::post('/{plan}', [BancaOnlineController::class, 'checkout'])->name('checkout');
    });

Route::middleware(['auth', 'can:administrador'])
    ->prefix('admin/banca-online-2026')
    ->name('admin.banca-online.')
    ->group(function () {
        Route::get('/', [AdminBancaOnlineController::class, 'index'])->name('index');
        Route::post('/sync', [AdminBancaOnlineController::class, 'sync'])->name('sync');
        Route::post('/items', [AdminBancaOnlineController::class, 'store'])->name('items.store');
        Route::put('/items/{servicio}', [AdminBancaOnlineController::class, 'update'])->name('items.update');
        Route::put('/packages/{servicio}', [AdminBancaOnlineController::class, 'updatePackage'])->name('packages.update');
    });

//panel produccion y ventas status
Route::get('/clientes/status/{agcliente}', [UserController::class, 'getuserstatus_ventas'])->name('getuserstatus_ventas');

//panel CLIENTE status
Route::get('/my_status', [UserController::class, 'my_status'])->name('my_status');

//panel Pasaportes erroneos
Route::get('/fixpassport', [UserController::class, 'fixpassport'])->name('fixpassport')->middleware('can:administrador');

Route::get('/cosvisitas', [CosVisitController::class, 'listado'])->name('cosvisitas')->middleware('can:cosvisitas.index');

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
Route::get('/gedcomexport', [GedcomController::class, 'gedcomexport'])->name('gedcomexport')->middleware(['auth', 'can:administrador']);
Route::get('/getGedcomGlobal', [GedcomController::class, 'getGedcomGlobal'])->name('getGedcomGlobal')->middleware(['auth', 'can:administrador']);
Route::post('/gedcom/import', [GedcomController::class, 'import'])->name('gedcom.import')->middleware(['auth', 'can:administrador']);

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

Route::group(['middleware' => ['auth', 'can:genealogista']], function(){
    Route::resource('family-groups', FamilyGroupController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('family-groups/{familyGroup}/recalculate', [FamilyGroupController::class, 'recalculate'])
        ->name('family-groups.recalculate');
    Route::post('family-groups/{familyGroup}/members', [FamilyGroupController::class, 'addMember'])
        ->name('family-groups.members.store');
    Route::delete('family-groups/{familyGroup}/members/{member}', [FamilyGroupController::class, 'removeMember'])
        ->name('family-groups.members.destroy');
});

// Grupo de rutas para vistas de árboles genealógicos
Route::group(['middleware' => ['auth'], 'as' => 'arboles.'], function(){
    Route::get('albero/{IDCliente}', [AlberoController::class, 'arbelo'])->name('albero.index')
        ->middleware('can:genealogista');
    Route::get('tree/{IDCliente}', [TreeController::class, 'tree'])->name('tree.index')
        ->middleware('can:genealogista');
    Route::get('tree/{IDCliente}/branch/{id}/{gen}/{parent}', [TreeController::class, 'branch'])->name('tree.branch')
        ->middleware('can:genealogista');
    Route::post('tree/{IDCliente}/line-color/{id}', [TreeController::class, 'updateLineColor'])->name('tree.line-color')
        ->middleware('can:genealogista');
    Route::get('tree/{IDCliente}/person/{id}/detail', [TreeController::class, 'personDetail'])->name('tree.person-detail')
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
    Route::get('pagospendientes', [ClienteController::class, 'pagospendientes'])->name('pagospendientes')
        ->middleware('can:cliente');
    Route::get('status', [ClienteController::class, 'status'])->name('status')
    ->middleware('can:cliente');
});

Route::middleware(['auth', 'can:cliente'])
    ->prefix('servicios-disponibles')
    ->name('service-store.')
    ->group(function () {
        Route::get('/', [ServiceStoreController::class, 'index'])->name('index');
        Route::get('/{servicio}', [ServiceStoreController::class, 'show'])->name('show');
        Route::post('/{servicio}', [ServiceStoreController::class, 'purchase'])->name('purchase');
    });

Route::get('testcorreos', [CorreoController::class, 'testcorreos'])->name('testcorreos');
Route::post('testcorreos', [CorreoController::class, 'sendcorreo'])->name('sendcorreo');


Route::post('getinfo', [ClienteController::class, 'procesargetinfo'])->name('procesargetinfo')
        ->middleware('can:cliente');

Route::get('checkRegAlzada', [ClienteController::class, 'checkRegAlzada'])->name('checkRegAlzada');

Route::post('gotopayfases', [ClienteController::class, 'gotopayfases'])->name('gotopayfases')
        ->middleware('can:cliente');

Route::post('pay', [ClienteController::class, 'procesarpay'])->name('procesarpay')
        ->middleware('can:cliente');
Route::post('payfases', [ClienteController::class, 'procesarpayfases'])->name('procesarpayfases')
        ->middleware('can:cliente');

Route::post('/procesarpaypalfases', [ClienteController::class, 'procesarpaypalfases'])->name('procesarpaypalfases');

Route::post('/procesarpaypal', [ClienteController::class, 'procesarPaypal'])->name('procesarpaypal');

// 1. Ruta GET para mostrar el formulario (C, R)
Route::get('/whatsapp/url', [WhatsappBotURLController::class, 'index'])->name('whatsapp.url.form');

Route::resource('/whatsapp/numbers', ReportPhoneNumbersController::class)
    ->names('whatsapp.numbers') // Define el prefijo de nombre de ruta
    ->except(['create', 'show', 'edit']);

// 2. Ruta POST para manejar el envío y la lógica de upsert (C, U)
Route::post('/whatsapp/url', [WhatsappBotURLController::class, 'storeOrUpdate'])->name('whatsapp.url.store');

Route::get('/revisarcupon', [ClienteController::class, 'revisarcupon'])->name('revisarcupon')
        ->middleware('can:cliente');

Route::post('/procesar-pago-stripe', [ClienteController::class, 'procesarPagoStripe'])->name('procesar-pago-stripe');

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

// Rutas para administradores
Route::prefix('admin/requests')->group(function () {
    Route::post('/{user}', [DocumentRequestController::class, 'store'])->name('admin.requests.store');
    // routes/web.php o routes/api.php
    Route::put('/{documentRequest}', [DocumentRequestController::class, 'update'])->name('admin.requests.update');
    Route::delete('/{request}', [DocumentRequestController::class, 'destroy'])->name('admin.requests.destroy');
    Route::post('/{documentRequest}/approve', [DocumentRequestController::class, 'approve'])
         ->name('admin.requests.approve');              // →

    Route::post('/{documentRequest}/reject', [DocumentRequestController::class, 'reject'])
         ->name('admin.requests.reject');               // →
});

// Rutas para clientes
Route::prefix('client/requests')->group(function () {
    Route::post('/{documentRequest}/upload', [DocumentRequestController::class, 'upload'])->name('upload');
    Route::post('/{documentRequest}/no-doc', [DocumentRequestController::class, 'noDoc'])->name('no_doc');
});


Route::get('/cron/scheduler-run', function () {
    // Ejecuta el scheduler
    Artisan::call('schedule:run');
    return response()->json([
        'status' => 'ok',
        'message' => 'Laravel scheduler ejecutado correctamente',
        'time' => now()->toDateTimeString(),
    ]);
});

Route::get('/cron/queue-worker', function () {
    // Ejecutar jobs pendientes
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--tries' => 3,
        '--max-time' => 60
    ]);

    return response()->json([
        'status' => 'ok',
        'timestamp' => now()
    ]);
});

// routes/web.php (solo para admins)
Route::post('/admin/process-queue', function () {
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    return back()->with('success', 'Jobs procesados');
});

Route::get('/cron/followups-registration-payment', function () {
    Artisan::call('followups:registration-payment', ['--no-interaction' => true]);

    return response()->json([
        'ok' => true,
        'output' => Artisan::output(),
        'ts' => now(),
    ]);
});

Route::get('/deploy', [DeployController::class, 'deploy'])->name('deploy.run');

Route::get('/hubspot/sync-client-owners', function () {

    Artisan::call('hubspot:sync-client-owners --match=email');

    return response()->json([
        'status' => 'ok',
        'output' => Artisan::output(),
    ]);

})->name('hubspot.sync-client-owners');
