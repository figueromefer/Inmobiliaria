<?php

namespace App\Services;

use App\Contracts\GoogleContractDocumentClient;
use App\Models\ContractDocumentVersion;
use App\Models\ContractDraftVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GoogleContractDocumentRenderer
{
    public function __construct(
        private GoogleContractDocumentClient $client,
        private ContractDocumentPayloadBuilder $builder,
        private ContractDocumentVersioningService $versioning,
    ) {
    }

    public function generate(ContractDocumentVersion $documentVersion): ContractDocumentVersion
    {
        [$documentVersion, $claimed] = $this->claimAttempt($documentVersion);
        if (!$claimed) {
            return $documentVersion;
        }

        try {
            $draftVersion = ContractDraftVersion::query()->findOrFail($documentVersion->contract_draft_version_id);
            $plan = $this->builder->build($draftVersion);
            if ($plan['status'] !== 'ready') {
                throw new RuntimeException('El snapshot documental ya no cumple los requisitos técnicos de generación.');
            }
            if ($plan['template_key'] !== $documentVersion->template_key || $plan['snapshot_hash'] !== $documentVersion->snapshot_hash) {
                throw new RuntimeException('La solicitud documental no coincide con el snapshot previsualizado.');
            }
            $templateId = $documentVersion->template_id ?: $plan['template_id'];
            $destination = $plan['destination_folder_id'];
            if (!is_string($templateId) || $templateId === '' || !is_string($destination) || $destination === '') {
                throw new RuntimeException('La configuración de plantilla o carpeta de Google no está completa.');
            }

            if (!$documentVersion->drive_folder_id) {
                $folder = $this->client->createFolder($destination, $plan['folder_name']);
                $documentVersion = $this->versioning->updateOperationalState($documentVersion, ['drive_folder_id' => $folder['id']]);
            }
            if (!$documentVersion->drive_file_id) {
                $copy = $this->client->copyTemplate($templateId, $plan['document_name'], $documentVersion->drive_folder_id);
                $documentVersion = $this->versioning->updateOperationalState($documentVersion, [
                    'drive_file_id' => $copy['id'],
                    'url' => $copy['url'],
                ]);
            }

            $this->client->applyOperations($documentVersion->drive_file_id, $plan['document_operations']);
            $preservedMarkers = array_values(array_filter(array_map(
                static fn (array $operation): ?string => ($operation['operation'] ?? null) === 'preserve_table'
                    ? ($operation['marker'] ?? $operation['identifier'] ?? null)
                    : null,
                $plan['document_operations'],
            )));
            $markersToVerify = array_values(array_diff($plan['template_marker_inventory']['required_markers'], $preservedMarkers));
            $remaining = $this->client->remainingMarkers($documentVersion->drive_file_id, $markersToVerify);
            if ($remaining !== []) {
                throw new RuntimeException('El documento generado conserva marcadores requeridos sin resolver.');
            }

            return $this->versioning->updateOperationalState($documentVersion, [
                'status' => ContractDocumentVersion::STATUS_GENERATED,
                'last_error' => null,
            ]);
        } catch (\Throwable $exception) {
            return $this->versioning->updateOperationalState($documentVersion, [
                'status' => ContractDocumentVersion::STATUS_FAILED,
                'last_error' => $this->safeError($exception),
            ]);
        }
    }

    /** @return array{0:ContractDocumentVersion,1:bool} */
    private function claimAttempt(ContractDocumentVersion $documentVersion): array
    {
        return DB::transaction(function () use ($documentVersion): array {
            $locked = ContractDocumentVersion::query()->lockForUpdate()->findOrFail($documentVersion->id);
            if (in_array($locked->status, [ContractDocumentVersion::STATUS_GENERATED, ContractDocumentVersion::STATUS_PROCESSING], true)) {
                return [$locked, false];
            }
            $locked->forceFill([
                'status' => ContractDocumentVersion::STATUS_PROCESSING,
                'attempts' => ((int) $locked->attempts) + 1,
                'last_error' => null,
            ])->save();

            return [$locked, true];
        });
    }

    private function safeError(\Throwable $exception): string
    {
        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return 'La generación documental falló. Revise la configuración y vuelva a intentar.';
    }
}
