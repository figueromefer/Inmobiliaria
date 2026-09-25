<?php

namespace Tests\Unit;

use App\Services\ContractDraftPayload;
use Tests\TestCase;

class ContractDraftPayloadTest extends TestCase
{
    public function test_it_normalizes_mxn_transport_format_without_float_rounding(): void
    {
        $payloads = app(ContractDraftPayload::class);
        $payload = $payloads->empty();
        $payload['amounts']['monthly_rent'] = '$23,000.50';
        $payload['amounts']['security_deposit'] = '$23,000.00';

        $normalized = $payloads->validateAndNormalize($payload);

        $this->assertSame('23000.50', $normalized['amounts']['monthly_rent']);
        $this->assertSame('23000.00', $normalized['amounts']['security_deposit']);
    }
}
