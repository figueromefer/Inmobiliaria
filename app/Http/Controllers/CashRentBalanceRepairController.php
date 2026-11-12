<?php

namespace App\Http\Controllers;

use App\Services\CashRentBalanceRepairService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CashRentBalanceRepairController extends Controller
{
    public function preview(CashRentBalanceRepairService $repair)
    {
        Gate::authorize('delete-anything');
        $summary = $repair->preview();
        $movimientos = $repair->candidates()->with(['cliente:pk_cliente,nombre', 'propiedad:pk_propiedad,alias,domicilio'])->orderBy('fecha')->orderBy('id')->paginate(50);
        return view('admin.mantenimiento.reparar-rentas-efectivo', compact('summary', 'movimientos'));
    }

    public function apply(Request $request, CashRentBalanceRepairService $repair)
    {
        Gate::authorize('delete-anything');
        $request->validate(['confirmacion' => ['required', 'accepted']]);
        $corrected = $repair->repair($request->user(), $request->ip(), $request->userAgent());
        return redirect()->route('admin.mantenimiento.reparar-rentas-efectivo.preview')->with('ok', "Corrección aplicada: {$corrected} movimiento(s) actualizado(s).");
    }
}
