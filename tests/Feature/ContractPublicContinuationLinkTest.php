<?php

namespace Tests\Feature;

use App\Models\ContractDraft;
use App\Models\ContractPublicRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPublicContinuationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_rotates_an_editable_public_link_without_changing_the_draft(): void
    {
        [$reference, $oldToken, $public] = $this->startPublicRequest();
        $public->forceFill(['expires_at' => now()->addDay()])->save();
        $draft = $public->draft->fresh('currentVersion');
        $before = [
            'reference' => $public->public_reference,
            'draft_id' => $public->contract_draft_id,
            'version_id' => $draft->current_version_id,
            'versions' => $draft->versions()->count(),
            'payload' => $draft->currentVersion->canonical_payload,
            'hash' => $public->token_hash,
        ];

        $response = $this->actingAs($this->manager())
            ->post(route('contratos.borradores.public-continuation-link.regenerate', $draft));
        $url = $this->continuationUrl($response);
        $newToken = $this->tokenFromContinuationUrl($url);

        $public->refresh();
        $draft->refresh();
        $this->assertSame($before['reference'], $public->public_reference);
        $this->assertSame($before['draft_id'], $public->contract_draft_id);
        $this->assertSame($before['version_id'], $draft->current_version_id);
        $this->assertSame($before['versions'], $draft->versions()->count());
        $this->assertSame($before['payload'], $draft->fresh('currentVersion')->currentVersion->canonical_payload);
        $this->assertNotSame($before['hash'], $public->token_hash);
        $this->assertTrue($public->expires_at->greaterThan(now()->addDays(29)));
        $this->assertSame($reference, $public->public_reference);
        $this->assertSame(64, strlen($newToken));
        $this->get($url)->assertOk();
        $this->get(route('contrato.solicitud.step', [$reference, $oldToken, 'generales']))->assertNotFound();
    }

    public function test_submitted_or_revoked_public_requests_cannot_rotate_their_link(): void
    {
        foreach (['submitted', 'revoked'] as $state) {
            [, , $public] = $this->startPublicRequest();
            $public->forceFill([$state === 'submitted' ? 'submitted_at' : 'revoked_at' => now()])->save();
            if ($state === 'submitted') {
                $public->draft->forceFill(['status' => ContractDraft::STATUS_SUBMITTED])->save();
            }
            $hash = $public->token_hash;

            $this->actingAs($this->manager())
                ->post(route('contratos.borradores.public-continuation-link.regenerate', $public->draft))
                ->assertStatus(422);

            $this->assertSame($hash, $public->fresh()->token_hash);
        }
    }

    public function test_user_without_manage_records_permission_cannot_rotate_a_public_link(): void
    {
        [, , $public] = $this->startPublicRequest();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->post(route('contratos.borradores.public-continuation-link.regenerate', $public->draft))
            ->assertForbidden();
    }

    public function test_administrative_list_does_not_render_tokens_or_hashes(): void
    {
        [$reference, $token, $public] = $this->startPublicRequest();

        $this->actingAs($this->manager())
            ->get(route('contratos.borradores.index'))
            ->assertOk()
            ->assertSee($reference)
            ->assertDontSee($token)
            ->assertDontSee($public->token_hash)
            ->assertSee('Generar enlace de continuación');
    }

    public function test_only_the_last_of_two_regenerated_links_remains_valid(): void
    {
        [$reference, $oldToken, $public] = $this->startPublicRequest();
        $manager = $this->manager();
        $firstUrl = $this->continuationUrl($this->actingAs($manager)
            ->post(route('contratos.borradores.public-continuation-link.regenerate', $public->draft)));
        $firstToken = $this->tokenFromContinuationUrl($firstUrl);
        $secondUrl = $this->continuationUrl($this->actingAs($manager)
            ->post(route('contratos.borradores.public-continuation-link.regenerate', $public->draft)));

        $this->get(route('contrato.solicitud.step', [$reference, $oldToken, 'generales']))->assertNotFound();
        $this->get(route('contrato.solicitud.step', [$reference, $firstToken, 'generales']))->assertNotFound();
        $this->get($secondUrl)->assertOk();
    }

    /** @return array{0:string,1:string,2:ContractPublicRequest} */
    private function startPublicRequest(): array
    {
        $response = $this->post(route('contrato.solicitud.start'), ['website' => '']);
        preg_match('#/contrato/solicitud/([^/]+)/([^/]+)/generales$#', (string) $response->headers->get('Location'), $matches);

        return [$matches[1], $matches[2], ContractPublicRequest::with('draft.currentVersion')->where('public_reference', $matches[1])->firstOrFail()];
    }

    private function continuationUrl($response): string
    {
        $response->assertOk()->assertSee('Nuevo enlace de continuación');
        preg_match('#value="([^"]+/contrato/solicitud/[^"]+/generales)"#', $response->getContent(), $matches);
        $this->assertCount(2, $matches);

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    private function tokenFromContinuationUrl(string $url): string
    {
        $segments = explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'));
        $this->assertSame(['contrato', 'solicitud'], array_slice($segments, 0, 2));
        $this->assertSame('generales', $segments[4]);

        return $segments[3];
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => User::ROLE_AGENT]);
    }
}
