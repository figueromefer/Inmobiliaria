<?php
namespace App\Services;
use App\Models\ContratoPendiente;
class JusticiaAlternativaEffectivePayloadService {
 public function effective(ContratoPendiente $pending, ?array $mapped = null): array { return $this->merge($mapped ?? $pending->mapped_payload ?? [], $pending->manual_overrides ?? []); }
 public function merge(array $base, array $overrides): array { foreach($overrides as $key=>$value) $base[$key] = is_array($value) && is_array($base[$key] ?? null) ? $this->merge($base[$key],$value) : $value; return $base; }
 public function missing(array $payload): array { $fields=['fecha_inicio_contrato'=>'Fecha de inicio','fecha_terminacion_contrato'=>'Fecha de término','monto_mensual'=>'Renta mensual','domicilio_inmueble_arrendamiento'=>'Domicilio del inmueble','nombre_solicitante'=>'Parte solicitante']; return collect($fields)->filter(fn($label,$key)=>blank($payload[$key]??null))->all(); }
}
