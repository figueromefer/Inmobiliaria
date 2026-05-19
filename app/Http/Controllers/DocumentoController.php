<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Cliente;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller {

public static $tipos=[
'comprobante_domicilio'=>'Comprobante domicilio',
'agua'=>'Agua',
'cfe'=>'CFE',
'predial'=>'Predial',
'recibo'=>'Recibo escaneado',
'otro'=>'Otro'
];

public function index(Request $request){ return parent::index($request);} 

public function create(Request $request) {
$clientes=Cliente::all();
$propiedades=Propiedad::all();
$clienteId=$request->query('cliente');
$propiedadId=$request->query('propiedad');
$tipos=self::$tipos;
return view('documentos.create',compact('clientes','propiedades','clienteId','propiedadId','tipos'));
}

public function store(Request $request){
$request->validate([
'titulo'=>'nullable|string|max:255',
'tipo'=>'required|string',
'archivo'=>'required|file|max:10240'
]);
$path=$request->file('archivo')->store('documentos','public');
$documento=Documento::create([
'titulo'=>$request->titulo,
'tipo'=>$request->tipo,
'archivo'=>$path,
'fk_cliente'=>$request->fk_cliente,
'fk_propiedad'=>$request->fk_propiedad,
]);
if($documento->fk_cliente)return redirect()->route('clientes.show',$documento->fk_cliente)->with('success','Documento agregado');
if($documento->fk_propiedad)return redirect()->route('propiedades.show',$documento->fk_propiedad)->with('success','Documento agregado');
return redirect()->route('documentos.index');
}
}
