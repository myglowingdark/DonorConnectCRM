<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaleOrganizationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_with_stale_org_session_is_redirected_from_org_routes(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->withSession(['current_organization_id' => 99999])
            ->get(route('transfers.index'))
            ->assertRedirect(route('organizations.index'));
    }

    public function test_super_admin_with_no_orgs_can_open_dashboard(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->withSession(['current_organization_id' => 99999])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Dashboard')
                ->has('organizations', 0)
            );
    }

    public function test_ensure_for_clears_deleted_organization_from_session(): void
    {
        $super = User::factory()->superAdmin()->create();
        $org = Organization::factory()->create();

        $this->actingAs($super)
            ->withSession(['current_organization_id' => $org->id])
            ->get(route('dashboard'))
            ->assertOk();

        $org->delete();

        $this->actingAs($super)
            ->withSession(['current_organization_id' => $org->id])
            ->get(route('transfers.index'))
            ->assertRedirect(route('organizations.index'));
    }
}
