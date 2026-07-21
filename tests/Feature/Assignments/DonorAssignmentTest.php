<?php

namespace Tests\Feature\Assignments;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_can_assign_donors_to_volunteer(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $volunteer = User::factory()->volunteer()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $donors = Donor::factory()->count(2)->create(['organization_id' => $org->id]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('assignments.store'), [
            'volunteer_id' => $volunteer->id,
            'donor_ids' => $donors->pluck('id')->all(),
        ])->assertRedirect();

        $this->assertEquals(2, DonorAssignment::query()
            ->where('organization_id', $org->id)
            ->where('volunteer_id', $volunteer->id)
            ->where('is_active', true)
            ->count());
    }

    public function test_cannot_assign_donor_from_another_organization(): void
    {
        $hope = Organization::factory()->create(['slug' => 'hope-a']);
        $seva = Organization::factory()->create(['slug' => 'seva-a']);
        $admin = User::factory()->orgAdmin()->create();
        $volunteer = User::factory()->volunteer()->create();
        $admin->organizations()->attach($hope->id, ['is_active' => true]);
        $volunteer->organizations()->attach($hope->id, ['is_active' => true]);

        $foreignDonor = Donor::factory()->create(['organization_id' => $seva->id]);

        $this->actingAs($admin);
        OrganizationContext::set($hope->id);

        $this->post(route('assignments.store'), [
            'volunteer_id' => $volunteer->id,
            'donor_ids' => [$foreignDonor->id],
        ])->assertSessionHasErrors('donor_ids');
    }
}
