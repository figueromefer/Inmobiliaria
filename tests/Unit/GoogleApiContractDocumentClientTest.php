<?php

namespace Tests\Unit;

use App\Services\GoogleApiContractDocumentClient;
use Tests\TestCase;

class GoogleApiContractDocumentClientTest extends TestCase
{
    public function test_paragraph_matching_concatenates_fragmented_text_runs(): void
    {
        $client = app(GoogleApiContractDocumentClient::class);
        $method = new \ReflectionMethod($client, 'paragraphText');

        $text = $method->invoke($client, [
            'elements' => [
                ['textRun' => ['content' => 'Que es una persona física, mayor de edad, ']],
                ['textRun' => ['content' => 'quien manifiesta que tiene facultades.']],
            ],
        ]);

        $this->assertSame(
            'Que es una persona física, mayor de edad, quien manifiesta que tiene facultades.',
            $text,
        );
    }
}
