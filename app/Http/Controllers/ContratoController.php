<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Services\JusticiaAlternativaImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

        $sortable = [
            'id','tipo_solicitante','fecha','fecha_inicio','fecha_fin',
            'comision_renta','comision_mensual','monto_mensual','created_at','cliente'
        ];

        $query = Contrato::query()->with(['inquilino','cliente','propiedad']);

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

        if ($clienteId > 0) {
            $query->where('fk_cliente', $clienteId);
        } elseif ($solicitante !== '') {
            $cid = (int) Cliente::where('nombre', $solicitante)->value('pk_cliente');
            if ($cid > 0) {
                $query->where('fk_cliente', $cid);
            } else {
                $query->whereRaw('1=0');
            }
        }

        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        if (!in_array($sort, $sortable, true)) $sort = 'fecha';

        if ($sort === 'cliente') {
            $query->leftJoin('clientes', 'contratos.fk_cliente', '=', 'clientes.pk_cliente')
                  ->orderBy('clientes.nombre', $dir)
                  ->select('contratos.*');
        } else {
            $query->orderBy($sort, $dir);
        }

        $contratos = $query->paginate($perPage)->appends([
            'q' => $q,
            'solicitante' => $solicitante,
            'cliente_id'  => $clienteId,
            'desde' => $desde,
            'hasta' => $hasta,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        $contratos->getCollection()->transform(function ($contrato) {
            if (!$contrato->fecha_fin) {
                $contrato->por_expirar = false;
                return $contrato;
            }

            $fechaFin = Carbon::parse($contrato->fecha_fin);
            $contrato->por_expirar = Carbon::now()->diffInMonths($fechaFin, false) <= 2;
            return $contrato;
        });

        $solicitantes = Cliente::orderBy('nombre')->pluck('nombre')->all();
        $clientes = Cliente::orderBy('nombre')->get(['pk_cliente as id','nombre']);
        $pendientesCount = ContratoPendiente::pendientes()->count();

        return view('contratos.index', compact(
            'contratos',
            'solicitantes',
            'clientes',
            'pendientesCount',
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

        $existingContrato = Contrato::where('expediente_justicia_alternativa', $expediente)->first();
        if ($existingContrato) {
            return back()
                ->withInput()
                ->withErrors(['expediente' => 'Este expediente ya fue importado en el contrato #'.$existingContrato->id.'.']);
        }

        $existingPendiente = ContratoPendiente::where('origen', 'justicia_alternativa')
            ->where('external_id', $expediente)
            ->where('estado', 'pendiente_match')
            ->first();

        if ($existingPendiente) {
            if (is_array($existingPendiente->raw_payload)) {
                $mapped = $service->mapPayload($existingPendiente->raw_payload);

                if ($service->hasComplementariaMappingMismatch($existingPendiente->raw_payload, $mapped)) {
                    return back()
                        ->withInput()
                        ->withErrors(['expediente' => 'El mapeo de Justicia Alternativa asignó la Parte Solicitante como Parte Complementaria. Revisa los encabezados del Google Sheet antes de importar.']);
                }

                if (($existingPendiente->mapped_payload ?? []) !== $mapped) {
                    $existingPendiente->update(['mapped_payload' => $mapped]);
                }
            }

            return redirect()
                ->route('contratos.pendientes.show', $existingPendiente)
                ->with('success', 'Este expediente ya estaba como pendiente. Continúa con la conciliación.');
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

        if ($service->hasComplementariaMappingMismatch($row, $mapped)) {
            return back()
                ->withInput()
                ->withErrors(['expediente' => 'El mapeo de Justicia Alternativa asignó la Parte Solicitante como Parte Complementaria. Revisa los encabezados del Google Sheet antes de importar.']);
        }

        $pendiente = ContratoPendiente::updateOrCreate(
            [
                'origen' => 'justicia_alternativa',
                'external_id' => $expediente,
            ],
            [
                'expediente' => $expediente,
                'estado' => 'pendiente_match',
                'raw_payload' => $row,
                'mapped_payload' => $mapped,
            ]
        );

        return redirect()
            ->route('contratos.pendientes.show', $pendiente)
            ->with('success', 'Expediente encontrado. Revisa y concilia los datos antes de crear el contrato.');
    }

    public function storeJusticiaAlternativa(Request $request, JusticiaAlternativaImportService $service)
    {
        return $this->previewJusticiaAlternativa($request, $service);
    }
}
