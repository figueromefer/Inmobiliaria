<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;

class ClientesApiController extends Controller
{
    public function index()
    {
        $clientes = Cliente::query()
            ->orderBy('nombre') // ajusta campo si difiere
            ->get(['pk_cliente', 'nombre']); // ajusta columnas

        return response()->json($clientes);
    }
}
