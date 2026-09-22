<?php

namespace App\Contracts;

interface GoogleContractDocumentClient
{
    /** @return array{id:string,url:string} */
    public function createFolder(string $parentFolderId, string $name): array;

    /** @return array{id:string,url:string} */
    public function copyTemplate(string $templateId, string $name, string $folderId): array;

    /** @param list<array<string, mixed>> $operations */
    public function applyOperations(string $documentId, array $operations): void;

    /** @param list<string> $markers
     * @return list<string>
     */
    public function remainingMarkers(string $documentId, array $markers): array;
}
