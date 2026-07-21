<?php

namespace Tests\Feature\Sync;

use App\Enums\ApiAuthType;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\User;
use App\Services\WordPress\WordPressDonorSyncService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressRazorpayBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_razorpay_credentials_from_bridge(): void
    {
        Http::fake([
            '*/razorpay/config' => Http::response([
                'razorpay_enabled' => true,
                'razorpay_key_id' => 'rzp_test_bridge',
                'razorpay_key_secret' => 'secret_bridge',
                'razorpay_webhook_secret' => 'whsec_bridge',
                'razorpay_mode' => 'test',
            ], 200),
        ]);

        $org = Organization::factory()->create([
            'razorpay_enabled' => false,
            'feature_overrides' => ['razorpay' => true, 'api' => true],
        ]);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $connection = OrganizationApiConnection::create([
            'organization_id' => $org->id,
            'name' => 'Bridge',
            'base_url' => 'https://partner.test/wp-json/donorconnect/v1',
            'auth_type' => ApiAuthType::Hmac,
            'credentials' => [
                'api_key' => 'key',
                'hmac_secret' => 'secret',
                'site_id' => 'site-1',
            ],
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('sync.razorpay', $connection))
            ->assertRedirect();

        $org->refresh();
        $this->assertTrue($org->razorpay_enabled);
        $this->assertSame('rzp_test_bridge', $org->razorpay_key_id);
        $this->assertSame('secret_bridge', $org->razorpay_key_secret);
    }

    public function test_payment_link_via_wordpress_bridge_when_crm_keys_missing(): void
    {
        Http::fake([
            '*/razorpay/payment-links' => Http::response([
                'ok' => true,
                'payment_link_id' => 'plink_test',
                'short_url' => 'https://rzp.io/i/test',
                'amount' => 50000,
                'currency' => 'INR',
            ], 201),
        ]);

        $org = Organization::factory()->create([
            'razorpay_enabled' => false,
            'feature_overrides' => ['razorpay' => true],
        ]);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);
        $donor = \App\Models\Donor::factory()->create(['organization_id' => $org->id]);

        OrganizationApiConnection::create([
            'organization_id' => $org->id,
            'name' => 'Bridge',
            'base_url' => 'https://partner.test/wp-json/donorconnect/v1',
            'auth_type' => ApiAuthType::Hmac,
            'credentials' => [
                'api_key' => 'key',
                'hmac_secret' => 'secret',
            ],
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('donors.payment-link', $donor), [
            'amount' => 500,
            'via' => 'wordpress',
        ])->assertRedirect();

        $this->assertDatabaseHas('razorpay_payments', [
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'razorpay_order_id' => 'plink_test',
            'amount' => 500,
        ]);
    }
}
