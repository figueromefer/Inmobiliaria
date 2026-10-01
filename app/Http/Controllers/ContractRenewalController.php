<?php

namespace App\Http\Controllers;

use App\Models\ContractDraft;
use App\Models\Contrato;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractRenewalController extends Controller
{
    public function store(Request $request, Contrato $contrato, ContractDraftPayload $payloads, ContractDraftVersioningService $versions): RedirectResponse
    {
        abort_if($contrato->origen === 'justicia_alternativa', 404);
        $draft = ContractDraft::query()
            ->where('renewal_of_contract_id', $contrato->id)
            ->where('purpose', 'renewal')
            ->where('created_by', $request->user()->id)
            ->where('status', ContractDraft::STATUS_DRAFT)
            ->first();

        if (! $draft) {
            $contrato->load(['draftVersion', 'cliente', 'propiedad', 'inquilino']);
            $snapshot = $contrato->draftVersion?->canonical_payload;
            $payload = $snapshot ?: $this->legacyPayload($contrato, $payloads);
            $payload['renewal']['is_renewal'] = 'yes';
            $payload['renewal']['previous_contract_id'] = $contrato->id;
            $draft = $versions->createDraft(
                $payload,
                [
                    'source' => 'internal', 'purpose' => 'renewal', 'renewal_of_contract_id' => $contrato->id,
                    'cliente_id' => $contrato->fk_cliente, 'propiedad_id' => $contrato->fk_propiedad,
                    'inquilino_id' => $contrato->inquilino_id, 'created_by' => $request->user()->id,
                ],
                $snapshot ? null : ['renewal_origin' => 'legacy_fallback'],
                ContractDraftPayload::SCHEMA_VERSION,
                $snapshot ? 'renewal_started_from_snapshot' : 'renewal_started_from_legacy_fallback',
            );
        }

        return redirect()->route('contratos.privados.wizard.show', [$draft, 'vigencia']);
    }

    /** @return array<string, mixed> */
    private function legacyPayload(Contrato $contrato, ContractDraftPayload $payloads): array
    {
        $payload = $payloads->empty();
        $payload['metadata']['contract_date'] = $contrato->fecha?->toDateString();
        $payload['lessor']['person']['full_name'] = $contrato->cliente?->nombre;
        $payload['lessor']['person']['rfc'] = $contrato->cliente?->rfc;
        $payload['lessor']['person']['address'] = $contrato->cliente?->domicilio;
        $payload['lessor']['person']['phone'] = $contrato->cliente?->celular ?: $contrato->cliente?->fijo;
        $payload['lessor']['person']['email'] = $contrato->cliente?->correo;
        $payload['lessee']['person']['full_name'] = $contrato->inquilino?->nombre;
        $payload['lessee']['person']['nationality'] = $contrato->inquilino?->nacionalidad;
        $payload['lessee']['person']['address'] = $contrato->inquilino?->domicilio;
        $payload['lessee']['person']['phone'] = $contrato->inquilino?->telefono;
        $payload['lessee']['person']['email'] = $contrato->inquilino?->correo;
        $payload['leased_property']['alias'] = $contrato->propiedad?->alias;
        $payload['leased_property']['address'] = $contrato->domicilio_inmueble ?: $contrato->propiedad?->domicilio;
        $payload['term']['start_date'] = $contrato->fecha_inicio?->toDateString();
        $payload['term']['end_date'] = $contrato->fecha_fin?->toDateString();
        $payload['term']['rent_due_rule']['raw_text'] = $contrato->dias_pago ? (string) $contrato->dias_pago : null;
        foreach (['total_rent' => 'monto_total', 'monthly_rent' => 'monto_mensual', 'security_deposit' => 'monto_deposito', 'rental_commission' => 'comision_renta', 'monthly_commission_value' => 'comision_mensual'] as $target => $source) {
            $payload['amounts'][$target] = $contrato->{$source};
        }

        return $payload;
    }
}
