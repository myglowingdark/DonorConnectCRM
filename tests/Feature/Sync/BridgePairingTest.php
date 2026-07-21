<?php

namespace Tests\Feature\Sync;

use App\Enums\ApiAuthType;
use App\Models\BridgePairingCode;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\User;
use App\Services\WordPress\BridgePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BridgePairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_can_generate_pairing_code(): void
    {
        [$org, $admin] = $this->seedOrgAdmin();

        $response = $this->actingAs($admin)
            ->post(route('organizations.sync.pairing-code', $org));

        $response->assertRedirect()
            ->assertSessionHas('bridge_pairing_code')
            ->assertSessionHas('bridge_pairing_expires_at');

        $plaintext = session('bridge_pairing_code');
        $this->assertStringStartsWith('dc_pair_', $plaintext);

        $this->assertDatabaseHas('bridge_pairing_codes', [
            'organization_id' => $org->id,
            'code_hash' => hash('sha256', $plaintext),
            'created_by' => $admin->id,
        ]);
    }

    public function test_claim_pairing_code_creates_connection_and_push_token(): void
    {
        [$org, $admin] = $this->seedOrgAdmin();
        $pairing = app(BridgePairingService::class)->generate($org, $admin);

        $response = $this->postJson(route('api.bridge.pair'), [
            'site_id' => 'wp-site-abc',
            'api_key' => 'wp-api-key-123',
            'hmac_secret' => 'wp-hmac-secret-456',
            'rest_base_url' => 'https://partner.example.org/wp-json/donorconnect/v1',
        ], [
            'Authorization' => 'Bearer '.$pairing['plaintext'],
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('organization_id', $org->id)
            ->assertJsonStructure(['connection_id', 'push_token', 'push_token_prefix']);

        $connection = OrganizationApiConnection::query()->where('organization_id', $org->id)->first();
        $this->assertNotNull($connection);
        $this->assertSame(ApiAuthType::Hmac, $connection->auth_type);
        $this->assertSame('wp-api-key-123', $connection->credentials['api_key'] ?? null);
        $this->assertSame('wp-hmac-secret-456', $connection->credentials['hmac_secret'] ?? null);
        $this->assertSame('wp-site-abc', $connection->credentials['site_id'] ?? null);

        $this->assertDatabaseHas('organization_api_tokens', [
            'organization_id' => $org->id,
            'name' => 'WordPress Bridge (paired)',
        ]);

        $code = BridgePairingCode::query()->where('organization_id', $org->id)->first();
        $this->assertNotNull($code->used_at);
    }

    public function test_claim_pairing_code_cannot_be_reused(): void
    {
        [$org, $admin] = $this->seedOrgAdmin();
        $pairing = app(BridgePairingService::class)->generate($org, $admin);
        $headers = ['Authorization' => 'Bearer '.$pairing['plaintext']];
        $payload = [
            'site_id' => 'wp-site-abc',
            'api_key' => 'wp-api-key-123',
            'hmac_secret' => 'wp-hmac-secret-456',
            'rest_base_url' => 'https://partner.example.org/wp-json/donorconnect/v1',
        ];

        $this->postJson(route('api.bridge.pair'), $payload, $headers)->assertOk();

        $this->postJson(route('api.bridge.pair'), $payload, $headers)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired pairing code.');
    }

    public function test_wrong_or_expired_pairing_code_returns_unauthorized(): void
    {
        [$org, $admin] = $this->seedOrgAdmin();
        $pairing = app(BridgePairingService::class)->generate($org, $admin);

        BridgePairingCode::query()
            ->where('code_hash', hash('sha256', $pairing['plaintext']))
            ->update(['expires_at' => now()->subMinute()]);

        $payload = [
            'site_id' => 'wp-site-abc',
            'api_key' => 'key',
            'hmac_secret' => 'secret',
            'rest_base_url' => 'https://partner.example.org/wp-json/donorconnect/v1',
        ];

        $this->postJson(route('api.bridge.pair'), $payload, [
            'Authorization' => 'Bearer '.$pairing['plaintext'],
        ])->assertUnauthorized();

        $this->postJson(route('api.bridge.pair'), $payload, [
            'Authorization' => 'Bearer dc_pair_invalid_code',
        ])->assertUnauthorized();
    }

    public function test_bridge_401_writes_structured_log_entry(): void
    {
        Event::fake();

        Http::fake([
            '*/health' => Http::response([
                'code' => 'dc_unauthorized',
                'message' => 'API key does not match.',
            ], 401),
        ]);

        [$org, $admin, $connection] = $this->seedConnection();

        $this->actingAs($admin)
            ->from(route('organizations.sync.edit', $org))
            ->post(route('organizations.sync.test', [$org, $connection]))
            ->assertRedirect()
            ->assertSessionHas('error');

        Event::assertDispatched(\Illuminate\Log\Events\MessageLogged::class, function ($event) use ($connection) {
            return $event->level === 'error'
                && $event->message === 'wordpress.bridge.http_failed'
                && ($event->context['connection_id'] ?? null) === $connection->id
                && ($event->context['http_status'] ?? null) === 401
                && ($event->context['wp_code'] ?? null) === 'dc_unauthorized';
        });
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    protected function seedOrgAdmin(): array
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        return [$org, $admin];
    }

    /**
     * @return array{0: Organization, 1: User, 2: OrganizationApiConnection}
     */
    protected function seedConnection(): array
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $connection = OrganizationApiConnection::create([
            'organization_id' => $org->id,
            'name' => 'DonorConnect Bridge',
            'base_url' => 'https://partner.example.org/wp-json/donorconnect/v1',
            'auth_type' => ApiAuthType::Hmac,
            'credentials' => [
                'api_key' => 'key-live',
                'key' => 'key-live',
                'header' => 'X-DC-API-Key',
                'hmac_secret' => 'secret-live',
                'site_id' => 'site-live-1',
            ],
            'is_active' => true,
        ]);

        return [$org, $admin, $connection];
    }
}
