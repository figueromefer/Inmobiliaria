<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClientesApiController;
use App\Http\Controllers\Api\FormsIntakeController;
use App\Http\Controllers\ContratoCalendarController;
use App\Models\Propiedad;
use App\Models\Cliente;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketCommentController;

Route::get('/clientes', [ClientesApiController::class, 'index']); // pública
Route::post('/forms/contratos', [FormsIntakeController::class, 'storeContrato']);
Route::get('/contratos/events', [ContratoCalendarController::class, 'events'])->name('api.contratos.events');

Route::get('/clientes/{cliente}/propiedades', function ($cliente) {
    return \App\Models\Propiedad::where('fk_cliente', $cliente)
        ->select('pk_propiedad','alias','domicilio')
        ->orderBy('alias')->get();
});

Route::get('/clientes-with-propiedades', function () {
    return Cliente::with([
        'propiedades' => function ($q) {
            // Incluye fk_cliente para que Eloquent pueda empatar
            $q->orderBy('alias')
              ->select('pk_propiedad', 'fk_cliente', 'alias', 'domicilio');
        }
    ])
    ->orderBy('nombre')
    // NO alias aquí; deja pk_cliente tal cual para que Eloquent lo use como PK
    ->get(['pk_cliente', 'nombre']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

    Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
    Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
});

