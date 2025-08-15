<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OperacionController extends Controller
{
   public function index()
    {
        return view('operaciones.index');
    }

     public function pagoRenta()
    {
        return view('operaciones.pago-renta');
    }

     public function depositoGarantia()
    {
        return view('operaciones.deposito-garantia');
    }

     public function gastosPropiedad()
    {
        return view('operaciones.gastos-propiedad');
    }
}
