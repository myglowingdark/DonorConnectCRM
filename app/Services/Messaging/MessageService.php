<?php

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\MetaTemplateStatus;
use App\Mail\DonorOutreachMail;
use App\Models\Donor;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMessagingSetting;
use App\Models\OutboundMessage;
use App\Models\PlatformMessagingSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SaaS\EntitlementService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private EntitlementService $entitlements,
        private MetaWhatsAppCredentialResolver $credentials,
        private MetaWhatsAppClient $metaClient,
    ) {}

    public function settingsFor(int $organizationId): OrganizationMessagingSetting
    {
        return OrganizationMessagingSetting::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'email_enabled' => true,
                'whatsapp_enabled' => true,
                'sms_enabled' => true,
                'whatsapp_use_platform' => true,
            ]
        );
    }

    /**
     * @param  array{
     *     channel: string,
     *     body?: string|null,
     *     subject?: string|null,
     *     message_template_id?: int|null,
     *     target_url?: string|null,
     *     tracking_link?: \App\Models\TrackingLink|null
     * }  $data
     */
    public function sendToDonor(Donor $donor, User $actor, array $data): OutboundMessage
    {
        $channel = MessageChannel::from($data['channel']);
        $settings = $this->settingsFor($donor->organization_id);
        $organization = Organization::query()->findOrFail($donor->organization_id);

        $this->assertChannelEnabled($settings, $channel, $organization);

        $template = null;
        if (! empty($data['message_template_id'])) {
            $template = MessageTemplate::query()
                ->forOrganization($donor->organization_id)
                ->whereKey($data['message_template_id'])
                ->where('channel', $channel)
                ->where('is_active', true)
                ->firstOrFail();
        }

        if ($channel === MessageChannel::WhatsApp) {
            if (! $template || ! $template->isMetaApproved()) {
                throw ValidationException::withMessages([
                    'message_template_id' => 'WhatsApp messages require an approved Meta template.',
                ]);
            }

            $this->entitlements->assertCanSendWhatsApp($organization);
        }

        $subject = $data['subject'] ?? $template?->subject;
        $body = ($data['body'] ?? '') ?: ($template?->body ?? '');

        if ($channel !== MessageChannel::WhatsApp && blank($body)) {
            throw ValidationException::withMessages([
                'body' => 'Message body is required.',
            ]);
        }

        if ($channel === MessageChannel::WhatsApp) {
            $body = $template->body;
        }

        $trackingLink = $data['tracking_link'] ?? null;
        if (! $trackingLink && ! empty($data['target_url'])) {
            $trackingLink = app(\App\Services\Tracking\TrackingLinkService::class)
                ->resolveOrCreate($donor, $actor, (string) $data['target_url'], $channel->value);
        }

        $replacements = $this->replacementsFor($donor, $organization, $actor, $trackingLink);

        $subject = $subject ? strtr($subject, $replacements) : null;
        $body = strtr($body, $replacements);

        $recipient = match ($channel) {
            MessageChannel::Email => $donor->email,
            MessageChannel::WhatsApp, MessageChannel::Sms => $this->normalizePhone($donor->phone ?? ''),
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
                MessageChannel::Email => $this->dispatchEmail($settings, $organization, $message, $template),
                MessageChannel::WhatsApp => $this->dispatchWhatsApp($organization, $settings, $message, $template, $replacements),
                MessageChannel::Sms => $this->dispatchProviderMessage($settings, $message, 'sms'),
            };

            $message->update([
                'status' => $result['status'],
                'error_message' => $result['error'] ?? null,
                'provider_message_id' => $result['provider_message_id'] ?? null,
                'provider_payload' => $result['provider_payload'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (ValidationException $e) {
            $message->update([
                'status' => MessageStatus::Failed,
                'error_message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
            ]);

            throw $e;
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

    public function submitTemplateToMeta(MessageTemplate $template, Organization $organization, User $actor): MessageTemplate
    {
        if (! $template->isWhatsApp()) {
            throw ValidationException::withMessages([
                'channel' => 'Only WhatsApp templates can be submitted to Meta.',
            ]);
        }

        $this->entitlements->assertFeature($organization, 'whatsapp');

        $metaName = $template->meta_name ?: $this->slugifyMetaName($template->name);
        // Meta language codes are lowercase (en, en_US). "EN" returns Invalid parameter.
        $language = strtolower((string) ($template->meta_language ?: 'en'));
        $category = $template->meta_category ?: 'UTILITY';

        $bodyForMeta = $this->toMetaBodyText($template);
        $exampleValues = $this->exampleValuesForTemplate($template, $organization);

        $credentials = $this->credentials->resolveForOrganization($organization);

        $headerFormat = null;
        $headerHandle = null;
        if ($template->usesDocumentHeader()) {
            $absolutePath = $template->absoluteAttachmentPath();
            if (! $absolutePath || ! is_readable($absolutePath)) {
                throw ValidationException::withMessages([
                    'attachment' => 'WhatsApp templates with {{receipt}} (or a document header) require an uploaded sample PDF/DOC before submitting to Meta.',
                ]);
            }

            $headerFormat = 'document';
            $headerHandle = $this->metaClient->uploadResumableMedia(
                $credentials,
                $absolutePath,
                $template->attachment_mime ?: 'application/pdf',
                $template->attachment_filename ?: basename($absolutePath),
            );
        }

        $result = $this->metaClient->createMessageTemplate($credentials, [
            'name' => $metaName,
            'language' => $language,
            'category' => $category,
            'body' => $bodyForMeta,
            'example_body' => $exampleValues,
            'header_format' => $headerFormat,
            'header_handle' => $headerHandle,
        ]);

        $template->update([
            'meta_name' => $metaName,
            'meta_language' => $language,
            'meta_category' => strtoupper($category),
            'meta_template_id' => $result['id'],
            'meta_status' => MetaTemplateStatus::fromMetaApi($result['status'] ?? null, MetaTemplateStatus::Pending),
            'meta_rejection_reason' => null,
            'variable_schema' => $template->orderedVariableKeys(),
            'header_format' => $headerFormat ?? ($template->header_format ?: 'none'),
        ]);

        $this->auditLogger->log(
            'messaging.template_submitted_meta',
            $template,
            null,
            ['meta_template_id' => $result['id'], 'meta_name' => $metaName],
            $organization->id,
            $actor,
        );

        return $template->fresh();
    }

    public function syncTemplateFromMeta(MessageTemplate $template, Organization $organization): MessageTemplate
    {
        if (! $template->isWhatsApp() || blank($template->meta_name)) {
            throw ValidationException::withMessages([
                'template' => 'Template has not been submitted to Meta yet.',
            ]);
        }

        $credentials = $this->credentials->resolveForOrganization($organization);
        $remoteTemplates = $this->metaClient->getMessageTemplates($credentials, $template->meta_name);

        $match = collect($remoteTemplates)->first(function (array $row) use ($template) {
            $nameMatch = ($row['name'] ?? null) === $template->meta_name;
            $langMatch = ! $template->meta_language || ($row['language'] ?? null) === $template->meta_language;

            return $nameMatch && $langMatch;
        });

        if (! $match) {
            throw ValidationException::withMessages([
                'template' => 'Template was not found on the Meta WhatsApp Business Account.',
            ]);
        }

        $template->update([
            'meta_template_id' => isset($match['id']) ? (string) $match['id'] : $template->meta_template_id,
            'meta_status' => MetaTemplateStatus::fromMetaApi($match['status'] ?? null),
            'meta_rejection_reason' => $match['rejected_reason'] ?? data_get($match, 'quality_score.score'),
        ]);

        return $template->fresh();
    }

    /**
     * Best-effort sync for WhatsApp templates awaiting Meta review.
     *
     * @return int Number of templates refreshed
     */
    public function syncPendingWhatsAppTemplates(Organization $organization): int
    {
        $templates = MessageTemplate::query()
            ->forOrganization($organization->id)
            ->where('channel', MessageChannel::WhatsApp)
            ->whereNotNull('meta_name')
            ->where(function ($query) {
                $query->whereIn('meta_status', [
                    MetaTemplateStatus::Pending->value,
                    MetaTemplateStatus::Paused->value,
                ])->orWhere(function ($inner) {
                    $inner->whereNotNull('meta_template_id')
                        ->whereNotIn('meta_status', [
                            MetaTemplateStatus::Approved->value,
                            MetaTemplateStatus::Rejected->value,
                        ]);
                });
            })
            ->get();

        $synced = 0;

        foreach ($templates as $template) {
            try {
                $this->syncTemplateFromMeta($template, $organization);
                $synced++;
            } catch (\Throwable $e) {
                Log::info('WhatsApp template auto-sync skipped', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    public function applyMetaTemplateStatusWebhook(array $value): ?MessageTemplate
    {
        $name = $value['message_template_name'] ?? null;
        $language = $value['message_template_language'] ?? null;
        $event = $value['event'] ?? $value['message_template_status'] ?? null;
        $templateId = isset($value['message_template_id']) ? (string) $value['message_template_id'] : null;

        if (blank($name) && blank($templateId)) {
            return null;
        }

        $query = MessageTemplate::query()->where('channel', MessageChannel::WhatsApp);

        if (filled($templateId)) {
            $query->where(function ($q) use ($templateId, $name) {
                $q->where('meta_template_id', $templateId);
                if (filled($name)) {
                    $q->orWhere('meta_name', $name);
                }
            });
        } else {
            $query->where('meta_name', $name);
        }

        if (filled($language)) {
            $query->where(function ($q) use ($language) {
                $q->whereNull('meta_language')->orWhere('meta_language', $language);
            });
        }

        $template = $query->latest('id')->first();

        if (! $template) {
            return null;
        }

        $template->update([
            'meta_template_id' => $templateId ?: $template->meta_template_id,
            'meta_status' => MetaTemplateStatus::fromMetaApi(is_string($event) ? $event : null),
            'meta_rejection_reason' => $value['reason'] ?? $value['rejected_reason'] ?? $template->meta_rejection_reason,
        ]);

        return $template->fresh();
    }

    public function testConnection(Organization $organization): array
    {
        $credentials = $this->credentials->resolveForOrganization($organization);

        return $this->metaClient->getPhoneNumber($credentials);
    }

    /**
     * Send a test email using the same SMTP resolution as donor outreach.
     *
     * @return array{source: string, to: string, from: string}
     */
    public function sendOrgSmtpTestEmail(Organization $organization, string $toEmail): array
    {
        $settings = $this->settingsFor($organization->id);
        $platform = PlatformMessagingSetting::current();

        if ($settings->usesCustomSmtp()) {
            $this->configureSmtpMailer('org_smtp', $settings);
            $mailer = 'org_smtp';
            $fromEmail = (string) $settings->from_email;
            $fromName = $settings->from_name ?: $organization->name;
            $source = 'organization SMTP';
        } elseif ($platform->email_enabled && $platform->usesCustomSmtp()) {
            $this->configureSmtpMailer('platform_smtp', $platform);
            $mailer = 'platform_smtp';
            $fromEmail = (string) $platform->from_email;
            $fromName = $platform->from_name ?: $organization->name;
            $source = 'platform SMTP';
        } else {
            $mailer = null;
            $fromEmail = (string) (config('mail.from.address') ?: '');
            $fromName = (string) (config('mail.from.name') ?: config('app.name'));
            $source = 'default app mailer ('.config('mail.default').')';

            if (blank($fromEmail)) {
                throw ValidationException::withMessages([
                    'smtp' => 'No SMTP is configured. Set organization or platform SMTP (host + from email), or MAIL_FROM_ADDRESS in .env.',
                ]);
            }
        }

        $body = "This is a test email from DRM confirming outbound email works via {$source}.";

        $send = function ($message) use ($toEmail, $fromEmail, $fromName) {
            $message->to($toEmail)
                ->subject('DRM SMTP connection test')
                ->from($fromEmail, $fromName);
        };

        try {
            if ($mailer) {
                Mail::mailer($mailer)->raw($body, $send);
            } else {
                Mail::raw($body, $send);
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'smtp' => 'SMTP test failed: '.$e->getMessage(),
            ]);
        }

        return [
            'source' => $source,
            'to' => $toEmail,
            'from' => $fromEmail,
        ];
    }

    /**
     * @return array{source: string, to: string, from: string}
     */
    public function sendPlatformSmtpTestEmail(string $toEmail): array
    {
        $platform = PlatformMessagingSetting::current();

        if (! $platform->usesCustomSmtp()) {
            throw ValidationException::withMessages([
                'smtp' => 'Configure platform SMTP host and from email before testing.',
            ]);
        }

        $this->configureSmtpMailer('platform_smtp', $platform);
        $fromEmail = (string) $platform->from_email;
        $fromName = $platform->from_name ?: config('app.name');

        try {
            Mail::mailer('platform_smtp')->raw(
                'This is a test email from DRM confirming platform SMTP settings work.',
                function ($message) use ($toEmail, $fromEmail, $fromName) {
                    $message->to($toEmail)
                        ->subject('DRM platform SMTP connection test')
                        ->from($fromEmail, $fromName);
                },
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'smtp' => 'SMTP test failed: '.$e->getMessage(),
            ]);
        }

        return [
            'source' => 'platform SMTP',
            'to' => $toEmail,
            'from' => $fromEmail,
        ];
    }

    /**
     * @return array{status: MessageStatus, error?: string|null, provider_message_id?: string|null, provider_payload?: array<string, mixed>|null}
     */
    protected function dispatchWhatsApp(
        Organization $organization,
        OrganizationMessagingSetting $settings,
        OutboundMessage $message,
        MessageTemplate $template,
        array $replacements,
    ): array {
        $credentials = $this->credentials->resolveForOrganization($organization, $settings);

        $components = [];

        if ($template->usesDocumentHeader()) {
            $documentUrl = $template->attachment_url;
            if (! filled($documentUrl)) {
                throw ValidationException::withMessages([
                    'attachment' => 'This WhatsApp template requires a document attachment to send.',
                ]);
            }

            $components[] = [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'document',
                    'document' => [
                        'link' => $documentUrl,
                        'filename' => $template->attachment_filename ?: 'document.pdf',
                    ],
                ]],
            ];
        }

        $keys = $template->orderedVariableKeys();
        if ($keys !== []) {
            $parameters = [];
            foreach ($keys as $key) {
                $token = '{{'.$key.'}}';
                $parameters[] = [
                    'type' => 'text',
                    'text' => (string) ($replacements[$token] ?? ''),
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        $result = $this->metaClient->sendTemplateMessage(
            $credentials,
            $message->recipient,
            $template->meta_name ?: $this->slugifyMetaName($template->name),
            $template->meta_language ?: 'en',
            $components,
        );

        return [
            'status' => MessageStatus::Sent,
            'provider_message_id' => $result['message_id'],
            'provider_payload' => [
                'source' => $credentials->source,
                'response' => $result['raw'],
            ],
        ];
    }

    /**
     * @return array{status: MessageStatus, error?: string|null}
     */
    protected function dispatchEmail(
        OrganizationMessagingSetting $settings,
        Organization $organization,
        OutboundMessage $message,
        ?MessageTemplate $template = null,
    ): array {
        $platform = PlatformMessagingSetting::current();

        $fromEmail = $settings->from_email ?: $platform->from_email;
        $fromName = $settings->from_name ?: ($platform->from_name ?: $organization->name);

        $attachment = null;
        if ($template?->has_attachment && $template->absoluteAttachmentPath()) {
            $attachment = [
                'path' => $template->absoluteAttachmentPath(),
                'name' => $template->attachment_filename,
                'mime' => $template->attachment_mime,
            ];
        }

        $mailable = new DonorOutreachMail(
            subjectLine: $message->subject ?: ('Message from '.$organization->name),
            bodyText: $message->body,
            fromEmail: $fromEmail,
            fromName: $fromName,
            attachment: $attachment,
        );

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
     * @return array{status: MessageStatus, error?: string|null}
     */
    protected function dispatchProviderMessage(
        OrganizationMessagingSetting $settings,
        OutboundMessage $message,
        string $kind,
    ): array {
        $apiKey = $settings->sms_api_key;
        $from = $settings->sms_from_number;

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

        Log::info('Outbound '.$kind.' dispatched via configured provider stub', [
            'outbound_message_id' => $message->id,
            'from' => $from,
            'recipient' => $message->recipient,
        ]);

        return ['status' => MessageStatus::Sent];
    }

    protected function assertChannelEnabled(
        OrganizationMessagingSetting $settings,
        MessageChannel $channel,
        Organization $organization,
    ): void {
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

        if ($channel === MessageChannel::WhatsApp) {
            $this->entitlements->assertFeature($organization, 'whatsapp');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function replacementsFor(
        Donor $donor,
        Organization $organization,
        User $actor,
        ?\App\Models\TrackingLink $trackingLink = null,
    ): array {
        $donationLink = $trackingLink?->publicUrl() ?? '';

        return [
            '{{name}}' => $donor->full_name,
            '{{donor_name}}' => $donor->full_name,
            '{{org}}' => $organization->name,
            '{{organization}}' => $organization->name,
            '{{phone}}' => $donor->phone ?? '',
            '{{email}}' => $donor->email ?? '',
            '{{volunteer}}' => $actor->name,
            '{{donation_link}}' => $donationLink,
            '{{tracking_link}}' => $donationLink,
            '{{receipt}}' => 'Receipt attached.',
            '{{1}}' => $donor->full_name,
            '{{2}}' => $organization->name,
            '{{3}}' => $actor->name,
        ];
    }

    protected function toMetaBodyText(MessageTemplate $template): string
    {
        $keys = $template->orderedVariableKeys();
        $body = (string) $template->body;

        // Document placeholders are not Meta body variables — strip them for the BODY component.
        foreach (MessageTemplate::DOCUMENT_VARIABLES as $docVar) {
            $body = preg_replace('/\{\{\s*'.preg_quote($docVar, '/').'\s*\}\}/i', '', $body) ?? $body;
        }
        $body = preg_replace("/[ \t]+/", ' ', $body) ?? $body;
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;
        $body = trim($body);

        foreach ($keys as $index => $key) {
            if (ctype_digit((string) $key)) {
                continue;
            }
            $body = preg_replace(
                '/\{\{\s*'.preg_quote((string) $key, '/').'\s*\}\}/',
                '{{'.($index + 1).'}}',
                $body,
            ) ?? $body;
        }

        // Meta rejects bodies that start or end with a variable (punctuation alone is not enough).
        if (preg_match('/^[\s\p{P}]*\{\{\d+\}\}/u', $body)) {
            $body = 'Hello, '.$body;
        }
        if (preg_match('/\{\{\d+\}\}[\s\p{P}]*$/u', $body)) {
            $body = rtrim($body).' Thank you.';
        }

        return $body;
    }

    /**
     * @return list<string>
     */
    protected function exampleValuesForTemplate(MessageTemplate $template, Organization $organization): array
    {
        $keys = $template->orderedVariableKeys();
        $examples = [];

        foreach ($keys as $key) {
            $examples[] = match ($key) {
                'name', 'donor_name', '1' => 'Donor Name',
                'org', 'organization', '2' => $organization->name,
                'volunteer', '3' => 'Volunteer',
                'phone' => '+919999999999',
                'email' => 'donor@example.com',
                default => 'Sample',
            };
        }

        return $examples;
    }

    protected function slugifyMetaName(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $name) ?: 'template');
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'template';
    }

    public function normalizePhone(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        $clean = preg_replace('/[^\d+]/', '', $phone) ?: '';

        if (str_starts_with($clean, '+')) {
            return '+'.preg_replace('/\D+/', '', substr($clean, 1));
        }

        $digits = preg_replace('/\D+/', '', $clean) ?: '';

        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        return $digits !== '' ? '+'.$digits : '';
    }
}
