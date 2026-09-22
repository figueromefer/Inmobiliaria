<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractDraftVersionConflictException;
use App\Models\ContractDraft;
use App\Models\ContractPublicRequest;
use App\Services\ContractDraftPayload;
use App\Services\ContractDocumentPayloadBuilder;
use App\Services\ContractDraftVersioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicContractRequestController extends Controller
{
    private const STEPS = ['generales' => 'Datos generales', 'arrendador' => 'Arrendador', 'arrendatario' => 'Arrendatario', 'tercero' => 'Tercero / fiador', 'garantia' => 'Garantía', 'vigencia' => 'Vigencia e importes', 'uso' => 'Uso del inmueble', 'pago' => 'Forma de pago', 'mantenimiento' => 'Mantenimiento', 'renovacion' => 'Renovación', 'resumen' => 'Revisión y envío'];

    public function create() { return view('contrato.public_start'); }

    public function start(Request $request, ContractDraftPayload $payloads, ContractDraftVersioningService $versioning)
    {
        $request->validate(['website' => ['nullable', 'max:0']]);
        $token = Str::random(64);
        $reference = (string) Str::ulid();
        $payload = $payloads->empty();
        $payload['metadata']['source'] = 'public_form';
        $draft = $versioning->createDraft($payload, ['source' => 'public_form', 'external_id' => $reference, 'status' => ContractDraft::STATUS_DRAFT], null, ContractDraftPayload::SCHEMA_VERSION, 'public_started');
        ContractPublicRequest::create(['contract_draft_id' => $draft->id, 'public_reference' => $reference, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(30)]);

        return redirect()->route('contrato.solicitud.step', [$reference, $token, 'generales']);
    }

    public function show(string $reference, string $token, string $step)
    {
        $this->step($step);
        $public = $this->resolve($reference, $token);
        return view('contrato.public_wizard', ['public' => $public, 'draft' => $public->draft->load('currentVersion'), 'step' => $step, 'steps' => self::STEPS, 'payload' => $public->draft->currentVersion->canonical_payload, 'token' => $token]);
    }

    public function save(Request $request, string $reference, string $token, string $step, ContractDraftPayload $payloads, ContractDraftVersioningService $versioning)
    {
        $this->step($step); abort_if($step === 'resumen', 405);
        $public = $this->resolve($reference, $token);
        $data = $request->validate(['payload' => ['required', 'array'], 'expected_version_id' => ['required', 'integer', 'min:1'], 'website' => ['nullable', 'max:0'], 'next' => ['nullable', 'boolean']]);
        foreach ([
            ['metadata', 'cliente_id'], ['metadata', 'propiedad_id'], ['metadata', 'inquilino_id'],
            ['metadata', 'source'], ['metadata', 'external_id'],
            ['leased_property', 'master_property_id'],
        ] as [$section, $field]) {
            if (array_key_exists($field, $data['payload'][$section] ?? [])) {
                throw ValidationException::withMessages(['payload' => 'No se permiten campos administrativos.']);
            }
        }
        try {
            $versioning->appendVersionFromExpected($public->draft, (int) $data['expected_version_id'], fn (array $current) => $payloads->validateAndNormalize($payloads->mergeForEditing($current, $data['payload'])), null, ContractDraftPayload::SCHEMA_VERSION, 'public_saved:'.$step);
        } catch (ContractDraftVersionConflictException) { throw ValidationException::withMessages(['expected_version_id' => 'Esta solicitud cambió en otra pestaña. Recarga la página antes de guardar.']); }
        $target = $request->boolean('next') ? $this->next($step) : $step;
        return redirect()->route('contrato.solicitud.step', [$reference, $token, $target])->with('success', 'Tu información se guardó. Puedes continuar después con este mismo enlace.');
    }

    public function submit(Request $request, string $reference, string $token, ContractDocumentPayloadBuilder $builder)
    {
        $request->validate(['website' => ['nullable', 'max:0']]);
        $public = $this->resolve($reference, $token);
        $plan = $builder->build($public->draft->currentVersion);
        if ($plan['status'] === 'blocked') {
            throw ValidationException::withMessages(['submission' => 'Completa los datos requeridos antes de enviar: '.implode(' ', $plan['reasons'])]);
        }
        $public->forceFill(['submitted_at' => now()])->save();
        $public->draft->forceFill(['status' => ContractDraft::STATUS_SUBMITTED])->save();
        return redirect()->route('contrato.solicitud.recibida', $reference);
    }

    public function received(string $reference)
    {
        $public = ContractPublicRequest::query()->where('public_reference', $reference)->whereNotNull('submitted_at')->firstOrFail();
        return view('contrato.public_received', compact('public'));
    }

    private function resolve(string $reference, string $token): ContractPublicRequest
    {
        $public = ContractPublicRequest::query()->with('draft.currentVersion')->where('public_reference', $reference)->firstOrFail();
        abort_unless($public->accepts($token), 404);
        return $public;
    }
    private function step(string $step): void { abort_unless(array_key_exists($step, self::STEPS), 404); }
    private function next(string $step): string { $keys = array_keys(self::STEPS); $index = array_search($step, $keys, true); return $keys[min($index + 1, count($keys) - 1)]; }
}
