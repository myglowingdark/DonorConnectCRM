<?php

namespace Tests\Feature\Commissions;

use App\Enums\AttributionStatus;
use App\Enums\CommissionCycleStatus;
use App\Models\CommissionSetting;
use App\Models\Donation;
use App\Models\DonationAttribution;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DonorAssignedNotification;
use App\Notifications\DonorHandoverNotification;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InternalTelecallersAndScopedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_mark_internal_telecaller_and_set_internal_rates(): void
    {
        $org = Organization::factory()->create();
        $super = User::factory()->superAdmin()->create();
        $internal = User::factory()->volunteer()->create([
            'is_internal_telecaller' => false,
        ]);
        $internal->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($super)
            ->put(route('users.update', $internal), [
                'name' => $internal->name,
                'email' => $internal->email,
                'phone' => $internal->phone,
                'role' => 'volunteer',
                'organization_ids' => [$org->id],
                'is_active' => true,
                'is_internal_telecaller' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($internal->fresh()->is_internal_telecaller);

        OrganizationContext::set($org->id);

        $this->put(route('commissions.settings.update'), [
            'individual_enabled' => true,
            'individual_default_percent' => 5,
            'shared_enabled' => false,
            'shared_percent' => 0,
            'internal_individual_enabled' => true,
            'internal_individual_default_percent' => 8,
            'internal_shared_enabled' => true,
            'internal_shared_percent' => 10,
            'volunteer_overrides' => [],
            'internal_volunteer_overrides' => [
                ['volunteer_id' => $internal->id, 'percent' => 12],
            ],
        ])->assertRedirect();

        $settings = CommissionSetting::query()->where('organization_id', $org->id)->first();
        $this->assertEquals(8, (float) $settings->internal_individual_default_percent);
        $this->assertEquals(12, $settings->rateForVolunteer($internal->id, true));
        $this->assertTrue($settings->internal_shared_enabled);
    }

    public function test_org_admin_cannot_change_internal_commission_rates(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        CommissionSetting::create([
            'organization_id' => $org->id,
            'individual_enabled' => true,
            'individual_default_percent' => 5,
            'internal_individual_default_percent' => 7,
            'internal_shared_percent' => 4,
            'internal_shared_enabled' => true,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->put(route('commissions.settings.update'), [
            'individual_enabled' => true,
            'individual_default_percent' => 6,
            'shared_enabled' => false,
            'shared_percent' => 0,
            'internal_individual_default_percent' => 99,
            'internal_shared_percent' => 99,
            'volunteer_overrides' => [],
        ])->assertRedirect();

        $settings = CommissionSetting::query()->where('organization_id', $org->id)->first();
        $this->assertEquals(6, (float) $settings->individual_default_percent);
        $this->assertEquals(7, (float) $settings->internal_individual_default_percent);
        $this->assertEquals(4, (float) $settings->internal_shared_percent);
    }

    public function test_cycle_keeps_internal_shared_pool_among_internal_only(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $orgVol = User::factory()->volunteer()->create(['is_internal_telecaller' => false]);
        $internalA = User::factory()->volunteer()->create(['is_internal_telecaller' => true]);
        $internalB = User::factory()->volunteer()->create(['is_internal_telecaller' => true]);

        foreach ([$admin, $orgVol, $internalA, $internalB] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        CommissionSetting::create([
            'organization_id' => $org->id,
            'individual_enabled' => true,
            'individual_default_percent' => 10,
            'shared_enabled' => false,
            'shared_percent' => 0,
            'internal_individual_enabled' => true,
            'internal_individual_default_percent' => 10,
            'internal_shared_enabled' => true,
            'internal_shared_percent' => 20,
        ]);

        $period = now()->format('Y-m');
        $make = function (User $volunteer, float $amount) use ($org) {
            $donor = Donor::factory()->create(['organization_id' => $org->id]);
            $donation = Donation::factory()->create([
                'organization_id' => $org->id,
                'donor_id' => $donor->id,
                'amount' => $amount,
                'donated_at' => now()->startOfMonth()->addDay(),
            ]);
            DonationAttribution::create([
                'organization_id' => $org->id,
                'donation_id' => $donation->id,
                'donor_id' => $donor->id,
                'volunteer_id' => $volunteer->id,
                'status' => AttributionStatus::Approved,
            ]);
        };

        $make($orgVol, 1000);
        $make($internalA, 1000);
        $make($internalB, 1000);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('commissions.cycles.calculate'), ['period' => $period])->assertRedirect();

        $this->assertDatabaseHas('commission_cycles', [
            'organization_id' => $org->id,
            'period' => $period,
            'status' => CommissionCycleStatus::Draft->value,
            'verified_donation_total' => 3000,
            // org: ₹100 individual; internal: ₹200 individual + ₹400 shared (20% of ₹2000) → payable ₹700
            'shared_pool' => 400,
            'payable_total' => 700,
        ]);

        $this->assertDatabaseHas('commission_line_items', [
            'volunteer_id' => $orgVol->id,
            'shared_allocation' => 0,
            'final_payable' => 100,
        ]);
        $this->assertDatabaseHas('commission_line_items', [
            'volunteer_id' => $internalA->id,
            'shared_allocation' => 200,
            'final_payable' => 300,
        ]);
        $this->assertDatabaseHas('commission_line_items', [
            'volunteer_id' => $internalB->id,
            'shared_allocation' => 200,
            'final_payable' => 300,
        ]);
    }

    public function test_assignment_notifies_only_assigned_volunteer(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $v1 = User::factory()->volunteer()->create();
        $v2 = User::factory()->volunteer()->create();
        foreach ([$admin, $v1, $v2] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $donor = Donor::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('assignments.store'), [
            'volunteer_id' => $v1->id,
            'donor_ids' => [$donor->id],
        ])->assertRedirect();

        Notification::assertSentTo($v1, DonorAssignedNotification::class);
        Notification::assertNotSentTo($v2, DonorAssignedNotification::class);
        Notification::assertNotSentTo($admin, DonorAssignedNotification::class);
    }

    public function test_handover_notifies_only_involved_volunteers(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $from = User::factory()->volunteer()->create();
        $to = User::factory()->volunteer()->create();
        $other = User::factory()->volunteer()->create();
        foreach ([$admin, $from, $to, $other] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $donor = Donor::factory()->create(['organization_id' => $org->id]);
        \App\Models\DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $from->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('handovers.store'), [
            'from_volunteer_id' => $from->id,
            'to_volunteer_ids' => [$to->id],
            'mode' => 'full',
        ])->assertRedirect();

        Notification::assertSentTo($from, DonorHandoverNotification::class);
        Notification::assertSentTo($to, DonorHandoverNotification::class);
        Notification::assertNotSentTo($other, DonorHandoverNotification::class);
        Notification::assertNotSentTo($admin, DonorHandoverNotification::class);
    }
}
