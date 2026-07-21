<?php

namespace Tests\Feature\Calling;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextDonorOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_donor_prefers_due_follow_up_then_oldest_contact(): void
    {
        $org = Organization::factory()->create();
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $cold = Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Cold Donor',
            'next_follow_up_at' => null,
            'last_contacted_at' => now()->subMonths(6),
            'do_not_call' => false,
        ]);

        $recent = Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Recent Donor',
            'next_follow_up_at' => null,
            'last_contacted_at' => now()->subDay(),
            'do_not_call' => false,
        ]);

        $due = Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Due Follow Up',
            'next_follow_up_at' => now()->subHour(),
            'last_contacted_at' => now()->subDays(2),
            'do_not_call' => false,
        ]);

        foreach ([$cold, $recent, $due] as $donor) {
            DonorAssignment::create([
                'organization_id' => $org->id,
                'donor_id' => $donor->id,
                'volunteer_id' => $volunteer->id,
                'assigned_by' => $volunteer->id,
                'is_active' => true,
            ]);
        }

        $ordered = Donor::query()
            ->forOrganization($org->id)
            ->assignedTo($volunteer->id)
            ->callable()
            ->orderForNextCall()
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Due Follow Up', 'Cold Donor', 'Recent Donor'], $ordered);
    }
}
