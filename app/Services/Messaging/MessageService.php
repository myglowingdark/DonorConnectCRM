<?php

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Mail\DonorOutreachMail;
use App\Models\Donor;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMessagingSetting;
use App\Models\OutboundMessage;
use App\Models\PlatformMessagingSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function settingsFor(int $organizationId): OrganizationMessagingSetting
    {
        return OrganizationMessagingSetting::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'email_enabled' => true,
                'whatsapp_enabled' => true,
                'sms_enabled' => true,
            ]
        );
    }

    /**
     * @param  array{
     *     channel: string,
     *     body: string,
     *     subject?: string|null,
     *     message_template_id?: int|null
     * }  $data
     */
    public function sendToDonor(Donor $donor, User $actor, array $data): OutboundMessage
    {
        $channel = MessageChannel::from($data['channel']);
        $settings = $this->settingsFor($donor->organization_id);
        $organization = Organization::query()->findOrFail($donor->organization_id);

        $this->assertChannelEnabled($settings, $channel);

        $template = null;
        if (! empty($data['message_template_id'])) {
            $template = MessageTemplate::query()
                ->forOrganization($donor->organization_id)
                ->whereKey($data['message_template_id'])
                ->where('channel', $channel)
                ->where('is_active', true)
                ->firstOrFail();
        }

        $subject = $data['subject'] ?? $template?->subject;
        $body = $data['body'] ?: ($template?->body ?? '');

        if (blank($body)) {
            throw ValidationException::withMessages([
                'body' => 'Message body is required.',
            ]);
        }

        $replacements = [
            '{{name}}' => $donor->full_name,
            '{{donor_name}}' => $donor->full_name,
            '{{org}}' => $organization->name,
            '{{organization}}' => $organization->name,
            '{{phone}}' => $donor->phone ?? '',
            '{{email}}' => $donor->email ?? '',
            '{{volunteer}}' => $actor->name,
        ];

        $subject = $subject ? strtr($subject, $replacements) : null;
        $body = strtr($body, $replacements);

        $recipient = match ($channel) {
            MessageChannel::Email => $donor->email,
            MessageChannel::WhatsApp, MessageChannel::Sms => $donor->phone,
        };

        if (blank($recipient)) {
            throw ValidationException::withMessages([
                'channel' => $channel === MessageChannel::Email
                    ? 'This donor has no email address.'
                    : 'This donor has no phone number.',
            ]);
        }

        $message = OutboundMessage::create([
            'organization_id' => $donor->organization_id,
            'donor_id' => $donor->id,
            'sent_by' => $actor->id,
            'message_template_id' => $template?->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => MessageStatus::Queued,
        ]);

        try {
            $result = match ($channel) {
                MessageChannel::Email => $this->dispatchEmail($settings, $organization, $message),
                MessageChannel::WhatsApp => $this->dispatchProviderMessage($settings, $message, 'whatsapp'),
                MessageChannel::Sms => $this->dispatchProviderMessage($settings, $message, 'sms'),
            };

            $message->update([
                'status' => $result['status'],
                'error_message' => $result['error'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $message->update([
                'status' => MessageStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'body' => 'Failed to send message: '.$e->getMessage(),
            ]);
        }

        $this->auditLogger->log(
            'donor.message_sent',
            $donor,
            null,
            [
                'outbound_message_id' => $message->id,
                'channel' => $channel->value,
                'status' => $message->status->value,
            ],
            $donor->organization_id,
            $actor,
        );

        return $message->fresh();
    }

    /**
     * @return array{status: MessageStatus, error?: string|null}
     */
    protected function dispatchEmail(
        OrganizationMessagingSetting $settings,
        Organization $organization,
        OutboundMessage $message,
    ): array {
        $platform = PlatformMessagingSetting::current();

        $fromEmail = $settings->from_email ?: $platform->from_email;
        $fromName = $settings->from_name ?: ($platform->from_name ?: $organization->name);

        $mailable = new DonorOutreachMail(
            subjectLine: $message->subject ?: ('Message from '.$organization->name),
            bodyText: $message->body,
            fromEmail: $fromEmail,
            fromName: $fromName,
        );

        // Fallback: org SMTP → platform SMTP → app default mailer (.env)
        if ($settings->usesCustomSmtp()) {
            $this->configureSmtpMailer('org_smtp', $settings);
            Mail::mailer('org_smtp')->to($message->recipient)->send($mailable);
        } elseif ($platform->email_enabled && $platform->usesCustomSmtp()) {
            $this->configureSmtpMailer('platform_smtp', $platform);
            Mail::mailer('platform_smtp')->to($message->recipient)->send($mailable);
        } else {
            Mail::to($message->recipient)->send($mailable);
        }

        return ['status' => MessageStatus::Sent];
    }

    /**
     * @param  OrganizationMessagingSetting|PlatformMessagingSetting  $smtp
     */
    protected function configureSmtpMailer(string $mailer, $smtp): void
    {
        Config::set("mail.mailers.{$mailer}", [
            'transport' => 'smtp',
            'host' => $smtp->smtp_host,
            'port' => $smtp->smtp_port ?: 587,
            'encryption' => $smtp->smtp_encryption ?: 'tls',
            'username' => $smtp->smtp_username,
            'password' => $smtp->smtp_password,
            'timeout' => null,
        ]);
    }

    /**
     * WhatsApp/SMS: if API key configured, log as sent-provider stub; otherwise mark logged.
     *
     * @return array{status: MessageStatus, error?: string|null}
     */
    protected function dispatchProviderMessage(
        OrganizationMessagingSetting $settings,
        OutboundMessage $message,
        string $kind,
    ): array {
        $apiKey = $kind === 'whatsapp' ? $settings->whatsapp_api_key : $settings->sms_api_key;
        $from = $kind === 'whatsapp' ? $settings->whatsapp_from_number : $settings->sms_from_number;

        if (blank($apiKey)) {
            Log::info('Outbound '.$kind.' message logged (provider not configured)', [
                'outbound_message_id' => $message->id,
                'recipient' => $message->recipient,
                'body' => $message->body,
            ]);

            return [
                'status' => MessageStatus::Logged,
                'error' => ucfirst($kind).' provider API key not configured — message saved for audit.',
            ];
        }

        // Provider integrations (Twilio / Meta / etc.) come next; record as sent stub when keys exist.
        Log::info('Outbound '.$kind.' dispatched via configured provider stub', [
            'outbound_message_id' => $message->id,
            'from' => $from,
            'recipient' => $message->recipient,
        ]);

        return ['status' => MessageStatus::Sent];
    }

    protected function assertChannelEnabled(OrganizationMessagingSetting $settings, MessageChannel $channel): void
    {
        $enabled = match ($channel) {
            MessageChannel::Email => $settings->email_enabled,
            MessageChannel::WhatsApp => $settings->whatsapp_enabled,
            MessageChannel::Sms => $settings->sms_enabled,
        };

        if (! $enabled) {
            throw ValidationException::withMessages([
                'channel' => $channel->label().' messaging is disabled for this organization.',
            ]);
        }
    }
}
