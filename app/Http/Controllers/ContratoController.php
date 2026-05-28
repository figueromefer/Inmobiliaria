<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Services\JusticiaAlternativaImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            if (!$contrato->fecha_fin) {
                $contrato->por_expirar = false;
                return $contrato;
            }

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

    public function showImportJusticiaAlternativaForm()
    {
        return view('contratos.justicia-alternativa');
    }

    public function previewJusticiaAlternativa(Request $request, JusticiaAlternativaImportService $service)
    {
        $validated = $request->validate([
            'expediente' => ['required', 'string', 'max:255'],
        ]);

        $expediente = trim($validated['expediente']);

        $existing = Contrato::where('expediente_justicia_alternativa', $expediente)->first();
        if ($existing) {
            return back()
                ->withInput()
                ->withErrors(['expediente' => 'Este expediente ya fue importado en el contrato #'.$existing->id.'.']);
        }

        $remote = $service->fetchByExpediente($expediente);
        if (!($remote['ok'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors(['expediente' => $remote['message'] ?? 'No se pudo consultar el expediente.']);
        }

        if (($remote['status'] ?? null) === 'duplicate') {
            return back()
                ->withInput()
                ->withErrors(['expediente' => 'Se encontró más de una respuesta con el mismo expediente en Justicia Alternativa. Corrige el Google Sheet antes de importar.']);
        }

        if (($remote['status'] ?? null) === 'not_found') {
            return back()
                ->withInput()
                ->withErrors(['expediente' => 'No se encontró ningún expediente con ese número.']);
        }

        $row = $remote['data'] ?? null;
        if (!is_array($row)) {
            return back()
                ->withInput()
                ->withErrors(['expediente' => 'La respuesta de Justicia Alternativa no contiene datos válidos.']);
        }

        $mapped = $service->mapPayload($row);
        $matches = $service->resolveMatches($mapped);

        $clientes = Cliente::orderBy('nombre')->get(['pk_cliente', 'nombre', 'correo', 'rfc']);
        $propiedades = Propiedad::orderBy('alias')->orderBy('domicilio')->get(['pk_propiedad', 'fk_cliente', 'alias', 'domicilio']);
        $inquilinos = Inquilino::orderBy('nombre')->get(['id', 'nombre', 'correo', 'telefono']);

        return view('contratos.justicia-alternativa-preview', compact(
            'expediente',
            'row',
            'mapped',
            'matches',
            'clientes',
            'propiedades',
            'inquilinos'
        ));
    }

    public function storeJusticiaAlternativa(Request $request, JusticiaAlternativaImportService $service)
    {
        $validated = $request->validate([
            'expediente' => ['required', 'string', 'max:255'],
            'fk_cliente' => ['required', 'integer', 'exists:clientes,pk_cliente'],
            'fk_propiedad' => ['required', 'integer', 'exists:propiedades,pk_propiedad'],
            'inquilino_id' => ['nullable', 'integer', 'exists:inquilinos,id'],
        ]);

        $expediente = trim($validated['expediente']);

        $existing = Contrato::where('expediente_justicia_alternativa', $expediente)->first();
        if ($existing) {
            return redirect()
                ->route('contratos.index')
                ->with('error', 'Este expediente ya fue importado en el contrato #'.$existing->id.'.');
        }

        $remote = $service->fetchByExpediente($expediente);
        if (!($remote['ok'] ?? false)) {
            return back()->withInput()->withErrors([
                'expediente' => $remote['message'] ?? 'No se pudo consultar nuevamente el expediente.',
            ]);
        }

        $row = $remote['data'] ?? null;
        if (!is_array($row)) {
            return back()->withInput()->withErrors([
                'expediente' => 'La respuesta de Justicia Alternativa no contiene datos válidos.',
            ]);
        }

        $mapped = $service->mapPayload($row);

        $contrato = DB::transaction(function () use ($validated, $mapped, $row, $expediente) {
            $inquilinoId = $validated['inquilino_id'] ?? null;

            if (!$inquilinoId && !empty($mapped['nombre_complementaria'])) {
                $inquilino = Inquilino::create([
                    'nombre' => $mapped['nombre_complementaria'],
                    'nacionalidad' => $mapped['nacionalidad_complementaria'] ?? null,
                    'domicilio' => $mapped['domicilio_complementaria'] ?? null,
                    'telefono' => $mapped['telefono_complementaria'] ?? null,
                    'correo' => $mapped['correo_complementaria'] ?? null,
                ]);

                $inquilinoId = $inquilino->id;
            }

            return Contrato::create([
                'fk_cliente' => $validated['fk_cliente'],
                'fk_propiedad' => $validated['fk_propiedad'],
                'tipo_solicitante' => $mapped['tipo_solicitante'] ?? null,
                'tipo_complementaria' => $mapped['tipo_complementaria'] ?? null,
                'fecha' => now(),
                'inquilino_id' => $inquilinoId,
                'domicilio_inmueble' => $mapped['domicilio_inmueble_arrendamiento'] ?? null,
                'fecha_inicio' => $mapped['fecha_inicio_contrato'] ?? null,
                'fecha_fin' => $mapped['fecha_terminacion_contrato'] ?? null,
                'dias_pago' => $mapped['dias_pago'] ?? null,
                'monto_total' => $mapped['monto_total'] ?? null,
                'monto_mensual' => $mapped['monto_mensual'] ?? null,
                'monto_deposito' => $mapped['monto_deposito'] ?? null,
                'origen' => 'justicia_alternativa',
                'expediente_justicia_alternativa' => $expediente,
                'imported_at' => now(),
                'raw_justicia_alternativa' => $row,
            ]);
        });

        return redirect()
            ->route('contratos.index')
            ->with('success', 'Contrato de Justicia Alternativa importado correctamente. Contrato #'.$contrato->id.'.');
    }
}
