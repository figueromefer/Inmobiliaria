<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClientesApiController;
use App\Http\Controllers\Api\FormsIntakeController;
use App\Http\Controllers\ContratoCalendarController;

Route::get('/clientes', [ClientesApiController::class, 'index']); // pública
Route::post('/forms/contratos', [FormsIntakeController::class, 'storeContrato']);
Route::get('/contratos/events', [ContratoCalendarController::class, 'events'])->name('api.contratos.events');