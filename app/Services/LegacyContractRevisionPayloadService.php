<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\ContratoPendiente;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds a revision from facts actually persisted by the legacy Forms intake.
 * It never reconstructs fields that only existed inside Apps Script before its
 * HTTP payload was sent.
 */
class LegacyContractRevisionPayloadService
{
    /** @return array<string, mixed> */
    public function build(Contrato $contrato, ContractDraftPayload $payloads): array
    {
        $contrato->loadMissing(['cliente', 'propiedad', 'inquilino']);
        $pending = $this->linkedPending($contrato);
        $sources = $pending ? [$pending->raw_payload ?? [], $pending->mapped_payload ?? []] : [];
        $payload = $payloads->empty();

        $this->set($payload, 'metadata.contract_date', $contrato->fecha?->toDateString());
        $this->set($payload, 'leased_property.alias', $contrato->propiedad?->alias);
        $this->set($payload, 'leased_property.address', $contrato->domicilio_inmueble ?: $contrato->propiedad?->domicilio);
        $this->set($payload, 'term.start_date', $contrato->fecha_inicio?->toDateString());
        $this->set($payload, 'term.end_date', $contrato->fecha_fin?->toDateString());
        $this->set($payload, 'term.rent_due_rule.raw_text', $contrato->dias_pago ? (string) $contrato->dias_pago : null);
        foreach (['total_rent' => 'monto_total', 'monthly_rent' => 'monto_mensual', 'security_deposit' => 'monto_deposito', 'rental_commission' => 'comision_renta', 'monthly_commission_value' => 'comision_mensual'] as $target => $source) {
            $payload['amounts'][$target] = $contrato->{$source};
        }
        $payload['amounts']['monthly_commission_unit'] = $contrato->comision_mensual_unidad ?? ($contrato->hasValidMonthlyCommission() ? ((float) $contrato->comision_mensual > 1 ? 'percent' : 'fraction') : 'unknown');

        $lessorType = $this->personType($contrato->tipo_solicitante, $sources, 'tipo_solicitante');
        $lesseeType = $this->personType($contrato->tipo_complementaria, $sources, 'tipo_complementaria');
        $this->seedLessorFromMasters($payload, $contrato, $lessorType);
        $this->seedLesseeFromMasters($payload, $contrato, $lesseeType);

        // Raw precedes mapped. Empty values never overwrite a valid preceding
        // value, and mapped wins only when it has a value.
        foreach ($sources as $source) {
            $this->overlaySharedFacts($payload, $source);
            $this->overlayLessor($payload, $source, $lessorType);
            $this->overlayLessee($payload, $source, $lesseeType);
        }

        return $payload;
    }

    private function linkedPending(Contrato $contrato): ?ContratoPendiente
    {
        $pendings = ContratoPendiente::query()->where('contrato_id', $contrato->id)->limit(2)->get();
        if ($pendings->count() !== 1) {
            if ($pendings->count() > 1) {
                Log::warning('Se omitió la reconstrucción legacy: hay varios pendientes vinculados al contrato.', [
                    'contrato_id' => $contrato->id,
                    'contrato_pendiente_ids' => $pendings->pluck('id')->all(),
                ]);
            }

            return null;
        }

        return $pendings->first();
    }

    /** @param array<int, array<string, mixed>> $sources */
    private function personType(?string $legacyType, array $sources, string $key): ?string
    {
        $type = $this->normalizePersonType($legacyType);
        foreach ($sources as $source) {
            $candidate = $this->normalizePersonType($source[$key] ?? null);
            if ($candidate !== null) $type = $candidate;
        }

        return $type;
    }

    private function normalizePersonType(mixed $value): ?string
    {
        if (! is_string($value)) return null;
        $value = mb_strtolower(trim($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $value);

        return match ($value) {
            'fisica', 'persona fisica' => 'fisica',
            'moral', 'persona moral' => 'moral',
            default => null,
        };
    }

    /** @param array<string,mixed> $payload */
    private function seedLessorFromMasters(array &$payload, Contrato $contrato, ?string $type): void
    {
        if ($type !== null) $payload['lessor']['person']['person_type'] = $type;
        $namePath = $type === 'moral' ? 'lessor.person.legal_name' : ($type === 'fisica' ? 'lessor.person.full_name' : null);
        if ($namePath) $this->set($payload, $namePath, $contrato->cliente?->nombre);
        foreach (['rfc' => $contrato->cliente?->rfc, 'address' => $contrato->cliente?->domicilio, 'phone' => $contrato->cliente?->celular ?: $contrato->cliente?->fijo, 'email' => $contrato->cliente?->correo] as $field => $value) {
            $this->set($payload, "lessor.person.{$field}", $value);
        }
    }

    /** @param array<string,mixed> $payload */
    private function seedLesseeFromMasters(array &$payload, Contrato $contrato, ?string $type): void
    {
        if ($type !== null) $payload['lessee']['person']['person_type'] = $type;
        $namePath = $type === 'moral' ? 'lessee.person.legal_name' : ($type === 'fisica' ? 'lessee.person.full_name' : null);
        if ($namePath) $this->set($payload, $namePath, $contrato->inquilino?->nombre);
        foreach (['nationality' => $contrato->inquilino?->nacionalidad, 'address' => $contrato->inquilino?->domicilio, 'phone' => $contrato->inquilino?->telefono, 'email' => $contrato->inquilino?->correo] as $field => $value) {
            $this->set($payload, "lessee.person.{$field}", $value);
        }
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $source */
    private function overlaySharedFacts(array &$payload, array $source): void
    {
        foreach ([
            'leased_property.alias' => ['propiedad_alias'],
            'leased_property.address' => ['propiedad_domicilio', 'domicilio_inmueble_arrendamiento'],
            'term.start_date' => ['fecha_inicio_contrato'],
            'term.end_date' => ['fecha_terminacion_contrato'],
            'term.rent_due_rule.raw_text' => ['dias_pago'],
        ] as $target => $keys) $this->setFrom($payload, $target, $source, $keys);
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $source */
    private function overlayLessor(array &$payload, array $source, ?string $type): void
    {
        if ($type === 'moral') $this->setFrom($payload, 'lessor.person.legal_name', $source, ['cliente_nombre', 'nombre_solicitante']);
        if ($type === 'fisica') $this->setFrom($payload, 'lessor.person.full_name', $source, ['cliente_nombre', 'nombre_solicitante']);
        foreach ([
            'rfc' => ['cliente_rfc', 'rfc_solicitante'],
            'address' => ['cliente_domicilio', 'domicilio_solicitante'],
            'phone' => ['cliente_telefono'],
            'email' => ['cliente_correo', 'correo_solicitante'],
        ] as $field => $keys) $this->setFrom($payload, "lessor.person.{$field}", $source, $keys);
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $source */
    private function overlayLessee(array &$payload, array $source, ?string $type): void
    {
        if ($type === 'moral') {
            $this->setFrom($payload, 'lessee.person.legal_name', $source, ['nombre_complementaria']);
            // Apps Script replaced this outgoing value with the representative's
            // nationality for a moral lessee, so it belongs to that object.
            $this->setRepresentativeNationality($payload, 'lessee', $source['nacionalidad_complementaria'] ?? null);
        }
        if ($type === 'fisica') {
            $this->setFrom($payload, 'lessee.person.full_name', $source, ['nombre_complementaria']);
            $this->setFrom($payload, 'lessee.person.nationality', $source, ['nacionalidad_complementaria']);
        }
        // The outgoing legacy payload populated domicilio_complementaria from
        // the leased-property address for PF, so it is never personal data.
        foreach (['phone' => ['telefono_complementaria'], 'email' => ['correo_complementaria']] as $field => $keys) {
            $this->setFrom($payload, "lessee.person.{$field}", $source, $keys);
        }
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $source @param array<int,string> $keys */
    private function setFrom(array &$payload, string $path, array $source, array $keys): void
    {
        foreach ($keys as $key) {
            if (($value = $source[$key] ?? null) !== null && $value !== '') {
                $this->set($payload, $path, $value);
                return;
            }
        }
    }

    /** @param array<string,mixed> $payload */
    private function setRepresentativeNationality(array &$payload, string $party, mixed $value): void
    {
        if ($value === null || $value === '') return;
        $payload[$party]['representative'] ??= [];
        $payload[$party]['representative']['nationality'] = $value;
    }

    /** @param array<string,mixed> $payload */
    private function set(array &$payload, string $path, mixed $value): void
    {
        if ($value !== null && $value !== '') data_set($payload, $path, $value);
    }
}
