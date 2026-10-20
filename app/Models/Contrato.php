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
        'comision_mensual_unidad',
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
        return $this->scopeActivosEnPeriodo($query, $mes->copy()->startOfMonth(), $mes->copy()->endOfMonth());
    }

    /** Contratos definitivos cuya vigencia se cruza con el periodo indicado. */
    public function scopeActivosEnPeriodo($query, Carbon $inicio, Carbon $fin)
    {
        return $query
            // Los contratos legacy/importados pueden carecer de inicio; hasta
            // corregirlos se conservan como potencialmente vigentes.
            ->where(function ($q) use ($fin) {
                $q->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $fin->toDateString());
            })
            ->where(function ($q) use ($inicio) {
                $q->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $inicio->toDateString());
            });
    }

    /** Contratos definitivos vigentes en una fecha concreta. */
    public function scopeVigentesEnFecha($query, Carbon $fecha)
    {
        return $this->scopeActivosEnPeriodo($query, $fecha->copy()->startOfDay(), $fecha->copy()->endOfDay());
    }

    public function getComisionMensualFractionAttribute(): float
    {
        return $this->monthlyCommissionFraction() ?? 0.0;
    }

    public static function monthlyCommissionFractionFor(mixed $value): ?float
    {
        if (! is_numeric($value)) return null;
        $v = (float) $value;
        if (! is_finite($v) || $v <= 0) return null;

        return $v > 1 ? $v / 100 : $v;
    }

    public function monthlyCommissionFraction(): ?float
    {
        $value = self::positiveNumber($this->comision_mensual);
        if ($value === null) return null;

        return match ($this->monthlyCommissionType()) {
            'percent' => $value / 100,
            'fraction' => $value <= 1 ? $value : null,
            'legacy' => self::monthlyCommissionFractionFor($value),
            default => null,
        };
    }

    public function hasValidMonthlyCommission(): bool
    {
        return self::isValidMonthlyCommission($this->comision_mensual, $this->comision_mensual_unidad);
    }

    /** percent, fraction, fixed, unknown, or legacy for historic NULL rows. */
    public function monthlyCommissionType(): string
    {
        return $this->comision_mensual_unidad === null ? 'legacy' : (string) $this->comision_mensual_unidad;
    }

    public static function isValidMonthlyCommission(mixed $value, ?string $unit = null): bool
    {
        $number = self::positiveNumber($value);
        if ($number === null) return false;

        return match ($unit) {
            null => self::monthlyCommissionFractionFor($number) !== null,
            'percent', 'fixed' => true,
            'fraction' => $number <= 1,
            default => false,
        };
    }

    /** The one calculation rule used by automatic and manual recalculation. */
    public function calculateMonthlyCommission(float $rentAmount): ?float
    {
        if (! $this->hasValidMonthlyCommission() || ! is_finite($rentAmount) || $rentAmount < 0) return null;

        return $this->monthlyCommissionType() === 'fixed'
            ? (float) $this->comision_mensual
            : $rentAmount * (float) $this->monthlyCommissionFraction();
    }

    public function monthlyCommissionDisplay(): string
    {
        if (! $this->hasValidMonthlyCommission()) return 'No definida';
        if ($this->monthlyCommissionType() === 'fixed') return '$'.number_format((float) $this->comision_mensual, 2).' fijo';

        return number_format((float) $this->monthlyCommissionFraction() * 100, 2).'%';
    }

    private static function positiveNumber(mixed $value): ?float
    {
        if (! is_numeric($value)) return null;
        $number = (float) $value;

        return is_finite($number) && $number > 0 ? $number : null;
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
