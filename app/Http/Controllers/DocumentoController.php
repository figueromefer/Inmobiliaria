<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Cliente;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller {

    public function index(Request $request) {
        $query = Documento::query();

        $clienteId   = $request->query('cliente');
        $propiedadId = $request->query('propiedad');

        if ($clienteId) {
            $query->where('fk_cliente', $clienteId);
        }
        if ($propiedadId) {
            $query->where('fk_propiedad', $propiedadId);
        }

        $documentos  = $query->with(['cliente','propiedad'])->paginate(10);
        $clientes    = Cliente::all();
        $propiedades = Propiedad::all();

        return view('documentos.index', compact('documentos','clientes','propiedades','clienteId','propiedadId'));
    }

    public function view(Documento $documento) {
        // Obtener ruta completa del archivo
        $path = storage_path('app/public/' . $documento->archivo);

        // Detectar el tipo MIME para establecer el header
        $mime = mime_content_type($path);

        // Devolver el contenido como respuesta con el encabezado de tipo correcto
        return response()->file($path, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($documento->archivo) . '"',
        ]);
    }

    public function create() {
        $clientes    = Cliente::all();
        $propiedades = Propiedad::all();
        return view('documentos.create', compact('clientes','propiedades'));
    }

    public function store(Request $request) {
        $request->validate([
            'titulo'       => 'nullable|string|max:255',
            'archivo'      => 'required|file',
            'fk_cliente'   => 'nullable|exists:clientes,pk_cliente',
            'fk_propiedad' => 'nullable|exists:propiedades,pk_propiedad',
        ]);

        $path = $request->file('archivo')->store('documentos', 'public');

        Documento::create([
            'titulo'       => $request->input('titulo'),
            'archivo'      => $path,
            'fk_cliente'   => $request->input('fk_cliente'),
            'fk_propiedad' => $request->input('fk_propiedad'),
        ]);

        return redirect()->route('documentos.index')->with('success','Documento cargado correctamente.');
    }

    public function show(Documento $documento) {
        return view('documentos.show', compact('documento'));
    }

    public function download(Documento $documento) {
        return Storage::disk('public')->download($documento->archivo);
    }

    public function destroy(Documento $documento) {
        Storage::disk('public')->delete($documento->archivo);
        $documento->delete();
        return redirect()->route('documentos.index')->with('success','Documento eliminado correctamente.');
    }
}
