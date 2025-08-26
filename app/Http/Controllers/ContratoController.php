<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Inquilino;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Cliente; 

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        // Filtros
        $q            = trim((string)$request->query('q', ''));           // búsqueda libre: solicitante/domicilio
        $solicitante  = trim((string)$request->query('solicitante', '')); // ⟵ nuevo filtro por solicitante (cliente)
        $desde        = $request->query('desde');                         // yyyy-mm-dd
        $hasta        = $request->query('hasta');                         // yyyy-mm-dd
        $perPage      = (int)$request->query('perPage', 15);
        if ($perPage < 5 || $perPage > 100) $perPage = 15;

        // Orden
        $sort = $request->query('sort', 'fecha');
        $dir  = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id','tipo_solicitante','solicitante','fecha','fecha_inicio','fecha_fin','comision_renta','comision_mensual','monto_mensual','created_at'];

        $query = Contrato::query()->with('inquilino');

        // Búsqueda libre
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('solicitante', 'like', "%{$q}%")
                ->orWhere('domicilio_inmueble', 'like', "%{$q}%");
            });
        }

        // ⟵ Filtro por solicitante (match exacto con el nombre del cliente)
        if ($solicitante !== '') {
            $query->where('solicitante', $solicitante);
        }

        // Rango de fechas (por fecha de alta del contrato)
        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        // Orden
        if (!in_array($sort, $sortable, true)) $sort = 'fecha';
        $query->orderBy($sort, $dir);

        // Paginación
        $contratos = $query->paginate($perPage)->appends([
            'q' => $q,
            'solicitante' => $solicitante,
            'desde' => $desde,
            'hasta' => $hasta,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        // Lista para el <select> (clientes ordenados por nombre)
        $solicitantes = Cliente::orderBy('nombre')->pluck('nombre')->all();

        return view('contratos.index', compact(
            'contratos', 'solicitantes', 'q', 'solicitante', 'desde', 'hasta', 'perPage', 'sort', 'dir'
        ));
    }
}
