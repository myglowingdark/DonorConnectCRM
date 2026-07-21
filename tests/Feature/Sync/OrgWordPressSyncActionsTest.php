<?php

namespace Tests\Feature\Sync;

use App\Enums\ApiAuthType;
use App\Enums\SyncStatus;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrgWordPressSyncActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_settings_exposes_relative_action_routes(): void
    {
        [$org, $admin, $connection] = $this->seedConnection();

        $base = '/organizations/'.$org->id.'/sync/'.$connection->id;

        $this->actingAs($admin)
            ->get(route('organizations.sync.edit', $org))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sync/Settings')
                ->where('connection.id', $connection->id)
                ->where('connection.site_id', 'site-live-1')
                ->where('routes.test', $base.'/test')
                ->where('routes.run', $base.'/run')
                ->where('routes.razorpay', $base.'/razorpay')
                ->where('routes.razorpay_status', $base.'/razorpay-status')
                ->where('routes.update', $base)
            );
    }

    public function test_org_scoped_test_action_does_not_five_hundred(): void
    {
        Http::fake([
            '*/health' => Http::response(['ok' => true], 200),
        ]);

        [$org, $admin, $connection] = $this->seedConnection();

        // Regression: injecting WordPressDonorSyncService after route models caused
        // Laravel to pass organization id into $connection (TypeError 500).
        $this->actingAs($admin)
            ->from(route('organizations.sync.edit', $org))
            ->post(route('organizations.sync.test', [$org, $connection]))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_org_admin_can_run_live_bridge_actions(): void
    {
        Http::fake([
            '*/health' => Http::response(['ok' => true, 'plugin' => 'donorconnect-bridge'], 200),
            '*/donors*' => Http::response([
                [
                    'id' => 'ext-1',
                    'name' => 'Live Donor',
                    'email' => 'live@example.com',
                    'phone' => '+919900000001',
                    'donations' => [
                        [
                            'donation_id' => 'don-1',
                            'amount' => 1000,
                            'currency' => 'INR',
                            'donated_at' => now()->toDateTimeString(),
                            'payment_status' => 'completed',
                        ],
                    ],
                ],
            ], 200),
            '*/razorpay/status' => Http::response([
                'configured' => true,
                'key_id_masked' => 'rzp_test_****',
            ], 200),
            '*/razorpay/config' => Http::response([
                'razorpay_enabled' => true,
                'razorpay_key_id' => 'rzp_test_live',
                'razorpay_key_secret' => 'secret_live',
                'razorpay_mode' => 'test',
            ], 200),
        ]);

        [$org, $admin, $connection] = $this->seedConnection([
            'feature_overrides' => ['razorpay' => true, 'api' => true],
        ]);

        $this->actingAs($admin)
            ->from(route('organizations.sync.edit', $org))
            ->post(route('organizations.sync.test', [$org, $connection]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->from(route('organizations.sync.edit', $org))
            ->post(route('organizations.sync.run', [$org, $connection]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame(SyncStatus::Success, $connection->sync_status);
        $this->assertNotNull($connection->last_synced_at);
        $this->assertDatabaseHas('donors', [
            'organization_id' => $org->id,
            'external_donor_id' => 'ext-1',
            'full_name' => 'Live Donor',
        ]);

        $this->actingAs($admin)
            ->from(route('organizations.sync.edit', $org))
            ->post(route('organizations.sync.razorpay-status', [$org, $connection]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->from(route('organizations.sync.edit', $org))
            ->post(route('organizations.sync.razorpay', [$org, $connection]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $org->refresh();
        $this->assertTrue($org->razorpay_enabled);
        $this->assertSame('rzp_test_live', $org->razorpay_key_id);
    }

    public function test_saving_without_secrets_keeps_existing_hmac_credentials(): void
    {
        [$org, $admin, $connection] = $this->seedConnection();

        $this->actingAs($admin)
            ->put(route('organizations.sync.update', [$org, $connection]), [
                'name' => 'DonorConnect Bridge Updated',
                'base_url' => $connection->base_url,
                'auth_type' => ApiAuthType::Hmac->value,
                'site_id' => 'site-live-1',
                'api_key' => '',
                'hmac_secret' => '',
                'is_active' => true,
            ])
            ->assertRedirect(route('organizations.sync.edit', $org));

        $connection->refresh();
        $this->assertSame('DonorConnect Bridge Updated', $connection->name);
        $this->assertSame('key-live', $connection->credentials['api_key'] ?? null);
        $this->assertSame('secret-live', $connection->credentials['hmac_secret'] ?? null);
        $this->assertSame('site-live-1', $connection->credentials['site_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $orgOverrides
     * @return array{0: Organization, 1: User, 2: OrganizationApiConnection}
     */
    protected function seedConnection(array $orgOverrides = []): array
    {
        $org = Organization::factory()->create($orgOverrides);
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
            'sync_status' => SyncStatus::Idle,
            'is_active' => true,
        ]);

        return [$org, $admin, $connection];
    }
}
