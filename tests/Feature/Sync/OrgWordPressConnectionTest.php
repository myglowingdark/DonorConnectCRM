<?php

namespace Tests\Feature\Sync;

use App\Enums\ApiAuthType;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgWordPressConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_can_connect_wordpress_for_own_org(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('organizations.sync.edit', $org))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sync/Settings')
                ->where('organization.id', $org->id)
            );

        $this->actingAs($admin)
            ->post(route('organizations.sync.store', $org), [
                'name' => 'Hope Bridge',
                'base_url' => 'https://hope.example.org/wp-json/donorconnect/v1',
                'auth_type' => ApiAuthType::Hmac->value,
                'site_id' => 'site-hope',
                'api_key' => 'key-hope',
                'hmac_secret' => 'secret-hope',
                'is_active' => true,
            ])
            ->assertRedirect(route('organizations.sync.edit', $org));

        $this->assertDatabaseHas('organization_api_connections', [
            'organization_id' => $org->id,
            'base_url' => 'https://hope.example.org/wp-json/donorconnect/v1',
        ]);
    }

    public function test_super_admin_can_connect_wordpress_for_any_org(): void
    {
        $org = Organization::factory()->create();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->post(route('organizations.sync.store', $org), [
                'name' => 'Partner Bridge',
                'base_url' => 'https://partner.example.org/wp-json/donorconnect/v1',
                'auth_type' => ApiAuthType::Hmac->value,
                'site_id' => 'site-partner',
                'api_key' => 'key-partner',
                'hmac_secret' => 'secret-partner',
                'is_active' => true,
            ])
            ->assertRedirect(route('organizations.sync.edit', $org));

        $this->assertSame(1, OrganizationApiConnection::query()->where('organization_id', $org->id)->count());
    }

    public function test_org_admin_cannot_connect_wordpress_for_other_org(): void
    {
        $own = Organization::factory()->create();
        $other = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($own->id, ['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('organizations.sync.edit', $other))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('organizations.sync.store', $other), [
                'name' => 'Nope',
                'base_url' => 'https://other.example.org/wp-json/donorconnect/v1',
                'auth_type' => ApiAuthType::Hmac->value,
                'site_id' => 'x',
                'api_key' => 'k',
                'hmac_secret' => 's',
            ])
            ->assertForbidden();
    }

    public function test_team_lead_cannot_manage_wordpress_connection(): void
    {
        $org = Organization::factory()->create();
        $lead = User::factory()->create(['role' => 'team_lead']);
        $lead->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($lead)
            ->get(route('organizations.sync.edit', $org))
            ->assertForbidden();
    }
}
