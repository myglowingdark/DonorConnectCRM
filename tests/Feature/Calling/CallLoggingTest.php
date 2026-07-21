<?php

namespace Tests\Feature\Calling;

use App\Enums\CallOutcome;
use App\Enums\DonorStatus;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_can_log_call_and_updates_donor(): void
    {
        $org = Organization::factory()->create();
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'do_not_call' => false,
            'donor_status' => DonorStatus::New,
        ]);

        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'assigned_by' => $volunteer->id,
            'is_active' => true,
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $followUp = now()->addDay()->format('Y-m-d H:i:s');

        $this->post(route('donors.log-call', $donor), [
            'outcome' => CallOutcome::Interested->value,
            'notes' => 'Promising conversation',
            'follow_up_at' => $followUp,
        ])->assertRedirect();

        $donor->refresh();
        $this->assertNotNull($donor->last_contacted_at);
        $this->assertEquals(DonorStatus::Interested, $donor->donor_status);
        $this->assertDatabaseHas('donor_interactions', [
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'outcome' => CallOutcome::Interested->value,
        ]);
    }

    public function test_do_not_call_blocks_new_call_logs(): void
    {
        $org = Organization::factory()->create();
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'do_not_call' => true,
            'donor_status' => DonorStatus::DoNotCall,
        ]);

        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'assigned_by' => $volunteer->id,
            'is_active' => true,
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->post(route('donors.log-call', $donor), [
            'outcome' => CallOutcome::Interested->value,
            'notes' => 'Should fail',
        ])->assertRedirect();

        $this->assertEquals(0, DonorInteraction::query()->where('donor_id', $donor->id)->count());
    }
}
