<?php

namespace Tests\Feature\Commissions;

use App\Enums\AttributionStatus;
use App\Enums\CallOutcome;
use App\Enums\CommissionCycleStatus;
use App\Mail\OrgReportMail;
use App\Models\CommissionSetting;
use App\Models\Donation;
use App\Models\DonationAttribution;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;
use App\Models\ReportRecipient;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\Reports\ReportMailService;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommissionAttributionAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attribute_donation_queues_pending_attributions(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $volunteer = User::factory()->volunteer()->create();
        foreach ([$admin, $volunteer] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $donor = Donor::factory()->create(['organization_id' => $org->id]);
        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
        ]);

        $donation = Donation::factory()->create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'amount' => 2000,
            'donated_at' => now()->subDays(2),
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->post(route('donors.log-call', $donor), [
            'outcome' => CallOutcome::Donated->value,
            'attribute_donation' => true,
            'notes' => 'Linked donation',
        ])->assertRedirect();

        $this->assertDatabaseHas('donation_attributions', [
            'donation_id' => $donation->id,
            'volunteer_id' => $volunteer->id,
            'status' => AttributionStatus::Pending->value,
        ]);
    }

    public function test_approve_calculate_cycle_and_volunteer_earnings(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $volunteer = User::factory()->volunteer()->create();
        foreach ([$admin, $volunteer] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        CommissionSetting::create([
            'organization_id' => $org->id,
            'individual_enabled' => true,
            'individual_default_percent' => 10,
            'shared_enabled' => false,
            'shared_percent' => 0,
        ]);

        $donor = Donor::factory()->create(['organization_id' => $org->id]);
        $donation = Donation::factory()->create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'amount' => 1000,
            'donated_at' => now()->startOfMonth()->addDay(),
        ]);

        $attribution = DonationAttribution::create([
            'organization_id' => $org->id,
            'donation_id' => $donation->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'status' => AttributionStatus::Pending,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('attributions.approve', $attribution))->assertRedirect();
        $this->assertSame(AttributionStatus::Approved, $attribution->fresh()->status);

        $period = now()->format('Y-m');
        $this->post(route('commissions.cycles.calculate'), ['period' => $period])
            ->assertRedirect();

        $this->assertDatabaseHas('commission_cycles', [
            'organization_id' => $org->id,
            'period' => $period,
            'status' => CommissionCycleStatus::Draft->value,
            'verified_donation_total' => 1000,
            'payable_total' => 100,
        ]);

        $this->assertDatabaseHas('commission_line_items', [
            'volunteer_id' => $volunteer->id,
            'final_payable' => 100,
        ]);

        $this->actingAs($volunteer);
        $this->get(route('commissions.mine'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Commissions/Mine')
                ->where('totals.payable', 100)
            );
    }

    public function test_email_report_recipient_schedule_and_send_due(): void
    {
        Mail::fake();

        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('email-reports.recipients.store'), [
            'name' => 'Director',
            'email' => 'director@example.com',
            'role_label' => 'Board',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('report_recipients', [
            'organization_id' => $org->id,
            'email' => 'director@example.com',
        ]);

        $hour = (int) now('Asia/Kolkata')->format('H');
        $sendAt = sprintf('%02d:00', $hour);

        // Force weekly due: Monday in Asia/Kolkata at current hour
        $monday = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->setTime($hour, 15, 0);
        if ($monday->isFuture() && ! $monday->isSameDay(Carbon::now('Asia/Kolkata'))) {
            $monday = Carbon::now('Asia/Kolkata')->previous(Carbon::MONDAY)->setTime($hour, 15, 0);
        }
        if ($monday->dayOfWeek !== Carbon::MONDAY) {
            $monday = Carbon::now('Asia/Kolkata')->startOfWeek(Carbon::MONDAY)->setTime($hour, 15, 0);
        }

        $schedule = ReportSchedule::create([
            'organization_id' => $org->id,
            'type' => 'weekly_stats',
            'frequency' => 'weekly',
            'send_at' => $sendAt.':00',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $service = app(ReportMailService::class);
        $this->assertTrue($service->isDue($schedule, $monday));

        Carbon::setTestNow($monday);
        $this->artisan('reports:send-due')->assertSuccessful();
        Mail::assertSent(OrgReportMail::class);
        $this->assertNotNull($schedule->fresh()->last_sent_at);
        Carbon::setTestNow();
    }

    public function test_transfer_notification_includes_url(): void
    {
        $org = Organization::factory()->create();
        $from = User::factory()->volunteer()->create();
        $to = User::factory()->volunteer()->create();
        foreach ([$from, $to] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $donor = Donor::factory()->create(['organization_id' => $org->id]);
        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $from->id,
            'assigned_by' => $from->id,
            'is_active' => true,
        ]);

        $this->actingAs($from);
        OrganizationContext::set($org->id);

        $this->post(route('transfers.store', $donor), [
            'to_volunteer_id' => $to->id,
            'reason' => 'Language match',
        ])->assertRedirect();

        $notification = $to->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertNotEmpty($notification->data['url'] ?? null);
        $this->assertStringContainsString('transfers', $notification->data['url']);
    }
}
