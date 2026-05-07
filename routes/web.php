<?php

use Illuminate\Support\Facades\Route;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ClienteController as ClienteCtl;
use App\Http\Controllers\PropiedadController;
use App\Http\Controllers\InquilinoController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\OperacionController;
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

Route::match(['GET', 'HEAD'], '/', function () {
    return auth()->check()
        ? redirect()->route('calendario.index')
        : view('auth.login');
})->name('home');

Route::middleware(['auth'])->group(function () {

    Route::get('/bitacora', [ActivityLogController::class, 'index'])
        ->name('bitacora.index');

    Route::resource('clientes', ClienteCtl::class);
    Route::resource('propiedades', PropiedadController::class);
});

require __DIR__ . '/auth.php';
