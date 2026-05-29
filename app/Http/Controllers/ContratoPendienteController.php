<?php

namespace App\Http\Controllers;

use App\Models\ContratoPendiente;

class ContratoPendienteController extends Controller
{
    public function index()
    {
        $pendientes = ContratoPendiente::with([
            'cliente',
            'propiedad',
            'inquilino',
            'contrato',
        ])
        ->latest()
        ->paginate(20);

        return view('contratos.pendientes.index', compact('pendientes'));
    }

    public function show(ContratoPendiente $pendiente)
    {
        return view('contratos.pendientes.show', compact('pendiente'));
    }
}
