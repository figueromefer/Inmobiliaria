<?php

use Illuminate\Support\Facades\Route;
use App\Models\Cliente; // <— IMPORTANTE para el endpoint JSON

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ClienteController as ClienteCtl;
use App\Http\Controllers\PropiedadController;
use App\Http\Controllers\InquilinoController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\OperacionController;

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

// Endpoint para Google Forms
Route::get('/api/clientes', function () {
    $token = request('token');
    abort_unless($token && hash_equals($token, env('FORMS_TOKEN')), 401);

    return response()->json(
        Cliente::orderBy('nombre')->get(['pk_cliente','nombre'])
    );
})->name('api.clientes');

// Rutas internas autenticadas
Route::middleware('auth')->group(function () {
    Route::resource('clientes', ClienteCtl::class);

    Route::get('/propiedades/mapa', [PropiedadController::class, 'mapa'])->name('propiedades.mapa');
    Route::resource('propiedades', PropiedadController::class)->parameters([
        'propiedades' => 'propiedad',
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

require __DIR__ . '/auth.php';
