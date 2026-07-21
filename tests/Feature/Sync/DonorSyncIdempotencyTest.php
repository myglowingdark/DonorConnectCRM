<?php

namespace Tests\Feature\Sync;

use App\Enums\ApiAuthType;
use App\Enums\SyncStatus;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Services\WordPress\WordPressDonorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DonorSyncIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_idempotent_for_external_ids(): void
    {
        $org = Organization::factory()->create();
        $connection = OrganizationApiConnection::create([
            'organization_id' => $org->id,
            'name' => 'WP',
            'base_url' => 'https://example.test/wp-json/donorconnect/v1',
            'auth_type' => ApiAuthType::None,
            'credentials' => null,
            'field_mappings' => null,
            'sync_settings' => [
                'per_page' => 100,
                'endpoints' => ['donors' => '/donors'],
            ],
            'sync_status' => SyncStatus::Idle,
            'is_active' => true,
        ]);

        $payload = [
            [
                'id' => 'ext-100',
                'name' => 'Anita Mehta',
                'email' => 'anita@example.com',
                'phone' => '+91 9811111111',
                'donations' => [
                    [
                        'donation_id' => 'don-100',
                        'amount' => 5000,
                        'currency' => 'INR',
                        'donated_at' => '2026-01-15 10:00:00',
                        'payment_status' => 'completed',
                    ],
                ],
            ],
        ];

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push($payload)
                ->push($payload)
                ->push([]),
        ]);

        $service = app(WordPressDonorSyncService::class);
        $service->sync($connection->fresh());
        $service->sync($connection->fresh());

        $this->assertEquals(1, Donor::query()->forOrganization($org->id)->where('external_donor_id', 'ext-100')->count());
        $this->assertEquals(1, Donation::query()->forOrganization($org->id)->where('external_donation_id', 'don-100')->count());
    }
}
