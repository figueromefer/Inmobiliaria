<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Inquilino;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentoController extends Controller
{
    public static array $tipos = [
        'comprobante_domicilio' => 'Comprobante domicilio',
        'agua' => 'Agua',
        'cfe' => 'CFE',
        'predial' => 'Predial',
        'recibo' => 'Recibo escaneado',
        'otro' => 'Otro',
    ];

    public function index(Request $request)
    {
        $query = Documento::query();
        $q = trim((string) $request->query('q', ''));

        $clienteId = $request->query('cliente');
        $propiedadId = $request->query('propiedad');
        $inquilinoId = $request->query('inquilino');

        if ($clienteId) {
            $query->where('fk_cliente', $clienteId);
        }

        if ($propiedadId) {
            $query->where('fk_propiedad', $propiedadId);
        }

        if ($inquilinoId) {
            $query->where('fk_inquilino', $inquilinoId);
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
        $clientes = Cliente::orderBy('nombre')->get();
        $propiedades = Propiedad::orderBy('alias')->get();
        $inquilinos = Inquilino::orderBy('nombre')->get();
        $clienteId = $request->query('cliente');
        $propiedadId = $request->query('propiedad');
        $inquilinoId = $request->query('inquilino');
        $tipos = self::$tipos;

        return view('documentos.create', compact('clientes', 'propiedades', 'inquilinos', 'clienteId', 'propiedadId', 'inquilinoId', 'tipos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'nullable|string|max:255',
            'tipo' => 'required|string|in:' . implode(',', array_keys(self::$tipos)),
            'archivo' => 'required|file|max:10240',
            'fk_cliente' => 'nullable|exists:clientes,pk_cliente',
            'fk_propiedad' => ['nullable', Rule::exists('propiedades', 'pk_propiedad')->whereNull('deleted_at')],
            'fk_inquilino' => 'nullable|exists:inquilinos,id',
        ]);

        $path = $request->file('archivo')->store('documentos', 'public');

        $documento = Documento::create([
            'titulo' => $request->input('titulo'),
            'tipo' => $request->input('tipo'),
            'archivo' => $path,
            'fk_cliente' => $request->input('fk_cliente'),
            'fk_propiedad' => $request->input('fk_propiedad'),
            'fk_inquilino' => $request->input('fk_inquilino'),
        ]);

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

    public function destroy(Documento $documento)
    {
        $clienteId = $documento->fk_cliente;
        $propiedadId = $documento->fk_propiedad;
        $inquilinoId = $documento->fk_inquilino;

        Storage::disk('public')->delete($documento->archivo);
        $documento->delete();

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
}
