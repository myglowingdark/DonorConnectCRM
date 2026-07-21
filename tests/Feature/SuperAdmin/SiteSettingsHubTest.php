<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\CommissionSetting;
use App\Models\DiscountCoupon;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanInvoice;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformCommissionSetting;
use App\Models\PlatformMessagingSetting;
use App\Models\User;
use App\Services\SaaS\PlanCatalog;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PlanCatalog::class)->seed();
    }

    public function test_org_admin_cannot_open_site_settings(): void
    {
        $admin = User::factory()->orgAdmin()->create();

        $this->actingAs($admin)
            ->get(route('site-settings.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_site_settings_hub(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->get(route('site-settings.index', ['tab' => 'plans']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SiteSettings/Index')
                ->where('tab', 'plans')
                ->has('plans')
                ->has('coupons')
                ->has('commissionDefaults'));
    }

    public function test_legacy_platform_messaging_edit_redirects_to_site_settings(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->get(route('platform.messaging.edit'))
            ->assertRedirect(route('site-settings.index', ['tab' => 'messaging']));
    }

    public function test_super_admin_can_update_modules_via_site_settings(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->put(route('site-settings.modules.update'), [
                'whatsapp_module_enabled' => true,
            ])
            ->assertRedirect(route('site-settings.index', ['tab' => 'modules']));

        $this->assertTrue(PlatformMessagingSetting::current()->whatsapp_module_enabled);
    }

    public function test_super_admin_can_update_platform_billing(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->put(route('site-settings.billing.update'), [
                'enabled' => true,
                'razorpay_key_id' => 'rzp_test_123',
                'razorpay_key_secret' => 'secret_abc',
                'razorpay_webhook_secret' => 'whsec_1',
            ])
            ->assertRedirect(route('site-settings.index', ['tab' => 'billing']));

        $billing = PlatformBillingSetting::current();
        $this->assertTrue($billing->enabled);
        $this->assertSame('rzp_test_123', $billing->razorpay_key_id);
        $this->assertSame('secret_abc', $billing->razorpay_key_secret);
    }

    public function test_super_admin_can_update_plan_fees(): void
    {
        $super = User::factory()->superAdmin()->create();
        $plan = Plan::query()->where('code', 'growth')->firstOrFail();

        $this->actingAs($super)
            ->put(route('site-settings.plans.update', $plan), [
                'name' => 'Growth Plus',
                'price_monthly' => 5999,
                'seats_limit' => 20,
                'donors_limit' => 6000,
                'campaigns_limit' => 25,
                'whatsapp_monthly_limit' => 3000,
                'telecaller_hours_monthly' => null,
                'imports_monthly_limit' => 60,
                'features' => ['messaging', 'reports', 'razorpay', 'whatsapp'],
                'is_active' => true,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('site-settings.index', ['tab' => 'plans']));

        $plan->refresh();
        $this->assertSame('Growth Plus', $plan->name);
        $this->assertSame(5999, $plan->price_monthly);
        $this->assertContains('whatsapp', $plan->features);
    }

    public function test_super_admin_can_save_commission_defaults_and_seed_on_org_create(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->put(route('site-settings.commission-defaults.update'), [
                'individual_enabled' => true,
                'individual_default_percent' => 7.5,
                'shared_enabled' => true,
                'shared_percent' => 2,
                'shared_eligibility' => 'active_contributors',
                'internal_individual_enabled' => true,
                'internal_individual_default_percent' => 4,
                'internal_shared_enabled' => false,
                'internal_shared_percent' => 0,
            ])
            ->assertRedirect(route('site-settings.index', ['tab' => 'defaults']));

        $defaults = PlatformCommissionSetting::current();
        $this->assertEquals(7.5, (float) $defaults->individual_default_percent);
        $this->assertEquals(4, (float) $defaults->internal_individual_default_percent);

        $this->actingAs($super)
            ->post(route('organizations.store'), [
                'name' => 'Seeded Org',
                'slug' => 'seeded-org',
                'brand_color' => '#1e3a8a',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'is_active' => true,
            ])
            ->assertRedirect(route('organizations.index'));

        $org = Organization::query()->where('slug', 'seeded-org')->firstOrFail();
        $settings = CommissionSetting::query()->where('organization_id', $org->id)->first();
        $this->assertNotNull($settings);
        $this->assertEquals(7.5, (float) $settings->individual_default_percent);
        $this->assertEquals(4, (float) $settings->internal_individual_default_percent);
        $this->assertFalse($settings->internal_shared_enabled);
    }

    public function test_coupon_create_and_apply_on_invoice(): void
    {
        $super = User::factory()->superAdmin()->create();
        $plan = Plan::query()->where('code', 'growth')->firstOrFail();
        $org = Organization::factory()->create([
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);

        $this->actingAs($super)
            ->post(route('site-settings.coupons.store'), [
                'code' => 'SAVE20',
                'name' => 'Launch discount',
                'type' => 'percent',
                'value' => 20,
                'plan_ids' => [],
                'max_redemptions' => 10,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ])
            ->assertRedirect(route('site-settings.index', ['tab' => 'coupons']));

        $coupon = DiscountCoupon::query()->where('code', 'SAVE20')->firstOrFail();
        $this->assertSame(20, $coupon->value);

        OrganizationContext::set($org->id);

        $this->actingAs($super)
            ->post(route('billing.invoices.store'), [
                'coupon_code' => 'SAVE20',
            ])
            ->assertRedirect();

        $invoice = PlanInvoice::query()->where('organization_id', $org->id)->latest('id')->first();
        $this->assertNotNull($invoice);
        $expectedDiscount = (int) floor($plan->price_monthly * 20 / 100);
        $this->assertSame($plan->price_monthly - $expectedDiscount, $invoice->amount);
        $this->assertSame(1, $coupon->fresh()->redeemed_count);
        $this->assertSame('SAVE20', $invoice->meta['coupon_code'] ?? null);
    }

    public function test_invalid_coupon_is_rejected(): void
    {
        $super = User::factory()->superAdmin()->create();
        $plan = Plan::query()->where('code', 'growth')->firstOrFail();
        $org = Organization::factory()->create([
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);

        OrganizationContext::set($org->id);

        $this->actingAs($super)
            ->post(route('billing.invoices.store'), [
                'coupon_code' => 'NOPE',
            ])
            ->assertSessionHasErrors('coupon_code');

        $this->assertSame(0, PlanInvoice::query()->where('organization_id', $org->id)->count());
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $super = User::factory()->superAdmin()->create();
        $plan = Plan::query()->where('code', 'growth')->firstOrFail();
        $org = Organization::factory()->create([
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);

        DiscountCoupon::query()->create([
            'code' => 'OLD10',
            'name' => 'Expired',
            'type' => 'percent',
            'value' => 10,
            'plan_ids' => null,
            'max_redemptions' => null,
            'redeemed_count' => 0,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        OrganizationContext::set($org->id);

        $this->actingAs($super)
            ->post(route('billing.invoices.store'), [
                'coupon_code' => 'OLD10',
            ])
            ->assertSessionHasErrors('coupon_code');
    }
}
