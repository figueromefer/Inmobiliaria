<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $clienteId = $request->query('cliente');
        $propiedadId = $request->query('propiedad');

        if ($clienteId) {
            $query->where('fk_cliente', $clienteId);
        }

        if ($propiedadId) {
            $query->where('fk_propiedad', $propiedadId);
        }

        $documentos = $query->with(['cliente', 'propiedad'])->paginate(10)->withQueryString();
        $clientes = Cliente::orderBy('nombre')->get();
        $propiedades = Propiedad::orderBy('alias')->get();
        $tipos = self::$tipos;

        return view('documentos.index', compact('documentos', 'clientes', 'propiedades', 'clienteId', 'propiedadId', 'tipos'));
    }

    public function create(Request $request)
    {
        $clientes = Cliente::orderBy('nombre')->get();
        $propiedades = Propiedad::orderBy('alias')->get();
        $clienteId = $request->query('cliente');
        $propiedadId = $request->query('propiedad');
        $tipos = self::$tipos;

        return view('documentos.create', compact('clientes', 'propiedades', 'clienteId', 'propiedadId', 'tipos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'nullable|string|max:255',
            'tipo' => 'required|string|in:' . implode(',', array_keys(self::$tipos)),
            'archivo' => 'required|file|max:10240',
            'fk_cliente' => 'nullable|exists:clientes,pk_cliente',
            'fk_propiedad' => 'nullable|exists:propiedades,pk_propiedad',
        ]);

        $path = $request->file('archivo')->store('documentos', 'public');

        $documento = Documento::create([
            'titulo' => $request->input('titulo'),
            'tipo' => $request->input('tipo'),
            'archivo' => $path,
            'fk_cliente' => $request->input('fk_cliente'),
            'fk_propiedad' => $request->input('fk_propiedad'),
        ]);

        if ($documento->fk_cliente) {
            return redirect()->route('clientes.show', $documento->fk_cliente)->with('success', 'Documento agregado correctamente.');
        }

        if ($documento->fk_propiedad) {
            return redirect()->route('propiedades.show', $documento->fk_propiedad)->with('success', 'Documento agregado correctamente.');
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

        Storage::disk('public')->delete($documento->archivo);
        $documento->delete();

        if ($clienteId) {
            return redirect()->route('clientes.show', $clienteId)->with('success', 'Documento eliminado correctamente.');
        }

        if ($propiedadId) {
            return redirect()->route('propiedades.show', $propiedadId)->with('success', 'Documento eliminado correctamente.');
        }

        return redirect()->route('documentos.index')->with('success', 'Documento eliminado correctamente.');
    }
}
