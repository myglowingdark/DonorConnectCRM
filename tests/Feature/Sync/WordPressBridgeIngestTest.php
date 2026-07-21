<?php

namespace Tests\Feature\Sync;

use App\Enums\ApiAuthType;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\OrganizationApiToken;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressBridgeIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_hmac_auth_type_exists_and_ingest_upserts_donors(): void
    {
        $this->assertSame('HMAC (DonorConnect Bridge)', ApiAuthType::Hmac->label());

        $org = Organization::factory()->create([
            'feature_overrides' => ['api' => true],
        ]);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $plain = 'dc_'.Str::lower(Str::random(40));
        OrganizationApiToken::create([
            'organization_id' => $org->id,
            'created_by' => $admin->id,
            'name' => 'bridge-test',
            'token_hash' => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 8),
            'abilities' => ['*'],
        ]);

        OrganizationContext::set($org->id);

        $this->withToken($plain)
            ->postJson('/api/v1/ingest/donors', [
                'site_id' => 'site-test-1',
                'donors' => [
                    [
                        'id' => 'ngobuddy-99',
                        'name' => 'Bridge Donor',
                        'email' => 'bridge@example.com',
                        'phone' => '+919900000099',
                        'donations' => [
                            [
                                'donation_id' => 'pay_bridge_1',
                                'amount' => 2500,
                                'currency' => 'INR',
                                'donated_at' => now()->toDateTimeString(),
                                'payment_status' => 'completed',
                                'payment_method' => 'Razorpay',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('donors', [
            'organization_id' => $org->id,
            'external_donor_id' => 'ngobuddy-99',
            'full_name' => 'Bridge Donor',
        ]);
        $this->assertDatabaseHas('donations', [
            'organization_id' => $org->id,
            'external_donation_id' => 'pay_bridge_1',
            'amount' => 2500,
        ]);
        $this->assertSame(1, Donor::query()->forOrganization($org->id)->count());
    }
}
