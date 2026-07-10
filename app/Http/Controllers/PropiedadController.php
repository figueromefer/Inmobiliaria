<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Task;
use App\Services\GeocodingService;
use App\Services\PerfilMovimientosService;
use App\Services\RecurringTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PropiedadController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', $request->get('search', '')));
        $estatus = (string) $request->query('estatus_informacion', '');
        $estatusPermitidos = ['pendiente_critico', 'pendiente', 'pendiente_completar', 'completo'];

        $propiedades = Propiedad::with('cliente')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('alias', 'like', "%{$q}%")
                        ->orWhere('domicilio', 'like', "%{$q}%")
                        ->orWhere('calle', 'like', "%{$q}%")
                        ->orWhere('numero_exterior', 'like', "%{$q}%")
                        ->orWhere('numero_interior', 'like', "%{$q}%")
                        ->orWhere('colonia', 'like', "%{$q}%")
                        ->orWhere('codigo_postal', 'like', "%{$q}%")
                        ->orWhere('municipio', 'like', "%{$q}%")
                        ->orWhere('estado', 'like', "%{$q}%")
                        ->orWhere('siapa', 'like', "%{$q}%")
                        ->orWhere('cfe', 'like', "%{$q}%")
                        ->orWhere('predial', 'like', "%{$q}%")
                        ->orWhereHas('cliente', function ($clienteQuery) use ($q) {
                            $clienteQuery->where('nombre', 'like', "%{$q}%");
                        });
                });
            })
            ->when(in_array($estatus, $estatusPermitidos, true), function ($query) use ($estatus) {
                $query->where('estatus_informacion', $estatus);
            })
            ->orderBy('alias')
            ->paginate(20)
            ->withQueryString();

        return view('propiedades.index', compact('propiedades', 'q', 'estatus'));
    }

    public function create(Request $request)
    {
        Gate::authorize('manage-records');

        $clientes = Cliente::orderBy('nombre')->get();
        $clientePreseleccionado = $request->get('cliente_id');

        return view('propiedades.create', compact('clientes', 'clientePreseleccionado'));
    }

    public function store(Request $request, GeocodingService $geocodingService)
    {
        Gate::authorize('manage-records');

        $request->merge([
            'clabe' => $this->normalizeClabe($request->input('clabe')),
        ]);

        $data = $request->validate([
            'fk_cliente' => 'required|exists:clientes,pk_cliente',
            'alias' => ['required','string','max:255','unique:propiedades,alias'],
            'domicilio' => 'nullable|string|max:255',
            'siapa' => 'nullable|string|max:255',
            'cfe' => 'nullable|string|max:255',
            'predial' => 'nullable|string|max:255',
            'mantenimiento_banco' => 'nullable|string|max:255',
            'mantenimiento_cuenta' => 'nullable|string|max:255',
            'referencia' => 'nullable|string|max:255',
            'clabe' => ['nullable', 'string', 'regex:/^\d{18}$/'],
            'mantenimiento_monto' => 'nullable|numeric',
            'mantenimiento_fecha_pago' => 'nullable|integer|min:1|max:31',
            'latitud' => 'nullable|string|max:255',
            'longitud' => 'nullable|string|max:255',
            'coordenadas_manual' => 'nullable|boolean',
            'estatus_informacion' => 'required|string',
            'calle' => 'nullable|string',
            'numero_exterior' => 'nullable|string',
            'numero_interior' => 'nullable|string',
            'colonia' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'municipio' => 'nullable|string',
            'estado' => 'nullable|string',
        ]);

        $data['mantenimiento_fecha_pago'] = $this->maintenanceDayToStoredDate($data['mantenimiento_fecha_pago'] ?? null);
        $coordenadasManual = (bool) ($data['coordenadas_manual'] ?? false);
        unset($data['coordenadas_manual']);

        $address = $this->composeAddressFromData($data);

        if (empty($data['domicilio']) && $address !== '') {
            $data['domicilio'] = $address;
        }

        if (! $coordenadasManual && (empty($data['latitud']) || empty($data['longitud'])) && $address !== '') {
            $coordinates = $geocodingService->geocode($address);

            if ($coordinates) {
                $data['latitud'] = $coordinates['latitud'];
                $data['longitud'] = $coordinates['longitud'];
            }
        }

        $propiedad = Propiedad::create($data);

        if ($propiedad->estatus_informacion !== 'completo') {
            Task::create([
                'title' => 'Completar información de propiedad: ' . $propiedad->alias,
                'description' => 'Revisar y completar información crítica de la propiedad.',
                'due_date' => now()->addDays(3)->toDateString(),
                'status' => 'pending',
                'priority' => $propiedad->estatus_informacion === 'pendiente_critico' ? 'high' : 'medium',
                'source_type' => Propiedad::class,
                'source_id' => $propiedad->pk_propiedad,
                'created_by' => auth()->id(),
            ]);
        }

        if ($propiedad->mantenimiento_fecha_pago) {
            app(RecurringTaskService::class)->generateMaintenancePaymentTasksForProperty($propiedad);
        }

        return redirect()->route('clientes.show', $propiedad->fk_cliente)->with('success', 'Propiedad creada correctamente.');
    }

    public function show(Propiedad $propiedad, Request $request, PerfilMovimientosService $movimientosService)
    {
        $propiedad->load(['cliente','documentos','contratos.inquilino','tickets.creator','tickets.assignee']);
        $movimientosPerfil = $movimientosService->forPropiedad($propiedad->pk_propiedad, $request);

        return view('propiedades.show', compact('propiedad', 'movimientosPerfil'));
    }

    public function edit(Propiedad $propiedad)
    {
        Gate::authorize('manage-records');
        $clientes = Cliente::orderBy('nombre')->get();
        return view('propiedades.edit', compact('propiedad', 'clientes'));
    }

    public function update(Request $request, Propiedad $propiedad, GeocodingService $geocodingService)
    {
        Gate::authorize('manage-records');

        $request->merge([
            'clabe' => $this->normalizeClabe($request->input('clabe')),
        ]);

        $data = $request->validate([
            'fk_cliente' => 'required|exists:clientes,pk_cliente',
            'alias' => ['required','string','max:255',Rule::unique('propiedades','alias')->ignore($propiedad->pk_propiedad,'pk_propiedad')],
            'domicilio' => 'nullable|string|max:255',
            'siapa' => 'nullable|string|max:255',
            'cfe' => 'nullable|string|max:255',
            'predial' => 'nullable|string|max:255',
            'mantenimiento_banco' => 'nullable|string|max:255',
            'mantenimiento_cuenta' => 'nullable|string|max:255',
            'referencia' => 'nullable|string|max:255',
            'clabe' => ['nullable', 'string', 'regex:/^\d{18}$/'],
            'mantenimiento_monto' => 'nullable|numeric',
            'mantenimiento_fecha_pago' => 'nullable|integer|min:1|max:31',
            'latitud' => 'nullable|string|max:255',
            'longitud' => 'nullable|string|max:255',
            'coordenadas_manual' => 'nullable|boolean',
            'estatus_informacion' => 'required|string',
            'calle' => 'nullable|string',
            'numero_exterior' => 'nullable|string',
            'numero_interior' => 'nullable|string',
            'colonia' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'municipio' => 'nullable|string',
            'estado' => 'nullable|string',
        ]);

        $originalAddress = $this->composeAddressFromData($propiedad->toArray());
        $data['mantenimiento_fecha_pago'] = $this->maintenanceDayToStoredDate($data['mantenimiento_fecha_pago'] ?? null);
        $coordenadasManual = (bool) ($data['coordenadas_manual'] ?? false);
        unset($data['coordenadas_manual']);

        $address = $this->composeAddressFromData($data);

        if (empty($data['domicilio']) && $address !== '') {
            $data['domicilio'] = $address;
        }

        $addressChanged = $this->normalizeAddressForCompare($address) !== $this->normalizeAddressForCompare($originalAddress);

        if (! $coordenadasManual && $address !== '' && ($addressChanged || empty($propiedad->latitud) || empty($propiedad->longitud))) {
            $coordinates = $geocodingService->geocode($address);

            if ($coordinates) {
                $data['latitud'] = $coordinates['latitud'];
                $data['longitud'] = $coordinates['longitud'];
            }
        }

        $propiedad->update($data);

        if ($propiedad->mantenimiento_fecha_pago) {
            app(RecurringTaskService::class)->generateMaintenancePaymentTasksForProperty($propiedad);
        }

        return redirect()->route('propiedades.index')->with('success', 'Propiedad actualizada correctamente.');
    }

    public function destroy(Propiedad $propiedad)
    {
        Gate::authorize('delete-anything');
        $propiedad->delete();
        return redirect()->route('propiedades.index')->with('success', 'Propiedad eliminada correctamente.');
    }

    public function mapa()
    {
        $propiedades = Propiedad::select('alias', 'latitud', 'longitud', 'domicilio')->get();
        return view('propiedades.mapa', compact('propiedades'));
    }

    private function maintenanceDayToStoredDate($day): ?string
    {
        if ($day === null || $day === '') {
            return null;
        }

        return sprintf('2000-01-%02d', (int) $day);
    }

    private function normalizeClabe($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/', '', (string) $value);

        return $value === '' ? null : $value;
    }

    private function composeAddressFromData(array $data): string
    {
        $streetLine = trim(implode(' ', array_filter([
            $data['calle'] ?? null,
            $data['numero_exterior'] ?? null,
            ! empty($data['numero_interior']) ? 'Int. '.$data['numero_interior'] : null,
        ])));

        $parts = array_filter([
            $streetLine !== '' ? $streetLine : null,
            ! empty($data['colonia']) ? 'Col. '.$data['colonia'] : null,
            ! empty($data['codigo_postal']) ? 'CP '.$data['codigo_postal'] : null,
            $data['municipio'] ?? null,
            $data['estado'] ?? null,
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return trim((string) ($data['domicilio'] ?? ''));
    }

    private function normalizeAddressForCompare(string $address): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($address))) ?? '';
    }
}
