<?php

namespace Tests\Feature\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Mail\DonorOutreachMail;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_messaging_settings_and_templates(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->put(route('messaging.settings.update'), [
            'email_enabled' => true,
            'whatsapp_enabled' => true,
            'sms_enabled' => true,
            'from_email' => 'hope@example.com',
            'from_name' => 'Hope Foundation',
        ])->assertRedirect();

        $this->post(route('messaging.templates.store'), [
            'name' => 'Follow-up email',
            'channel' => MessageChannel::Email->value,
            'subject' => 'Hello {{name}}',
            'body' => 'Thanks from {{org}}',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('message_templates', [
            'organization_id' => $org->id,
            'name' => 'Follow-up email',
            'channel' => 'email',
        ]);
    }

    public function test_volunteer_can_send_email_and_sms_to_donor(): void
    {
        Mail::fake();

        $org = Organization::factory()->create(['name' => 'Hope Foundation']);
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Anita Mehta',
            'email' => 'anita@example.com',
            'phone' => '+919811111111',
        ]);

        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'assigned_by' => $volunteer->id,
            'is_active' => true,
        ]);

        $template = MessageTemplate::create([
            'organization_id' => $org->id,
            'name' => 'Thanks',
            'channel' => MessageChannel::Email,
            'subject' => 'Hi {{name}}',
            'body' => 'Warm regards from {{org}} via {{volunteer}}',
            'is_active' => true,
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->post(route('donors.messages.send', $donor), [
            'channel' => 'email',
            'message_template_id' => $template->id,
            'subject' => 'Hi {{name}}',
            'body' => 'Warm regards from {{org}} via {{volunteer}}',
        ])->assertRedirect();

        Mail::assertSent(DonorOutreachMail::class);

        $email = OutboundMessage::query()->where('channel', 'email')->first();
        $this->assertNotNull($email);
        $this->assertSame(MessageStatus::Sent, $email->status);
        $this->assertStringContainsString('Anita Mehta', (string) $email->subject);
        $this->assertStringContainsString('Hope Foundation', $email->body);

        $this->post(route('donors.messages.send', $donor), [
            'channel' => 'sms',
            'body' => 'Short reminder for {{name}}',
        ])->assertRedirect();

        $sms = OutboundMessage::query()->where('channel', 'sms')->first();
        $this->assertNotNull($sms);
        $this->assertSame(MessageStatus::Logged, $sms->status);
        $this->assertStringContainsString('Anita Mehta', $sms->body);
    }

    public function test_notification_mark_read_redirects_to_action_url(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->volunteer()->create();
        $user->organizations()->attach($org->id, ['is_active' => true]);

        $target = route('transfers.index', ['status' => 'pending']);

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\DonorTransferNotification',
            'data' => [
                'title' => 'Donor transfer requested',
                'body' => 'Test',
                'url' => $target,
            ],
        ]);

        $this->actingAs($user);
        OrganizationContext::set($org->id);

        $this->post(route('notifications.read', $notification->id))
            ->assertRedirect($target);

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
