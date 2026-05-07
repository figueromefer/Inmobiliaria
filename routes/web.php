<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClienteController as ClienteCtl;
use App\Http\Controllers\PropiedadController;
use App\Http\Controllers\InquilinoController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ContratoCalendarController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\ReporteMensualController;
use App\Http\Controllers\BackfillContratosController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\Web\TicketWebController;
use App\Http\Controllers\PagoCalendarController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReporteGananciasClientesController;
use App\Http\Controllers\ActivityLogController;

Route::get('/__backfill_contratos_fk__', [BackfillContratosController::class, 'run']);

Route::get('/__migrate_status__', function () {
    Artisan::call('migrate:status');
    return nl2br(e(Artisan::output()));
});

Route::get('/__migrate_dry_run__', function () {
    Artisan::call('migrate', ['--pretend' => true]);
    return nl2br(e(Artisan::output()));
});

Route::get('/__run_migrate__', function () {
    Artisan::call('migrate', ['--force' => true]);
    return nl2br(Artisan::output());
});

Route::get('/__clear_caches__', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');

    return nl2br(Artisan::output() . "\nCaches limpiados ✔️");
});

Route::match(['GET', 'HEAD'], '/', function () {
    return auth()->check()
        ? redirect()->route('calendario.index')
        : view('auth.login');
})->name('home');

Route::view('/login', 'auth.login')->name('login')->middleware('guest');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request')->middleware('guest');

Route::get('/dashboard', function () {
    return redirect()->route('calendario.index');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth', 'can:manage-users'])->group(function () {
    Route::resource('users', UserController::class)->except(['show']);
});

Route::middleware('auth')->group(function () {

    Route::get('/tareas', [\App\Http\Controllers\TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tareas', [\App\Http\Controllers\TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tareas/{task}', [\App\Http\Controllers\TaskController::class, 'updateStatus'])->name('tasks.updateStatus');

    Route::get('/bitacora', [ActivityLogController::class, 'index'])->name('bitacora.index');

    Route::resource('clientes', ClienteCtl::class);

    Route::get('/propiedades/mapa', [PropiedadController::class, 'mapa'])->name('propiedades.mapa');
    Route::resource('propiedades', PropiedadController::class)->parameters(['propiedades' => 'propiedad']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/calendario', [ContratoCalendarController::class, 'index'])->name('calendario.index');
    Route::get('/inquilinos', [InquilinoController::class, 'index'])->name('inquilinos.index');
    Route::get('/contratos', [ContratoController::class, 'index'])->name('contratos.index');

    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');

    Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
    Route::get('/movimientos/crear', [MovimientoController::class, 'create'])->name('movimientos.create');
    Route::post('/movimientos', [MovimientoController::class, 'store'])->name('movimientos.store');
    Route::get('/movimientos/propiedades-por-cliente/{cliente}', [MovimientoController::class, 'propiedadesPorCliente'])->name('movimientos.propiedadesPorCliente');
    Route::get('/movimientos/{movimiento}/recibo', [MovimientoController::class, 'recibo'])->name('movimientos.recibo');

    Route::get('/reportes/mensual', [ReporteMensualController::class, 'index'])->name('reportes.mensual');
    Route::get('/reportes/mensual/pdf', [ReporteMensualController::class, 'pdf'])->name('reportes.mensual.pdf');
    Route::get('/reportes/ganancias-clientes', [ReporteGananciasClientesController::class, 'index'])->name('reportes.ganancias-clientes');

    Route::resource('documentos', DocumentoController::class);
    Route::get('documentos/{documento}/download', [DocumentoController::class, 'download'])->name('documentos.download');
    Route::get('documentos/{documento}/ver', [DocumentoController::class, 'view'])->name('documentos.view');

    Route::get('/tickets', [TicketWebController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketWebController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketWebController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketWebController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/edit', [TicketWebController::class, 'edit'])->name('tickets.edit');
    Route::put('/tickets/{ticket}', [TicketWebController::class, 'update'])->name('tickets.update');
    Route::delete('/tickets/{ticket}', [TicketWebController::class, 'destroy'])->name('tickets.destroy');
    Route::post('/tickets/{ticket}/comments', [TicketWebController::class, 'storeComment'])->name('tickets.comments.store');
    Route::patch('/tickets/{ticket}/status', [TicketWebController::class, 'updateStatus'])->name('tickets.status.update');

    Route::get('/calendario/eventos-adeudos', [\App\Http\Controllers\CalendarioController::class, 'eventosDeAdeudos'])->name('calendario.eventos-adeudos');
    Route::get('/pagos-pendientes/events', [PagoCalendarController::class, 'events'])->name('api.pagos.events');
});

require __DIR__ . '/auth.php';
