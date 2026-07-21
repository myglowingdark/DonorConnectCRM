<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_cannot_view_donor_from_unassigned_organization(): void
    {
        $hope = Organization::factory()->create(['name' => 'Hope Foundation', 'slug' => 'hope']);
        $seva = Organization::factory()->create(['name' => 'Seva Trust', 'slug' => 'seva']);

        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($hope->id, ['is_active' => true]);

        $foreignDonor = Donor::factory()->create([
            'organization_id' => $seva->id,
            'full_name' => 'Secret Donor',
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($hope->id);

        $this->get(route('donors.show', $foreignDonor))
            ->assertForbidden();
    }

    public function test_volunteer_cannot_search_donors_from_other_organization(): void
    {
        $hope = Organization::factory()->create(['slug' => 'hope-2']);
        $seva = Organization::factory()->create(['slug' => 'seva-2']);

        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($hope->id, ['is_active' => true]);

        Donor::factory()->create([
            'organization_id' => $hope->id,
            'full_name' => 'Visible Donor',
        ]);

        Donor::factory()->create([
            'organization_id' => $seva->id,
            'full_name' => 'Hidden UniqueDonorXYZ',
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($hope->id);

        $response = $this->get(route('donors.index', ['search' => 'Hidden UniqueDonorXYZ']));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Donors/Index')
            ->has('donors.data', 0)
        );
    }

    public function test_volunteer_cannot_export_reports(): void
    {
        $org = Organization::factory()->create();
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->get(route('reports.export'))->assertForbidden();
    }

    public function test_volunteer_cannot_switch_to_unassigned_organization(): void
    {
        $hope = Organization::factory()->create(['slug' => 'hope-3']);
        $seva = Organization::factory()->create(['slug' => 'seva-3']);
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($hope->id, ['is_active' => true]);

        $this->actingAs($volunteer)
            ->post(route('organization.switch'), ['organization_id' => $seva->id])
            ->assertForbidden();
    }
}
