<?php

namespace Tests\Feature\Users;

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgAdminVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_does_not_see_volunteer_other_organization_memberships(): void
    {
        $hope = Organization::factory()->create(['name' => 'Hope Foundation', 'slug' => 'hope-vis']);
        $seva = Organization::factory()->create(['name' => 'Seva Trust', 'slug' => 'seva-vis']);

        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($hope->id, ['is_active' => true]);

        $volunteer = User::factory()->volunteer()->create(['name' => 'Priya Multi']);
        $volunteer->organizations()->attach([
            $hope->id => ['is_active' => true],
            $seva->id => ['is_active' => true],
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($hope->id);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data', 2) // admin + volunteer
                ->where('users.data', function ($users) use ($hope, $seva) {
                    $priya = collect($users)->firstWhere('name', 'Priya Multi');

                    if (! $priya) {
                        return false;
                    }

                    $orgNames = collect($priya['organizations'])->pluck('name');

                    return $orgNames->contains('Hope Foundation')
                        && ! $orgNames->contains('Seva Trust');
                })
            );
    }

    public function test_org_admin_update_preserves_other_organization_memberships(): void
    {
        $hope = Organization::factory()->create(['slug' => 'hope-keep']);
        $seva = Organization::factory()->create(['slug' => 'seva-keep']);

        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($hope->id, ['is_active' => true]);

        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach([
            $hope->id => ['is_active' => true],
            $seva->id => ['is_active' => true],
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($hope->id);

        $this->put(route('users.update', $volunteer), [
            'name' => $volunteer->name,
            'email' => $volunteer->email,
            'phone' => $volunteer->phone,
            'role' => 'volunteer',
            'organization_ids' => [$hope->id],
            'is_active' => true,
        ])->assertRedirect();

        $volunteer->refresh();
        $this->assertTrue($volunteer->belongsToOrganization($hope->id));
        $this->assertTrue($volunteer->belongsToOrganization($seva->id));
    }
}
