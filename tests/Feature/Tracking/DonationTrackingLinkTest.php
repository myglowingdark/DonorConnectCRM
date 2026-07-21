<?php

namespace Tests\Feature\Tracking;

use App\Enums\AttributionSource;
use App\Enums\AttributionStatus;
use App\Enums\MessageChannel;
use App\Enums\TrackingEventType;
use App\Enums\TransferStatus;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\DonorTransferRequest;
use App\Models\Organization;
use App\Models\OrganizationMessagingSetting;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\Tracking\TrackingLinkService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DonationTrackingLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_reuses_same_link_per_donor_and_open_redirects_with_dcr(): void
    {
        Mail::fake();

        [$org, $volunteer, $donor] = $this->setupAssignedVolunteer();

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $target = 'https://hfsfoundation.org/project/blood-donation-camp/';

        $this->post(route('donors.tracking-links.store', $donor), [
            'target_url' => $target,
            'channel' => 'copy',
        ])->assertRedirect();

        $link = TrackingLink::query()->first();
        $this->assertNotNull($link);
        $this->assertSame($volunteer->id, $link->volunteer_id);
        $this->assertDatabaseHas('tracking_events', [
            'tracking_link_id' => $link->id,
            'event_type' => TrackingEventType::Sent->value,
        ]);

        $code = $link->code;

        $this->post(route('donors.tracking-links.store', $donor), [
            'target_url' => 'https://hfsfoundation.org/project/another-cause/',
            'channel' => 'copy',
        ])->assertRedirect();

        $this->assertSame(1, TrackingLink::query()->count());
        $link->refresh();
        $this->assertSame($code, $link->code);
        $this->assertSame('https://hfsfoundation.org/project/another-cause/', $link->target_url);

        $response = $this->get(route('tracking.redirect', $code));
        $response->assertRedirect('https://hfsfoundation.org/project/another-cause/?dcr='.$code);

        $link->refresh();
        $this->assertSame(1, $link->open_count);
        $this->assertNotNull($link->last_opened_at);
        $this->assertDatabaseHas('tracking_events', [
            'tracking_link_id' => $link->id,
            'event_type' => TrackingEventType::Opened->value,
        ]);
    }

    public function test_email_send_injects_donation_link_and_marks_sent(): void
    {
        Mail::fake();

        [$org, $volunteer, $donor] = $this->setupAssignedVolunteer();
        OrganizationMessagingSetting::query()->firstOrCreate(
            ['organization_id' => $org->id],
            ['email_enabled' => true, 'whatsapp_enabled' => true, 'sms_enabled' => true]
        );

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->post(route('donors.tracking-links.store', $donor), [
            'target_url' => 'https://hfsfoundation.org/project/blood-donation-camp/',
            'channel' => 'email',
            'subject' => 'Please donate',
            'body' => 'Hello {{name}}, donate here {{donation_link}}',
        ])->assertRedirect();

        $link = TrackingLink::query()->firstOrFail();
        $this->assertSame(MessageChannel::Email->value, $link->channel);
        $this->assertNotNull($link->outbound_message_id);

        Mail::assertSent(\App\Mail\DonorOutreachMail::class, function ($mail) use ($link) {
            return str_contains($mail->bodyText, $link->publicUrl());
        });
    }

    public function test_donation_with_dcr_auto_approves_within_window_and_respects_last_touch(): void
    {
        Notification::fake();

        [$org, $volunteerA, $donor] = $this->setupAssignedVolunteer();
        $org->update(['attribution_window_days' => 3]);

        $volunteerB = User::factory()->volunteer()->create();
        $volunteerB->organizations()->attach($org->id, ['is_active' => true]);

        $service = app(TrackingLinkService::class);

        $linkA = $service->resolveOrCreate(
            $donor,
            $volunteerA,
            'https://hfsfoundation.org/project/blood-donation-camp/',
            'email'
        );
        $service->markSent($linkA, channel: 'email');
        $service->recordOpen($linkA);

        $linkB = $service->resolveOrCreate(
            $donor,
            $volunteerB,
            'https://hfsfoundation.org/project/blood-donation-camp/',
            'email'
        );
        $service->markSent($linkB, channel: 'email');
        $service->recordOpen($linkB);

        $donation = Donation::factory()->create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'amount' => 1500,
            'donated_at' => now(),
            'source_data' => [
                'utm_source' => 'donorconnect',
                'utm_content' => $linkB->code,
                'dcr' => $linkB->code,
            ],
        ]);

        $attribution = $service->attributeDonationIfTracked($donation);

        $this->assertNotNull($attribution);
        $this->assertSame($volunteerB->id, $attribution->volunteer_id);
        $this->assertSame(AttributionStatus::Approved, $attribution->status);
        $this->assertSame(AttributionSource::TrackingLink, $attribution->source);
        $this->assertDatabaseHas('tracking_events', [
            'tracking_link_id' => $linkB->id,
            'event_type' => TrackingEventType::Paid->value,
        ]);
    }

    public function test_expired_open_window_does_not_auto_attribute(): void
    {
        [$org, $volunteer, $donor] = $this->setupAssignedVolunteer();
        $org->update(['attribution_window_days' => 3]);

        $service = app(TrackingLinkService::class);
        $link = $service->resolveOrCreate(
            $donor,
            $volunteer,
            'https://hfsfoundation.org/project/blood-donation-camp/'
        );
        $service->recordOpen($link);
        $link->update(['last_opened_at' => now()->subDays(5)]);

        $donation = Donation::factory()->create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'amount' => 500,
            'source_data' => ['dcr' => $link->code],
        ]);

        $this->assertNull($service->attributeDonationIfTracked($donation));
        $this->assertDatabaseCount('donation_attributions', 0);
    }

    public function test_tracking_visibility_owner_admin_and_transfer_recipient(): void
    {
        [$org, $owner, $donor] = $this->setupAssignedVolunteer();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $other = User::factory()->volunteer()->create();
        $other->organizations()->attach($org->id, ['is_active' => true]);

        $recipient = User::factory()->volunteer()->create();
        $recipient->organizations()->attach($org->id, ['is_active' => true]);

        $service = app(TrackingLinkService::class);
        $link = $service->resolveOrCreate(
            $donor,
            $owner,
            'https://hfsfoundation.org/project/blood-donation-camp/'
        );
        $service->markSent($link);

        $this->assertCount(1, $service->visibleLinksFor($donor, $owner));
        $this->assertCount(1, $service->visibleLinksFor($donor, $admin));
        $this->assertCount(0, $service->visibleLinksFor($donor, $other));

        DonorTransferRequest::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'from_volunteer_id' => $owner->id,
            'to_volunteer_id' => $recipient->id,
            'requested_by' => $owner->id,
            'status' => TransferStatus::Accepted,
            'responded_by' => $recipient->id,
            'responded_at' => now(),
        ]);

        $this->assertCount(1, $service->visibleLinksFor($donor, $recipient));
    }

    public function test_super_admin_can_set_attribution_window_days(): void
    {
        $org = Organization::factory()->create(['attribution_window_days' => 3]);
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->put(route('organizations.update', $org), [
                'name' => $org->name,
                'slug' => $org->slug,
                'brand_color' => $org->brand_color ?? '#1e3a8a',
                'timezone' => $org->timezone ?? 'Asia/Kolkata',
                'currency' => $org->currency ?? 'INR',
                'is_active' => true,
                'attribution_window_days' => 7,
            ])
            ->assertRedirect();

        $this->assertSame(7, $org->fresh()->attribution_window_days);
    }

    public function test_page_view_beacon_records_event(): void
    {
        [$org, $volunteer, $donor] = $this->setupAssignedVolunteer();
        $service = app(TrackingLinkService::class);
        $link = $service->resolveOrCreate(
            $donor,
            $volunteer,
            'https://hfsfoundation.org/project/blood-donation-camp/'
        );

        $this->postJson(route('tracking.events'), [
            'dcr' => $link->code,
            'event_type' => 'page_view',
            'page_url' => 'https://hfsfoundation.org/project/blood-donation-camp/',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('tracking_events', [
            'tracking_link_id' => $link->id,
            'event_type' => TrackingEventType::PageView->value,
        ]);
    }

    /**
     * @return array{0: Organization, 1: User, 2: Donor}
     */
    protected function setupAssignedVolunteer(): array
    {
        $org = Organization::factory()->create(['attribution_window_days' => 3]);
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'email' => 'donor@example.com',
            'phone' => '+919811122233',
        ]);

        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'assigned_by' => $volunteer->id,
            'is_active' => true,
        ]);

        return [$org, $volunteer, $donor];
    }
}
