<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentoController extends Controller
{
    public static array $tipos = [
        'identificacion' => 'Identificación',
        'comprobante_domicilio' => 'Comprobante domicilio',
        'titulo_propiedad' => 'Título / escritura',
        'siapa' => 'SIAPA',
        'agua' => 'Agua',
        'cfe' => 'CFE',
        'gas' => 'Gas',
        'predial' => 'Predial',
        'recibo' => 'Recibo escaneado',
        'reporte_investigacion' => 'Reporte de investigación',
        'reporte_entrega_cliente' => 'Reporte / entrega al cliente',
        'contrato_original_firmado' => 'Contrato original firmado',
        'otro' => 'Otro',
    ];

    public function index(Request $request)
    {
        $query = Documento::query();
        $q = trim((string) $request->query('q', ''));

        $clienteId = $request->query('cliente');
        $propiedadId = $request->query('propiedad');
        $inquilinoId = $request->query('inquilino');
        $contratoId = $request->query('contrato');

        if ($clienteId) {
            $query->where('fk_cliente', $clienteId);
        }

        if ($propiedadId) {
            $query->where('fk_propiedad', $propiedadId);
        }

        if ($inquilinoId) {
            $query->where('fk_inquilino', $inquilinoId);
        }

        if ($contratoId) {
            $query->where('contrato_id', $contratoId);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%{$q}%")
                    ->orWhere('tipo', 'like', "%{$q}%")
                    ->orWhere('archivo', 'like', "%{$q}%")
                    ->orWhereHas('cliente', function ($clienteQuery) use ($q) {
                        $clienteQuery->where('nombre', 'like', "%{$q}%");
                    })
                    ->orWhereHas('propiedad', function ($propiedadQuery) use ($q) {
                        $propiedadQuery->where('alias', 'like', "%{$q}%")
                            ->orWhere('domicilio', 'like', "%{$q}%");
                    })
                    ->orWhereHas('inquilino', function ($inquilinoQuery) use ($q) {
                        $inquilinoQuery->where('nombre', 'like', "%{$q}%");
                    });
            });
        }

        $documentos = $query->with(['cliente', 'propiedad', 'inquilino'])->paginate(10)->withQueryString();
        $clientes = Cliente::orderBy('nombre')->get();
        $propiedades = Propiedad::orderBy('alias')->get();
        $inquilinos = Inquilino::orderBy('nombre')->get();
        $tipos = self::$tipos;

        return view('documentos.index', compact('documentos', 'clientes', 'propiedades', 'inquilinos', 'clienteId', 'propiedadId', 'inquilinoId', 'tipos', 'q'));
    }

    public function create(Request $request)
    {
        Gate::authorize('manage-records');

        $clientes = Cliente::orderBy('nombre')->get();
        $propiedades = Propiedad::orderBy('alias')->get();
        $inquilinos = Inquilino::orderBy('nombre')->get();
        $clienteId = $request->query('cliente');
        $propiedadId = $request->query('propiedad');
        $inquilinoId = $request->query('inquilino');
        $contratoId = $request->query('contrato');
        $tipos = self::$tipos;

        $returnContext = $this->validatedReturnContext($request);

        $contratos = Contrato::orderByDesc('id')->get(['id','expediente_justicia_alternativa']);
        return view('documentos.create', compact('clientes', 'propiedades', 'inquilinos', 'contratos', 'clienteId', 'propiedadId', 'inquilinoId', 'contratoId', 'tipos', 'returnContext'));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-records');

        $request->validate([
            'titulo' => 'nullable|string|max:255',
            'tipo' => 'required|string|in:' . implode(',', array_keys(self::$tipos)),
            'archivo' => 'required|file|max:10240',
            'fk_cliente' => 'nullable|exists:clientes,pk_cliente',
            'fk_propiedad' => ['nullable', Rule::exists('propiedades', 'pk_propiedad')->whereNull('deleted_at')],
            'fk_inquilino' => 'nullable|exists:inquilinos,id',
            'contrato_id' => 'nullable|exists:contratos,id',
        ]);

        $path = $request->file('archivo')->store('documentos', 'public');

        $documento = Documento::create([
            'titulo' => $request->input('titulo'),
            'tipo' => $request->input('tipo'),
            'archivo' => $path,
            'fk_cliente' => $request->input('fk_cliente'),
            'fk_propiedad' => $request->input('fk_propiedad'),
            'fk_inquilino' => $request->input('fk_inquilino'),
            'contrato_id' => $request->input('contrato_id'),
        ]);

        if ($returnUrl = $this->returnContextUrl($request)) {
            return redirect($returnUrl)->with('success', 'Documento agregado correctamente.');
        }

        if ($documento->fk_cliente) {
            return redirect()->route('clientes.show', $documento->fk_cliente)->with('success', 'Documento agregado correctamente.');
        }

        if ($documento->fk_propiedad) {
            return redirect()->route('propiedades.show', $documento->fk_propiedad)->with('success', 'Documento agregado correctamente.');
        }

        if ($documento->fk_inquilino) {
            return redirect()->route('inquilinos.show', $documento->fk_inquilino)->with('success', 'Documento agregado correctamente.');
        }

        return redirect()->route('documentos.index')->with('success', 'Documento agregado correctamente.');
    }

    public function show(Documento $documento)
    {
        return view('documentos.show', compact('documento'));
    }

    public function view(Documento $documento)
    {
        $path = storage_path('app/public/' . $documento->archivo);

        abort_unless(file_exists($path), 404, 'Archivo no encontrado.');

        $mime = mime_content_type($path);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($documento->archivo) . '"',
        ]);
    }

    public function download(Documento $documento)
    {
        return Storage::disk('public')->download($documento->archivo);
    }

    public function destroy(Request $request, Documento $documento)
    {
        Gate::authorize('delete-anything');

        $clienteId = $documento->fk_cliente;
        $propiedadId = $documento->fk_propiedad;
        $inquilinoId = $documento->fk_inquilino;

        Storage::disk('public')->delete($documento->archivo);
        $documento->delete();

        if ($returnUrl = $this->returnContextUrl($request)) {
            return redirect($returnUrl)->with('success', 'Documento eliminado correctamente.');
        }

        if ($clienteId) {
            return redirect()->route('clientes.show', $clienteId)->with('success', 'Documento eliminado correctamente.');
        }

        if ($propiedadId) {
            return redirect()->route('propiedades.show', $propiedadId)->with('success', 'Documento eliminado correctamente.');
        }

        if ($inquilinoId) {
            return redirect()->route('inquilinos.show', $inquilinoId)->with('success', 'Documento eliminado correctamente.');
        }

        return redirect()->route('documentos.index')->with('success', 'Documento eliminado correctamente.');
    }

    private function validatedReturnContext(Request $request): ?array
    {
        $context = $request->input('context');
        $id = filter_var($request->input('context_id'), FILTER_VALIDATE_INT);

        return in_array($context, ['cliente', 'propiedad', 'inquilino', 'contrato'], true) && $id && $id > 0
            ? ['type' => $context, 'id' => $id]
            : null;
    }

    private function returnContextUrl(Request $request): ?string
    {
        $context = $this->validatedReturnContext($request);
        if (! $context) return null;

        return match ($context['type']) {
            'cliente' => Cliente::whereKey($context['id'])->exists() ? route('clientes.show', $context['id']) : null,
            'propiedad' => Propiedad::withTrashed()->whereKey($context['id'])->exists() ? route('propiedades.show', $context['id']) : null,
            'inquilino' => Inquilino::whereKey($context['id'])->exists() ? route('inquilinos.show', $context['id']) : null,
            'contrato' => Contrato::whereKey($context['id'])->exists() ? route('contratos.show', $context['id']) : null,
        };
    }
}
