<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClientesApiController;
use App\Http\Controllers\Api\FormsIntakeController;
use App\Http\Controllers\ContratoCalendarController;

Route::get('/clientes', [ClientesApiController::class, 'index']); // pública
Route::post('/forms/contratos', [FormsIntakeController::class, 'storeContrato']);
Route::get('/contratos/events', [ContratoCalendarController::class, 'events'])->name('api.contratos.events');

Route::get('/clientes/{cliente}/propiedades', function ($cliente) {
    return \App\Models\Propiedad::where('fk_cliente', $cliente)
        ->select('pk_propiedad','alias','domicilio')
        ->orderBy('alias')->get();
});
