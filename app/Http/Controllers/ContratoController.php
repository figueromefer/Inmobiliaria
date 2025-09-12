<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Inquilino;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Cliente; 
use App\Http\Requests\ContratoRequest;
use Carbon\Carbon;

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        // === Filtros ===
        $q           = trim((string) $request->query('q', ''));         // búsqueda libre (cliente, propiedad, domicilio_inmueble)
        $solicitante = trim((string) $request->query('solicitante', ''));// LEGADO: nombre del cliente (por compat)
        $clienteId   = (int) $request->query('cliente_id', 0);          // NUEVO: filtro por id de cliente
        $desde       = $request->query('desde');                        // yyyy-mm-dd
        $hasta       = $request->query('hasta');                        // yyyy-mm-dd
        $perPage     = (int) $request->query('perPage', 15);
        if ($perPage < 5 || $perPage > 100) $perPage = 15;

        // === Orden ===
        $sort = $request->query('sort', 'fecha');
        $dir  = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Campos permitidos para ordenar:
        // - 'cliente' es un alias lógico (ordenará por clientes.nombre vía join)
        // - si quieres también por 'propiedad', puedes agregar un bloque similar más abajo
        $sortable = [
            'id','tipo_solicitante','fecha','fecha_inicio','fecha_fin',
            'comision_renta','comision_mensual','monto_mensual','created_at','cliente'
        ];

        // === Query base con relaciones ===
        $query = Contrato::query()->with(['inquilino','cliente','propiedad']);

        // === Búsqueda libre ===
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->whereHas('cliente', function($c) use ($q){
                        $c->where('nombre','like',"%{$q}%");
                    })
                  ->orWhereHas('propiedad', function($p) use ($q){
                        $p->where('alias','like',"%{$q}%")
                          ->orWhere('domicilio','like',"%{$q}%");
                    })
                  ->orWhere('domicilio_inmueble','like',"%{$q}%");
            });
        }

        // === Filtro por cliente (preferir cliente_id) ===
        if ($clienteId > 0) {
            $query->where('fk_cliente', $clienteId);
        } elseif ($solicitante !== '') {
            // Compatibilidad: si aún te llega ?solicitante=Nombre, lo mapeamos a fk_cliente
            $cid = (int) Cliente::where('nombre', $solicitante)->value('pk_cliente');
            if ($cid > 0) {
                $query->where('fk_cliente', $cid);
            } else {
                // si no existe, forzamos un resultado vacío
                $query->whereRaw('1=0');
            }
        }

        // === Rango de fechas (por fecha de alta del contrato) ===
        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        // === Orden ===
        if (!in_array($sort, $sortable, true)) $sort = 'fecha';

        if ($sort === 'cliente') {
            // Ordenar por nombre de cliente requiere join; seleccionamos contratos.* para evitar columnas duplicadas
            $query->leftJoin('clientes', 'contratos.fk_cliente', '=', 'clientes.pk_cliente')
                  ->orderBy('clientes.nombre', $dir)
                  ->select('contratos.*');
        } else {
            // ordenar por columnas propias de contratos
            $query->orderBy($sort, $dir);
        }

        // === Paginación ===
        $contratos = $query->paginate($perPage)->appends([
            'q' => $q,
            'solicitante' => $solicitante, // legado
            'cliente_id'  => $clienteId,    // nuevo
            'desde' => $desde,
            'hasta' => $hasta,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        // === Calcular proximidad a vencimiento ===
        $contratos->getCollection()->transform(function ($contrato) {
            $fechaFin = Carbon::parse($contrato->fecha_fin);
            // true si la fecha fin es dentro de 2 meses desde hoy (o antes)
            $contrato->por_expirar = Carbon::now()->diffInMonths($fechaFin, false) <= 2;
            return $contrato;
        });

        // === Datos para selects en la vista ===
        // LEGADO (tu vista actual quizá espera esto):
        $solicitantes = Cliente::orderBy('nombre')->pluck('nombre')->all();

        // NUEVO (recomendado para migrar el filtro a cliente_id):
        $clientes = Cliente::orderBy('nombre')->get(['pk_cliente as id','nombre']);

        return view('contratos.index', compact(
            'contratos',
            'solicitantes', // legado (array de nombres)
            'clientes',     // nuevo (id + nombre)
            'q','solicitante','clienteId','desde','hasta','perPage','sort','dir'
        ));
    }
}
