<?php

namespace Tests\Feature\Calling;

use App\Enums\CallOutcome;
use App\Enums\TransferStatus;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\DonorTransferRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DonorTransferNotification;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DonorTransferAndLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_can_set_languages_and_donor_preferred_language_on_call(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create(['languages' => ['en']]);
        $volunteer = User::factory()->volunteer()->create(['languages' => ['hi', 'en']]);
        $admin->organizations()->attach($org->id, ['is_active' => true]);
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->put(route('users.update', $volunteer), [
            'name' => $volunteer->name,
            'email' => $volunteer->email,
            'phone' => $volunteer->phone,
            'role' => 'volunteer',
            'organization_ids' => [$org->id],
            'languages' => ['ta', 'en'],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertEquals(['ta', 'en'], $volunteer->fresh()->languages);

        $donor = Donor::factory()->create(['organization_id' => $org->id]);
        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->post(route('donors.log-call', $donor), [
            'outcome' => CallOutcome::Interested->value,
            'preferred_language' => 'hi',
            'notes' => 'Prefers Hindi',
        ])->assertRedirect();

        $this->assertSame('hi', $donor->fresh()->preferred_language);
    }

    public function test_transfer_requires_acceptance_notifies_admin_and_marks_transferred(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $from = User::factory()->volunteer()->create();
        $to = User::factory()->volunteer()->create();

        foreach ([$admin, $from, $to] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'was_transferred' => false,
        ]);

        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $from->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($from);
        OrganizationContext::set($org->id);

        $this->post(route('transfers.store', $donor), [
            'to_volunteer_id' => $to->id,
            'reason' => 'Better language match',
        ])->assertRedirect();

        $transfer = DonorTransferRequest::query()->first();
        $this->assertNotNull($transfer);
        $this->assertSame(TransferStatus::Pending, $transfer->status);

        Notification::assertSentTo($to, DonorTransferNotification::class);
        Notification::assertSentTo($admin, DonorTransferNotification::class);

        $this->assertTrue(
            DonorAssignment::query()
                ->where('donor_id', $donor->id)
                ->where('volunteer_id', $from->id)
                ->where('is_active', true)
                ->exists()
        );

        $this->actingAs($to);
        OrganizationContext::set($org->id);

        $this->post(route('transfers.accept', $transfer))->assertRedirect();

        $this->assertSame(TransferStatus::Accepted, $transfer->fresh()->status);
        $this->assertTrue($donor->fresh()->was_transferred);
        $this->assertTrue(
            DonorAssignment::query()
                ->where('donor_id', $donor->id)
                ->where('volunteer_id', $to->id)
                ->where('is_active', true)
                ->exists()
        );
        $this->assertFalse(
            DonorAssignment::query()
                ->where('donor_id', $donor->id)
                ->where('volunteer_id', $from->id)
                ->where('is_active', true)
                ->exists()
        );
    }
}
