<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Movimiento;
use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Inquilino;
use App\Models\Propiedad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $perPage = (int)$request->query('perPage', 15);
        if ($perPage < 5 || $perPage > 100) $perPage = 15;

        $query = Movimiento::query()->with(['cliente','propiedad','inquilino','approver']);

        if ($q !== '') {
            $query->where(function($w) use ($q){
                $w->whereHas('cliente', fn($c)=>$c->where('nombre','like',"%{$q}%"))
                  ->orWhereHas('propiedad', fn($p)=>$p->where('alias','like',"%{$q}%"))
                  ->orWhereHas('inquilino', fn($i)=>$i->where('nombre','like',"%{$q}%"))
                  ->orWhere('folio','like',"%{$q}%")
                  ->orWhere('concepto','like',"%{$q}%")
                  ->orWhere('asignado_a_tipo','like',"%{$q}%")
                  ->orWhere('estado_pago','like',"%{$q}%")
                  ->orWhere('forma_pago','like',"%{$q}%")
                  ->orWhere('approval_status','like',"%{$q}%");
            });
        }

        $query->orderByDesc('fecha')->orderByDesc('id');

        $movimientos = $query->paginate($perPage)->withQueryString();

        return view('movimientos.index', compact('movimientos','q','perPage'));
    }

    public function create(Request $request)
    {
        $clienteId = (int) $request->query('cliente_id', 0);

        return view('movimientos.create', array_merge(
            $this->formOptions(),
            compact('clienteId')
        ));
    }

    public function edit(Movimiento $movimiento)
    {
        Gate::authorize('manage-records');

        $movimiento->loadMissing(['cliente', 'propiedad', 'inquilino']);

        return view('movimientos.create', array_merge(
            $this->formOptions(),
            compact('movimiento')
        ));
    }

    private function formOptions(): array
    {
        $clientes = Cliente::query()
            ->select('clientes.pk_cliente as id', 'clientes.nombre')
            ->when(Schema::hasColumn('clientes', 'deleted_at'), function ($query) {
                $query->whereNull('clientes.deleted_at');
            })
            ->orderBy('clientes.nombre')
            ->get();

        $propiedades = Propiedad::query()
            ->with('cliente:pk_cliente,nombre')
            ->whereHas('cliente', function ($query) {
                if (Schema::hasColumn('clientes', 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }
            })
            ->orderBy('alias')
            ->get(['pk_propiedad as id', 'fk_cliente', 'alias', 'domicilio']);

        $inquilinos = Inquilino::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'correo', 'telefono']);

        return compact('clientes', 'propiedades', 'inquilinos');
    }

    public function store(Request $request)
    {
        $data = $this->validatedMovimientoData($request);
        $storedComprobante = null;

        if ($request->hasFile('comprobante')) {
            $storedComprobante = $this->storeComprobante($request->file('comprobante'));
            $data = array_merge($data, $storedComprobante);
        }

        [$data, $message] = $this->applyApprovalState($request, $data, 'registrado');

        try {
            $movimiento = Movimiento::withoutEvents(fn () => Movimiento::create($data));
        } catch (\Throwable $exception) {
            $this->deleteComprobante($storedComprobante, 'No se pudo revertir un comprobante tras fallar la base de datos.');

            throw $exception;
        }
        $movimiento->ensureFolio();
        $this->logMovimientoCreated($request, $movimiento->fresh());

        return redirect()->route('movimientos.index')->with('ok', $message);
    }

    public function update(Request $request, Movimiento $movimiento)
    {
        Gate::authorize('manage-records');

        $data = $this->validatedMovimientoData($request);
        $oldComprobante = $this->comprobanteData($movimiento);
        $newComprobante = null;

        if ($request->hasFile('comprobante')) {
            $newComprobante = $this->storeComprobante($request->file('comprobante'));
            $data = array_merge($data, $newComprobante);
        }

        [$data, $message] = $this->applyApprovalState($request, $data, 'actualizado');

        try {
            $movimiento->update($data);
        } catch (\Throwable $exception) {
            $this->deleteComprobante($newComprobante, 'No se pudo revertir un comprobante tras fallar la actualización.');

            throw $exception;
        }

        if ($newComprobante && $oldComprobante && ! $this->deleteComprobante($oldComprobante, 'No se pudo eliminar el comprobante anterior del movimiento.')) {
            return redirect()->route('movimientos.index')
                ->with('error', 'El movimiento se actualizó, pero no fue posible eliminar el comprobante anterior.');
        }

        return redirect()->route('movimientos.index')->with('ok', $message);
    }

    public function destroy(Movimiento $movimiento)
    {
        Gate::authorize('delete-anything');

        $folio = $movimiento->folio ?: Movimiento::formatFolio($movimiento->id);
        $comprobante = $this->comprobanteData($movimiento);

        if ($comprobante && ! $this->deleteComprobante($comprobante, 'No se pudo eliminar el comprobante del movimiento.')) {
            return redirect()->route('movimientos.index')
                ->with('error', "No se eliminó el movimiento {$folio} porque no se pudo eliminar su comprobante.");
        }

        $movimiento->delete();

        return redirect()->route('movimientos.index')
            ->with('ok', "Movimiento {$folio} eliminado correctamente.");
    }

    private function validatedMovimientoData(Request $request): array
    {
        $rules = [
            'asignado_a_tipo' => ['required','in:cliente,propiedad,inquilino'],
            'cliente_id' => ['nullable','integer','exists:clientes,pk_cliente'],
            'propiedad_id' => ['nullable','integer', Rule::exists('propiedades', 'pk_propiedad')->whereNull('deleted_at')],
            'inquilino_id' => ['nullable','integer','exists:inquilinos,id'],
            'concepto' => ['required','in:deposito,renta,gasto,gasto_cliente,iguala,pago_cliente'],
            'fecha' => ['required','date'],
            'importe' => ['required','numeric','min:0'],
            'forma_pago' => ['nullable','in:efectivo,transferencia'],
            'estado_pago' => ['nullable','in:pendiente,liquidado,cancelado'],
            'fecha_liquidacion' => ['nullable','date'],
            'afecta_saldo_cliente' => ['nullable','boolean'],
            'notas' => ['nullable','string'],
            'comprobante' => ['nullable', 'file', 'extensions:jpg,jpeg,png,pdf', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config('movimientos.comprobantes.max_kib')],
        ];

        $concepto = $request->input('concepto');
        $data = $request->validate($rules, [
            'comprobante.extensions' => 'El comprobante debe ser un archivo PDF, JPG o PNG.',
            'comprobante.mimetypes' => 'El contenido del comprobante debe corresponder a un PDF, JPG o PNG válido.',
            'comprobante.max' => 'El comprobante no puede superar los 50 MB.',
            'comprobante.file' => 'El comprobante debe ser un archivo válido.',
        ]);
        $data = $this->resolveMovimientoAssignment($data);
        $data = $this->resolvePaymentState($data);

        if (in_array($concepto, ['gasto', 'gasto_cliente', 'iguala'], true)) {
            $data['forma_pago'] = 'efectivo';
        } else {
            if (!$request->filled('forma_pago')) {
                throw ValidationException::withMessages(['forma_pago' => 'Selecciona la forma de pago.']);
            }
        }

        unset($data['comprobante']);

        return $data;
    }

    private function applyApprovalState(Request $request, array $data, string $action): array
    {
        if ($request->user()?->role === 'admin') {
            $data['approval_status'] = Movimiento::STATUS_APPROVED;
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
            $message = "Movimiento {$action} y aprobado.";
        } else {
            $data['approval_status'] = Movimiento::STATUS_PENDING;
            $data['approved_by'] = null;
            $data['approved_at'] = null;
            $message = "Movimiento {$action} y pendiente de aprobación.";
        }

        return [$data, $message];
    }

    public function approve(Request $request, Movimiento $movimiento)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        if (! $movimiento->isPendingApproval()) {
            return redirect()->route('movimientos.index')->with('ok', 'El movimiento ya no está pendiente.');
        }

        $movimiento->approveBy($request->user());

        return redirect()->route('movimientos.index')->with('ok', 'Movimiento aprobado correctamente.');
    }

    public function propiedadesPorCliente($clienteId)
    {
        $props = Propiedad::where('fk_cliente', (int)$clienteId)
            ->orderBy('alias')
            ->get(['pk_propiedad as id', 'alias']);

        return response()->json($props);
    }

    private function resolveMovimientoAssignment(array $data): array
    {
        return match ($data['asignado_a_tipo']) {
            'cliente' => $this->resolveClienteAssignment($data),
            'propiedad' => $this->resolvePropiedadAssignment($data),
            'inquilino' => $this->resolveInquilinoAssignment($data),
        };
    }

    private function resolveClienteAssignment(array $data): array
    {
        if (empty($data['cliente_id'])) {
            throw ValidationException::withMessages(['cliente_id' => 'Selecciona un cliente.']);
        }

        $cliente = $this->findActiveCliente((int) $data['cliente_id']);

        if (! $cliente) {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente seleccionado no existe o está archivado.']);
        }

        $data['cliente_id'] = $cliente->pk_cliente;
        $data['propiedad_id'] = null;
        $data['inquilino_id'] = null;

        return $data;
    }

    private function resolvePropiedadAssignment(array $data): array
    {
        if (empty($data['propiedad_id'])) {
            throw ValidationException::withMessages(['propiedad_id' => 'Selecciona una propiedad.']);
        }

        $propiedad = Propiedad::with('cliente')->find($data['propiedad_id']);

        if (! $propiedad) {
            throw ValidationException::withMessages(['propiedad_id' => 'La propiedad seleccionada no existe.']);
        }

        if (! $this->clienteIsActive($propiedad->cliente)) {
            throw ValidationException::withMessages(['propiedad_id' => 'La propiedad no tiene un cliente dueño válido o el cliente está archivado.']);
        }

        $data['cliente_id'] = $propiedad->fk_cliente;
        $data['propiedad_id'] = $propiedad->pk_propiedad;
        $data['inquilino_id'] = null;

        return $data;
    }

    private function resolveInquilinoAssignment(array $data): array
    {
        if (empty($data['inquilino_id'])) {
            throw ValidationException::withMessages(['inquilino_id' => 'Selecciona un inquilino.']);
        }

        $inquilino = Inquilino::find($data['inquilino_id']);

        if (! $inquilino) {
            throw ValidationException::withMessages(['inquilino_id' => 'El inquilino seleccionado no existe.']);
        }

        $contrato = $this->resolveContratoForInquilino($inquilino);

        if (! $contrato || ! $contrato->propiedad || ! $this->clienteIsActive($contrato->cliente ?: $contrato->propiedad->cliente)) {
            throw ValidationException::withMessages(['inquilino_id' => 'No se pudo resolver un contrato, propiedad y cliente válidos para el inquilino seleccionado.']);
        }

        $data['cliente_id'] = $contrato->fk_cliente ?: $contrato->propiedad->fk_cliente;
        $data['propiedad_id'] = $contrato->fk_propiedad;
        $data['inquilino_id'] = $inquilino->id;

        return $data;
    }

    private function resolveContratoForInquilino(Inquilino $inquilino): ?Contrato
    {
        $today = now()->toDateString();
        $baseQuery = Contrato::query()
            ->with(['cliente', 'propiedad.cliente'])
            ->where('inquilino_id', $inquilino->id)
            ->when(Schema::hasColumn('contratos', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            });

        $active = (clone $baseQuery)
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $today);
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        return $active ?: $baseQuery
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();
    }

    private function findActiveCliente(int $clienteId): ?Cliente
    {
        return Cliente::query()
            ->when(Schema::hasColumn('clientes', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->where('pk_cliente', $clienteId)
            ->first();
    }

    private function clienteIsActive(?Cliente $cliente): bool
    {
        if (! $cliente) {
            return false;
        }

        return ! Schema::hasColumn('clientes', 'deleted_at') || $cliente->deleted_at === null;
    }

    private function resolvePaymentState(array $data): array
    {
        $data['estado_pago'] = $data['estado_pago'] ?? Movimiento::PAYMENT_LIQUIDATED;
        $data['afecta_saldo_cliente'] = (bool) ($data['afecta_saldo_cliente'] ?? true);

        if ($data['estado_pago'] === Movimiento::PAYMENT_PENDING || $data['estado_pago'] === Movimiento::PAYMENT_CANCELED) {
            $data['fecha_liquidacion'] = null;

            return $data;
        }

        $data['fecha_liquidacion'] = $data['fecha_liquidacion'] ?? $data['fecha'];

        return $data;
    }

    private function logMovimientoCreated(Request $request, Movimiento $movimiento): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'created',
            'model_type' => Movimiento::class,
            'model_id' => $movimiento->getKey(),
            'module' => class_basename($movimiento),
            'old_values' => null,
            'new_values' => ActivityLog::sanitizeValues($movimiento->toArray()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function comprobante(Movimiento $movimiento)
    {
        $comprobante = $this->comprobanteData($movimiento);

        abort_unless($comprobante && $this->isAllowedComprobanteDisk($comprobante['comprobante_disk']), 404);

        $disk = Storage::disk($comprobante['comprobante_disk']);

        if (! $disk->exists($comprobante['comprobante'])) {
            abort(404);
        }

        $filename = $this->downloadFilename($comprobante['comprobante_nombre_original'], $comprobante['comprobante']);
        $mime = $comprobante['comprobante_mime'] ?: $disk->mimeType($comprobante['comprobante']);

        if ($comprobante['comprobante_disk'] === 'r2') {
            return redirect()->away($disk->temporaryUrl(
                $comprobante['comprobante'],
                now()->addMinutes(config('movimientos.comprobantes.temporary_url_minutes')),
                array_filter([
                    'ResponseContentDisposition' => 'inline; filename="'.$filename.'"',
                    'ResponseContentType' => $mime,
                ])
            ));
        }

        return response()->file($disk->path($comprobante['comprobante']), array_filter([
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]));
    }

    private function storeComprobante(UploadedFile $file): array
    {
        $diskName = config('movimientos.comprobantes.disk');

        if (! $this->isAllowedComprobanteDisk($diskName)) {
            Log::error('El disco configurado para comprobantes de movimientos no está permitido.', ['disk' => $diskName]);
            throw ValidationException::withMessages(['comprobante' => 'No fue posible almacenar el comprobante. Inténtalo de nuevo.']);
        }

        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };

        if (! $extension) {
            throw ValidationException::withMessages(['comprobante' => 'El contenido del comprobante debe corresponder a un PDF, JPG o PNG válido.']);
        }

        $path = 'movimientos/comprobantes/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;

        try {
            $storedPath = Storage::disk($diskName)->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private']);

            if (! $storedPath) {
                throw new \RuntimeException('El disco no confirmó el almacenamiento del comprobante.');
            }
        } catch (\Throwable $exception) {
            Log::error('No se pudo almacenar el comprobante del movimiento.', [
                'disk' => $diskName,
                'exception_type' => $exception::class,
            ]);

            throw ValidationException::withMessages(['comprobante' => 'No fue posible almacenar el comprobante. Inténtalo de nuevo.']);
        }

        return [
            'comprobante' => $path,
            'comprobante_disk' => $diskName,
            'comprobante_nombre_original' => $file->getClientOriginalName(),
            'comprobante_mime' => $mime,
            'comprobante_size' => $file->getSize(),
        ];
    }

    private function comprobanteData(Movimiento $movimiento): ?array
    {
        if (! $movimiento->comprobante) {
            return null;
        }

        return [
            'comprobante' => $movimiento->comprobante,
            'comprobante_disk' => $movimiento->comprobante_disk ?: 'public',
            'comprobante_nombre_original' => $movimiento->comprobante_nombre_original,
            'comprobante_mime' => $movimiento->comprobante_mime,
            'comprobante_size' => $movimiento->comprobante_size,
        ];
    }

    private function deleteComprobante(?array $comprobante, string $message): bool
    {
        if (! $comprobante) {
            return true;
        }

        if (! $this->isAllowedComprobanteDisk($comprobante['comprobante_disk'])) {
            Log::error($message, ['disk' => $comprobante['comprobante_disk'], 'path' => $comprobante['comprobante']]);

            return false;
        }

        try {
            if (! Storage::disk($comprobante['comprobante_disk'])->delete($comprobante['comprobante'])) {
                Log::error($message, ['disk' => $comprobante['comprobante_disk'], 'path' => $comprobante['comprobante']]);

                return false;
            }
        } catch (\Throwable $exception) {
            Log::error($message, [
                'disk' => $comprobante['comprobante_disk'],
                'path' => $comprobante['comprobante'],
                'exception_type' => $exception::class,
            ]);

            return false;
        }

        return true;
    }

    private function isAllowedComprobanteDisk(string $disk): bool
    {
        return in_array($disk, config('movimientos.comprobantes.allowed_disks'), true);
    }

    private function downloadFilename(?string $originalName, string $path): string
    {
        $filename = $originalName ?: basename($path);

        return str_replace(["\r", "\n", '"'], '', $filename);
    }

    public function recibo(Movimiento $movimiento)
    {
        $movimiento->loadMissing(['cliente', 'propiedad', 'inquilino']);

        if (!in_array($movimiento->concepto, ['deposito', 'renta'], true)) {
            return redirect()->back()->with('error', 'El recibo sólo está disponible para depósitos o rentas.');
        }

        if ($movimiento->approval_status !== Movimiento::STATUS_APPROVED) {
            return redirect()->back()->with('error', 'El recibo sólo está disponible para movimientos aprobados.');
        }

        $pdf = Pdf::loadView('movimientos.recibo_pdf', [
            'movimiento' => $movimiento,
        ]);

        $filename = 'recibo-' . ($movimiento->folio ?: $movimiento->id) . '.pdf';

        return $pdf->stream($filename, ['Attachment' => false]);
    }
}
