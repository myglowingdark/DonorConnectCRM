<?php

namespace Tests\Feature\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\MetaTemplateStatus;
use App\Enums\UserRole;
use App\Mail\DonorOutreachMail;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMessagingSetting;
use App\Models\OutboundMessage;
use App\Models\PlatformMessagingSetting;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_messaging_settings_and_templates(): void
    {
        $org = Organization::factory()->create([
            'feature_overrides' => ['whatsapp' => true],
        ]);
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
            'whatsapp_use_platform' => true,
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

    public function test_whatsapp_feature_off_blocks_send(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'phone' => '+919811111111',
        ]);

        $template = MessageTemplate::create([
            'organization_id' => $org->id,
            'name' => 'Thanks WA',
            'channel' => MessageChannel::WhatsApp,
            'body' => 'Hello {{name}}',
            'is_active' => true,
            'meta_name' => 'thanks_wa',
            'meta_language' => 'en',
            'meta_category' => 'UTILITY',
            'meta_status' => MetaTemplateStatus::Approved,
        ]);

        OrganizationMessagingSetting::query()->create([
            'organization_id' => $org->id,
            'whatsapp_enabled' => true,
            'whatsapp_use_platform' => true,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('donors.messages.send', $donor), [
            'channel' => 'whatsapp',
            'message_template_id' => $template->id,
        ])->assertSessionHasErrors();
    }

    public function test_org_admin_can_create_whatsapp_template_but_team_lead_cannot(): void
    {
        $org = Organization::factory()->create([
            'feature_overrides' => ['whatsapp' => true],
        ]);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $lead = User::factory()->create(['role' => UserRole::TeamLead]);
        $lead->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('messaging.templates.store'), [
            'name' => 'Donor thanks',
            'channel' => 'whatsapp',
            'body' => 'Hello {{name}} from {{org}}',
            'meta_name' => 'donor_thanks',
            'meta_language' => 'en',
            'meta_category' => 'UTILITY',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('message_templates', [
            'organization_id' => $org->id,
            'meta_name' => 'donor_thanks',
            'meta_status' => 'draft',
        ]);

        $this->actingAs($lead);
        OrganizationContext::set($org->id);

        $this->post(route('messaging.templates.store'), [
            'name' => 'Lead template',
            'channel' => 'whatsapp',
            'body' => 'Hello',
            'meta_name' => 'lead_template',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_staff_can_send_approved_whatsapp_via_platform_credentials(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test123']],
            ], 200),
        ]);

        $org = Organization::factory()->create([
            'feature_overrides' => ['whatsapp' => true],
            'whatsapp_monthly_limit' => 100,
        ]);

        PlatformMessagingSetting::current()->update([
            'whatsapp_enabled' => true,
            'meta_access_token' => 'platform-token',
            'meta_phone_number_id' => 'phone-1',
            'meta_waba_id' => 'waba-1',
            'meta_api_version' => 'v21.0',
        ]);

        OrganizationMessagingSetting::query()->create([
            'organization_id' => $org->id,
            'whatsapp_enabled' => true,
            'whatsapp_use_platform' => true,
        ]);

        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Anita Mehta',
            'phone' => '9811111111',
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
            'name' => 'Thanks WA',
            'channel' => MessageChannel::WhatsApp,
            'body' => 'Hello {{name}} from {{org}}',
            'is_active' => true,
            'meta_name' => 'thanks_wa',
            'meta_language' => 'en',
            'meta_category' => 'UTILITY',
            'meta_status' => MetaTemplateStatus::Approved,
            'variable_schema' => ['name', 'org'],
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->post(route('donors.messages.send', $donor), [
            'channel' => 'whatsapp',
            'message_template_id' => $template->id,
        ])->assertRedirect();

        $message = OutboundMessage::query()->where('channel', 'whatsapp')->first();
        $this->assertNotNull($message);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame('wamid.test123', $message->provider_message_id);
        $this->assertSame('+919811111111', $message->recipient);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/phone-1/messages')
                && $request['template']['name'] === 'thanks_wa';
        });
    }

    public function test_org_override_credentials_preferred_over_platform(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/org-phone/messages' => Http::response([
                'messages' => [['id' => 'wamid.org']],
            ], 200),
        ]);

        $org = Organization::factory()->create([
            'feature_overrides' => ['whatsapp' => true],
        ]);

        PlatformMessagingSetting::current()->update([
            'whatsapp_enabled' => true,
            'meta_access_token' => 'platform-token',
            'meta_phone_number_id' => 'platform-phone',
            'meta_waba_id' => 'platform-waba',
        ]);

        OrganizationMessagingSetting::query()->create([
            'organization_id' => $org->id,
            'whatsapp_enabled' => true,
            'whatsapp_use_platform' => false,
            'whatsapp_api_key' => 'org-token',
            'whatsapp_phone_number_id' => 'org-phone',
            'whatsapp_waba_id' => 'org-waba',
        ]);

        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'phone' => '+919822222222',
        ]);

        $template = MessageTemplate::create([
            'organization_id' => $org->id,
            'name' => 'Org WA',
            'channel' => MessageChannel::WhatsApp,
            'body' => 'Hi {{name}}',
            'is_active' => true,
            'meta_name' => 'org_wa',
            'meta_language' => 'en',
            'meta_status' => MetaTemplateStatus::Approved,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('donors.messages.send', $donor), [
            'channel' => 'whatsapp',
            'message_template_id' => $template->id,
        ])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/org-phone/messages'));
    }

    public function test_submit_template_to_meta_and_sync_status(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/waba-1/message_templates*' => Http::sequence()
                ->push(['id' => 'tmpl-1', 'status' => 'PENDING'], 200)
                ->push([
                    'data' => [[
                        'id' => 'tmpl-1',
                        'name' => 'donor_thanks',
                        'language' => 'en',
                        'status' => 'APPROVED',
                    ]],
                ], 200),
        ]);

        $org = Organization::factory()->create([
            'feature_overrides' => ['whatsapp' => true],
        ]);

        PlatformMessagingSetting::current()->update([
            'whatsapp_enabled' => true,
            'meta_access_token' => 'platform-token',
            'meta_phone_number_id' => 'phone-1',
            'meta_waba_id' => 'waba-1',
        ]);

        OrganizationMessagingSetting::query()->create([
            'organization_id' => $org->id,
            'whatsapp_enabled' => true,
            'whatsapp_use_platform' => true,
        ]);

        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $template = MessageTemplate::create([
            'organization_id' => $org->id,
            'name' => 'Donor thanks',
            'channel' => MessageChannel::WhatsApp,
            'body' => 'Hello {{name}}',
            'is_active' => true,
            'meta_name' => 'donor_thanks',
            'meta_language' => 'en',
            'meta_category' => 'UTILITY',
            'meta_status' => MetaTemplateStatus::Draft,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('messaging.templates.submit-meta', $template))
            ->assertRedirect();

        $this->assertSame(MetaTemplateStatus::Pending, $template->fresh()->meta_status);
        $this->assertSame('tmpl-1', $template->fresh()->meta_template_id);

        $this->post(route('messaging.templates.sync-meta-pending'))
            ->assertRedirect();

        $this->assertSame(MetaTemplateStatus::Approved, $template->fresh()->meta_status);
    }

    public function test_team_lead_cannot_submit_meta_template(): void
    {
        $org = Organization::factory()->create([
            'feature_overrides' => ['whatsapp' => true],
        ]);
        $lead = User::factory()->create(['role' => UserRole::TeamLead]);
        $lead->organizations()->attach($org->id, ['is_active' => true]);

        $template = MessageTemplate::create([
            'organization_id' => $org->id,
            'name' => 'Donor thanks',
            'channel' => MessageChannel::WhatsApp,
            'body' => 'Hello',
            'is_active' => true,
            'meta_name' => 'donor_thanks',
            'meta_status' => MetaTemplateStatus::Draft,
        ]);

        $this->actingAs($lead);
        OrganizationContext::set($org->id);

        $this->post(route('messaging.templates.submit-meta', $template))
            ->assertForbidden();
    }

    public function test_webhook_updates_outbound_message_status(): void
    {
        PlatformMessagingSetting::current()->update([
            'whatsapp_enabled' => true,
            'meta_access_token' => 'token',
            'meta_app_secret' => 'secret',
            'meta_phone_number_id' => 'phone',
            'meta_waba_id' => 'waba',
        ]);

        $org = Organization::factory()->create();
        $donor = Donor::factory()->create(['organization_id' => $org->id]);

        $message = OutboundMessage::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'channel' => MessageChannel::WhatsApp,
            'recipient' => '+919811111111',
            'body' => 'Hello',
            'status' => MessageStatus::Sent,
            'provider_message_id' => 'wamid.abc',
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.abc',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'secret');

        $this->call(
            'POST',
            route('webhooks.meta.whatsapp'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Hub-Signature-256' => $signature,
            ],
            $body,
        )->assertOk();

        $this->assertSame(MessageStatus::Delivered, $message->fresh()->status);
    }

    public function test_whatsapp_monthly_limit_enforced(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x']]], 200),
        ]);

        $org = Organization::factory()->create([
            'feature_overrides' => ['whatsapp' => true],
            'whatsapp_monthly_limit' => 1,
        ]);

        PlatformMessagingSetting::current()->update([
            'whatsapp_enabled' => true,
            'meta_access_token' => 'token',
            'meta_phone_number_id' => 'phone-1',
            'meta_waba_id' => 'waba-1',
        ]);

        OrganizationMessagingSetting::query()->create([
            'organization_id' => $org->id,
            'whatsapp_enabled' => true,
            'whatsapp_use_platform' => true,
        ]);

        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'phone' => '+919833333333',
        ]);

        $template = MessageTemplate::create([
            'organization_id' => $org->id,
            'name' => 'Limit WA',
            'channel' => MessageChannel::WhatsApp,
            'body' => 'Hi',
            'is_active' => true,
            'meta_name' => 'limit_wa',
            'meta_language' => 'en',
            'meta_status' => MetaTemplateStatus::Approved,
        ]);

        OutboundMessage::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'channel' => MessageChannel::WhatsApp,
            'recipient' => '+919833333333',
            'body' => 'Prior',
            'status' => MessageStatus::Sent,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('donors.messages.send', $donor), [
            'channel' => 'whatsapp',
            'message_template_id' => $template->id,
        ])->assertSessionHasErrors('whatsapp_limit');
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
