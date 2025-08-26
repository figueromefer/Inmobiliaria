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

Route::get('/__backfill_contratos_fk__', [BackfillContratosController::class, 'run']);


Route::get('/__clear_caches__', function () {
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return 'Caches cleared';
});

Route::get('/__migrate_status__', function () {
    Artisan::call('migrate:status');
    return nl2br(e(Artisan::output()));
});

Route::get('/__migrate_dry_run__', function () {
    // Muestra el SQL que se intentaría ejecutar (no aplica cambios)
    Artisan::call('migrate', ['--pretend' => true]);
    return nl2br(e(Artisan::output()));
});

Route::get('/__run_migrate__', function () {
    Artisan::call('migrate', ['--force' => true]);
    return nl2br(Artisan::output());
});





// Home: si hay sesión -> dashboard; si no -> login
Route::match(['GET','HEAD'], '/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('auth.login');
})->name('home');


// Login y Forgot Password (como vistas; usa tus blades reales)
Route::view('/login', 'auth.login')->name('login')->middleware('guest');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request')->middleware('guest');

// Dashboard autenticado (quita 'verified' si no usas verificación por correo)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');


// Rutas internas autenticadas
Route::middleware('auth')->group(function () {
    Route::resource('clientes', ClienteCtl::class);

    Route::get('/propiedades/mapa', [PropiedadController::class, 'mapa'])->name('propiedades.mapa');
    Route::resource('propiedades', PropiedadController::class)->parameters(['propiedades' => 'propiedad',]);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/calendario', [ContratoCalendarController::class, 'index'])->name('calendario.index');
    Route::get('/inquilinos', [InquilinoController::class, 'index'])->name('inquilinos.index');
    Route::get('/contratos', [ContratoController::class, 'index'])->name('contratos.index');

    Route::get('/movimientos',            [MovimientoController::class, 'index'])->name('movimientos.index');
    Route::get('/movimientos/crear',      [MovimientoController::class, 'create'])->name('movimientos.create');
    Route::post('/movimientos',           [MovimientoController::class, 'store'])->name('movimientos.store');

    // Endpoint para llenar el select de propiedades según cliente elegido
    Route::get('/movimientos/propiedades-por-cliente/{cliente}', [MovimientoController::class, 'propiedadesPorCliente'])->name('movimientos.propiedadesPorCliente');

    Route::get('/reportes/mensual', [ReporteMensualController::class, 'index'])->name('reportes.mensual');
});

require __DIR__ . '/auth.php';
