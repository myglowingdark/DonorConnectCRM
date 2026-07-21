<?php

namespace Tests\Feature\Calling;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialerQueueSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_can_open_and_skip_queue_donor(): void
    {
        $org = Organization::factory()->create();
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $first = Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'First Donor',
            'next_follow_up_at' => null,
            'do_not_call' => false,
        ]);
        $second = Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Second Donor',
            'next_follow_up_at' => null,
            'do_not_call' => false,
        ]);

        foreach ([$first, $second] as $donor) {
            DonorAssignment::create([
                'organization_id' => $org->id,
                'donor_id' => $donor->id,
                'volunteer_id' => $volunteer->id,
                'assigned_by' => $volunteer->id,
                'is_active' => true,
            ]);

            // Newer inactive row — breaks hasOne()->where()->latestOfMany()
            DonorAssignment::create([
                'organization_id' => $org->id,
                'donor_id' => $donor->id,
                'volunteer_id' => User::factory()->volunteer()->create()->id,
                'assigned_by' => $volunteer->id,
                'is_active' => false,
            ]);
        }

        $this->assertNotNull($first->fresh()->activeAssignment);
        $this->assertSame($volunteer->id, $first->fresh()->activeAssignment->volunteer_id);

        $this->actingAs($volunteer)
            ->withSession(['current_organization_id' => $org->id])
            ->get(route('donors.show', $first))
            ->assertOk();

        $this->actingAs($volunteer)
            ->withSession(['current_organization_id' => $org->id])
            ->get(route('dialer.queue'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dialer/Queue')
                ->where('donor.id', $first->id)
            );

        $this->actingAs($volunteer)
            ->withSession(['current_organization_id' => $org->id])
            ->post(route('dialer.skip'), ['donor_id' => $first->id])
            ->assertRedirect(route('dialer.queue'));

        $this->assertTrue($first->fresh()->next_follow_up_at->isFuture());

        $this->actingAs($volunteer)
            ->withSession(['current_organization_id' => $org->id])
            ->get(route('dialer.queue'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dialer/Queue')
                ->where('donor.id', $second->id)
            );
    }
}
