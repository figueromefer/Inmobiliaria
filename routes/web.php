<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PropiedadController;
use App\Http\Controllers\InquilinoController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\OperacionController;
use App\Models\Cliente;
use Illuminate\Support\Facades\Route;

Route::get('/api/clientes', function () {
    $token = request('token');
    abort_unless($token && hash_equals($token, env('FORMS_TOKEN')), 401);

    return response()->json(
        Cliente::orderBy('nombre')->get(['pk_cliente', 'nombre'])
    );
})->name('api.clientes');

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

    Route::middleware('auth')->group(function () {
    Route::resource('clientes', ClienteController::class);
    Route::get('/propiedades/mapa', [PropiedadController::class, 'mapa'])->name('propiedades.mapa');
    Route::resource('propiedades', PropiedadController::class)->parameters([
    'propiedades' => 'propiedad'
]);
    

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario');
    Route::get('/inquilinos', [InquilinoController::class, 'index'])->name('inquilinos');
    Route::get('/contratos', [ContratoController::class, 'index'])->name('contratos');

    // Submenú Operaciones
    Route::get('/operaciones/pago-renta', [OperacionController::class, 'pagoRenta'])->name('operaciones.pago-renta');
    Route::get('/operaciones/deposito-garantia', [OperacionController::class, 'depositoGarantia'])->name('operaciones.deposito-garantia');
    Route::get('/operaciones/gastos-propiedad', [OperacionController::class, 'gastosPropiedad'])->name('operaciones.gastos-propiedad');
});



require __DIR__.'/auth.php';
