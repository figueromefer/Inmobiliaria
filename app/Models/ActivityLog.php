<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'module',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getHumanMessageAttribute(): string
    {
        $action = match ($this->action) {
            'created' => 'Se creó',
            'updated' => 'Se actualizó',
            'deleted' => 'Se eliminó',
            default => 'Se registró',
        };

        if ($this->isMovementLog()) {
            return $this->movementHumanMessage($action);
        }

        return trim($action . ' ' . $this->moduleLabel() . ' ' . $this->recordLabel);
    }

    public function getRecordLabelAttribute(): string
    {
        $values = $this->mergedValues();
        $module = strtolower((string) $this->module);

        $candidateFields = match ($module) {
            'cliente' => ['nombre'],
            'propiedad' => ['alias', 'domicilio'],
            'inquilino' => ['nombre'],
            'documento' => ['titulo', 'nombre', 'nombre_archivo', 'archivo'],
            'contrato' => ['expediente_justicia_alternativa', 'expediente', 'domicilio_inmueble'],
            'maintenanceticket', 'ticket' => ['title', 'titulo', 'asunto'],
            default => ['nombre', 'name', 'titulo', 'title', 'alias', 'descripcion', 'description'],
        };

        foreach ($candidateFields as $field) {
            if (! empty($values[$field])) {
                return (string) $values[$field];
            }
        }

        return '#' . ($this->model_id ?: 'sin id');
    }

    public function getTechnicalDetailAttribute(): array
    {
        return [
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
        ];
    }

    public function getTechnicalDetailJsonAttribute(): string
    {
        return json_encode($this->technical_detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function moduleLabel(): string
    {
        return match (strtolower((string) $this->module)) {
            'cliente' => 'el cliente',
            'propiedad' => 'la propiedad',
            'inquilino' => 'el inquilino',
            'documento' => 'el documento',
            'movimiento' => 'el movimiento',
            'contrato' => 'el contrato',
            'maintenanceticket', 'ticket' => 'el ticket',
            default => strtolower((string) ($this->module ?: 'registro')),
        };
    }

    private function mergedValues(): array
    {
        return array_merge(
            is_array($this->old_values) ? $this->old_values : [],
            is_array($this->new_values) ? $this->new_values : [],
        );
    }

    private function isMovementLog(): bool
    {
        return strtolower((string) $this->module) === 'movimiento';
    }

    private function movementHumanMessage(string $fallbackAction): string
    {
        $values = $this->mergedValues();
        $concept = $values['concepto'] ?? $values['tipo'] ?? 'movimiento';
        $amount = $values['importe'] ?? $values['monto'] ?? null;

        if (in_array($this->action, ['created', 'updated'], true) && is_numeric($amount)) {
            $action = $this->action === 'created' ? 'Se registró' : $fallbackAction;

            return $action . ' un movimiento de ' . $concept . ' por $' . number_format((float) $amount, 2);
        }

        return $fallbackAction . ' el movimiento ' . $this->recordLabel;
    }
}
