<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    protected $table = 'contratos';

    protected $fillable = [
        'fk_cliente',
        'fk_propiedad',
        'tipo_solicitante',
        'tipo_complementaria',
        'tipo_tercero',
        'solicitante',
        'fecha',
        'inquilino_id',
        'domicilio_inmueble',
        'fecha_inicio',
        'fecha_fin',
        'comision_renta',
        'comision_mensual',
        'dias_pago',
        'monto_total',
        'monto_mensual',
        'monto_deposito',
        'edit_url',
        'urldoc',
        'origen',
        'expediente_justicia_alternativa',
        'imported_at',
        'raw_justicia_alternativa',
        'contract_draft_version_id',
        'contract_document_version_id',
        'previous_contract_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'imported_at' => 'datetime',
        'raw_justicia_alternativa' => 'array',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'fk_cliente', 'pk_cliente');
    }

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'fk_propiedad', 'pk_propiedad')->withTrashed();
    }

    public function inquilino()
    {
        return $this->belongsTo(Inquilino::class, 'inquilino_id');
    }

    public function pendientes()
    {
        return $this->hasMany(ContratoPendiente::class, 'contrato_id');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'contrato_id');
    }

    public function draftVersion()
    {
        return $this->belongsTo(ContractDraftVersion::class, 'contract_draft_version_id');
    }

    public function documentVersion()
    {
        return $this->belongsTo(ContractDocumentVersion::class, 'contract_document_version_id');
    }

    /** Drafts used for versioned corrections; they never represent renewals. */
    public function revisionDrafts()
    {
        return $this->hasMany(ContractDraft::class, 'editing_contract_id');
    }

    public function renewalDrafts()
    {
        return $this->hasMany(ContractDraft::class, 'renewal_of_contract_id');
    }

    public function getDriveFolderUrlAttribute(): ?string
    {
        $id = $this->documentVersion?->drive_folder_id;

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]+$/', $id)
            ? 'https://drive.google.com/drive/folders/'.rawurlencode($id)
            : null;
    }

    public function getDocumentUrlAttribute(): ?string
    {
        return $this->documentVersion?->url ?: $this->urldoc;
    }

    public function previousContract()
    {
        return $this->belongsTo(self::class, 'previous_contract_id');
    }

    public function renewals()
    {
        return $this->hasMany(self::class, 'previous_contract_id');
    }

    public function scopeActivosEnMes($query, Carbon $mes)
    {
        $inicio = $mes->copy()->startOfMonth();
        $fin = $mes->copy()->endOfMonth();

        return $query
            ->where(function ($q) use ($fin) {
                $q->whereNull('fecha_inicio')
                    ->orWhere('fecha_inicio', '<=', $fin);
            })
            ->where(function ($q) use ($inicio) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $inicio);
            });
    }

    public function getComisionMensualFractionAttribute(): float
    {
        $v = (float) ($this->comision_mensual ?? 0);

        return $v > 1 ? $v / 100 : $v;
    }

    /** Estado de vigencia para UI; no altera la vigencia contractual. */
    public function getVigenciaEstadoAttribute(): array
    {
        if (! $this->fecha_inicio || ! $this->fecha_fin) {
            return ['key' => 'sin_vigencia', 'label' => 'Sin vigencia'];
        }

        $today = Carbon::today();
        $inicio = Carbon::parse($this->fecha_inicio)->startOfDay();
        $fin = Carbon::parse($this->fecha_fin)->endOfDay();

        if ($inicio->gt($today)) {
            return ['key' => 'proximo', 'label' => 'Próximo'];
        }

        if ($fin->lt($today)) {
            return ['key' => 'vencido', 'label' => 'Vencido'];
        }

        if ($fin->lte($today->copy()->addMonthsNoOverflow(2)->endOfDay())) {
            return ['key' => 'por_vencer', 'label' => 'Por vencer'];
        }

        return ['key' => 'vigente', 'label' => 'Vigente'];
    }
}
