<?php

namespace Tests\Feature\SaaS;

use App\Models\Donor;
use App\Models\Organization;
use App\Models\OrganizationApiToken;
use App\Models\Plan;
use App\Models\User;
use App\Services\SaaS\PlanCatalog;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaasPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PlanCatalog::class)->seed();
    }

    protected function growthOrgWithAdmin(): array
    {
        $plan = Plan::query()->where('code', 'growth')->firstOrFail();
        $org = Organization::factory()->create(['plan_id' => $plan->id, 'subscription_status' => 'active']);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        return [$org, $admin];
    }

    protected function freeOrgWithAdmin(): array
    {
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $org = Organization::factory()->create(['plan_id' => $plan->id, 'subscription_status' => 'active']);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        return [$org, $admin];
    }

    public function test_suspended_org_hard_lock_403_for_org_admin(): void
    {
        [$org, $admin] = $this->freeOrgWithAdmin();
        $org->update(['subscription_status' => 'suspended']);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->get(route('donors.index'))->assertForbidden();
    }

    public function test_plan_feature_gate_blocks_webhooks_ui(): void
    {
        [$org, $admin] = $this->freeOrgWithAdmin();

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->get(route('webhooks.index'))->assertForbidden();
    }

    public function test_impersonation_start_and_leave(): void
    {
        $super = User::factory()->superAdmin()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        [$org, $admin] = $this->freeOrgWithAdmin();

        $this->actingAs($super);
        OrganizationContext::set($org->id);

        $this->post(route('impersonation.start', $admin))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(session()->has('impersonator_id'));

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($super);
        $this->assertFalse(session()->has('impersonator_id'));
    }

    public function test_api_token_can_list_donors(): void
    {
        [$org] = $this->growthOrgWithAdmin();
        Donor::factory()->create(['organization_id' => $org->id, 'full_name' => 'API Donor']);

        $plaintext = 'dc_'.Str::random(40);
        OrganizationApiToken::create([
            'organization_id' => $org->id,
            'name' => 'Integration',
            'token_hash' => hash('sha256', $plaintext),
            'token_prefix' => substr($plaintext, 0, 12),
        ]);

        $this->getJson('/api/v1/donors', [
            'Authorization' => 'Bearer '.$plaintext,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'API Donor');
    }

    public function test_payment_link_endpoint_blocked_when_razorpay_not_entitled(): void
    {
        [$org, $admin] = $this->freeOrgWithAdmin();
        $donor = Donor::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->postJson(route('donors.payment-link', $donor), ['amount' => 500])
            ->assertForbidden();
    }

    public function test_capacity_booking_create(): void
    {
        $plan = Plan::query()->where('code', 'enterprise')->firstOrFail();
        $org = Organization::factory()->create(['plan_id' => $plan->id, 'subscription_status' => 'active']);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('capacity.store'), [
            'seats' => 3,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
            'notes' => 'Campaign push',
        ])->assertRedirect();

        $this->assertDatabaseHas('telecaller_capacity_bookings', [
            'organization_id' => $org->id,
            'seats' => 3,
            'status' => 'pending',
        ]);
    }

    public function test_audit_index_returns_200_for_org_admin(): void
    {
        [$org, $admin] = $this->freeOrgWithAdmin();

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Audit/Index'));
    }
}
