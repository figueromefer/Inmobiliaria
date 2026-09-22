<?php

namespace App\Services;

use App\Contracts\GoogleContractDocumentClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleApiContractDocumentClient implements GoogleContractDocumentClient
{
    public function __construct(private GoogleServiceAccountTokenProvider $tokens)
    {
    }

    public function createFolder(string $parentFolderId, string $name): array
    {
        $response = $this->drive()->post('files?supportsAllDrives=true', [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentFolderId],
        ]);
        $this->ensureSuccessful($response->successful());

        return $this->fileReference((array) $response->json());
    }

    public function copyTemplate(string $templateId, string $name, string $folderId): array
    {
        $response = $this->drive()->post("files/{$templateId}/copy?supportsAllDrives=true", [
            'name' => $name,
            'parents' => [$folderId],
        ]);
        $this->ensureSuccessful($response->successful());

        return $this->fileReference((array) $response->json());
    }

    public function applyOperations(string $documentId, array $operations): void
    {
        foreach ($operations as $operation) {
            $name = $operation['operation'] ?? null;
            if ($name === 'preserve_table') {
                continue;
            }
            if ($name === 'replace_placeholders') {
                $requests = [];
                foreach ($operation['content'] ?? [] as $marker => $value) {
                    $requests[] = ['replaceAllText' => ['containsText' => ['text' => $marker, 'matchCase' => true], 'replaceText' => (string) $value]];
                }
                $this->batchUpdate($documentId, $requests);
                continue;
            }
            if (in_array($name, ['replace_marker', 'remove_marker', 'remove_text'], true)) {
                $marker = (string) ($operation['marker'] ?? '');
                if ($marker === '') {
                    throw new RuntimeException('La operación documental no identifica un marcador físico.');
                }
                $this->batchUpdate($documentId, [[
                    'replaceAllText' => [
                        'containsText' => ['text' => $marker, 'matchCase' => true],
                        'replaceText' => (string) ($operation['content'] ?? ''),
                    ],
                ]]);
                continue;
            }
            if (in_array($name, ['remove_table', 'remove_table_with_preceding_title'], true)) {
                $marker = (string) ($operation['marker'] ?? $operation['identifier'] ?? '');
                $range = $this->tableRangeForMarker($documentId, $marker, $name === 'remove_table_with_preceding_title');
                if ($range !== null) {
                    $this->batchUpdate($documentId, [['deleteContentRange' => ['range' => $range]]]);
                }
                continue;
            }
            if ($name === 'remove_paragraph_containing') {
                $marker = (string) ($operation['marker'] ?? '');
                $range = $this->paragraphRangeForMarker($documentId, $marker);
                if ($range !== null) {
                    $this->batchUpdate($documentId, [['deleteContentRange' => ['range' => $range]]]);
                }
                continue;
            }
            throw new RuntimeException('La operación documental solicitada no es compatible con el renderer.');
        }
    }

    public function remainingMarkers(string $documentId, array $markers): array
    {
        $document = $this->document($documentId);
        $serialized = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return array_values(array_filter($markers, static fn (string $marker): bool => str_contains($serialized, $marker)));
    }

    /** @param list<array<string, mixed>> $requests */
    private function batchUpdate(string $documentId, array $requests): void
    {
        if ($requests === []) {
            return;
        }
        $response = $this->docs()->post("documents/{$documentId}:batchUpdate", ['requests' => $requests]);
        $this->ensureSuccessful($response->successful());
    }

    /** @return array<string, mixed> */
    private function document(string $documentId): array
    {
        $response = $this->docs()->get("documents/{$documentId}", ['includeTabsContent' => 'true']);
        $this->ensureSuccessful($response->successful());

        return (array) $response->json();
    }

    /** @return array{startIndex:int,endIndex:int,tabId?:string}|null */
    private function tableRangeForMarker(string $documentId, string $marker, bool $includePrecedingTitle): ?array
    {
        foreach ($this->tabContents($this->document($documentId)) as $tab) {
            $content = $tab['content'];
            foreach ($content as $index => $element) {
                if (!isset($element['table']) || !str_contains(json_encode($element['table'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $marker)) {
                    continue;
                }
                $start = (int) $element['startIndex'];
                if ($includePrecedingTitle && $index > 0 && isset($content[$index - 1]['paragraph']['elements'])) {
                    $start = (int) $content[$index - 1]['startIndex'];
                }
                $range = ['startIndex' => $start, 'endIndex' => (int) $element['endIndex']];
                if (($tab['tabId'] ?? null) !== null) {
                    $range['tabId'] = $tab['tabId'];
                }
                return $range;
            }
        }
        return null;
    }

    /** @return array{startIndex:int,endIndex:int,tabId?:string}|null */
    private function paragraphRangeForMarker(string $documentId, string $marker): ?array
    {
        foreach ($this->tabContents($this->document($documentId)) as $tab) {
            foreach ($tab['content'] as $element) {
                if (!isset($element['paragraph']) || !str_contains($this->paragraphText($element['paragraph']), $marker)) {
                    continue;
                }
                $range = ['startIndex' => (int) $element['startIndex'], 'endIndex' => (int) $element['endIndex']];
                if (($tab['tabId'] ?? null) !== null) {
                    $range['tabId'] = $tab['tabId'];
                }
                return $range;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $paragraph */
    private function paragraphText(array $paragraph): string
    {
        $text = '';
        foreach ($paragraph['elements'] ?? [] as $element) {
            $text .= $element['textRun']['content'] ?? '';
        }

        return $text;
    }

    /** @return list<array{tabId:?string,content:list<array<string,mixed>>}> */
    private function tabContents(array $document): array
    {
        if (isset($document['tabs'])) {
            return array_values(array_filter(array_map(static function (array $tab): ?array {
                $content = $tab['documentTab']['body']['content'] ?? null;
                return is_array($content) ? ['tabId' => $tab['tabProperties']['tabId'] ?? null, 'content' => $content] : null;
            }, $document['tabs'])));
        }

        return isset($document['body']['content']) ? [['tabId' => null, 'content' => $document['body']['content']]] : [];
    }

    private function drive(): PendingRequest
    {
        return $this->request()->baseUrl('https://www.googleapis.com/drive/v3/');
    }

    private function docs(): PendingRequest
    {
        return $this->request()->baseUrl('https://docs.googleapis.com/v1/');
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken())
            ->acceptJson()
            ->timeout((int) config('services.google_contracts.timeout', 20));
    }

    /** @return array{id:string,url:string} */
    private function fileReference(array $payload): array
    {
        $id = $payload['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Google no devolvió el identificador del archivo creado.');
        }

        return ['id' => $id, 'url' => "https://docs.google.com/document/d/{$id}/edit"];
    }

    private function ensureSuccessful(bool $successful): void
    {
        if (!$successful) {
            throw new RuntimeException('Google no pudo completar la operación documental.');
        }
    }
}
