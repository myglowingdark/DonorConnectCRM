<?php

namespace App\Services\Messaging;

use App\Models\Organization;
use App\Models\OrganizationMessagingSetting;
use App\Models\PlatformMessagingSetting;
use App\Services\SaaS\EntitlementService;
use Illuminate\Validation\ValidationException;

class MetaWhatsAppCredentialResolver
{
    public function __construct(private EntitlementService $entitlements) {}

    public function resolveForOrganization(Organization $organization, ?OrganizationMessagingSetting $settings = null): MetaWhatsAppCredentials
    {
        $this->entitlements->assertFeature($organization, 'whatsapp');

        $settings ??= OrganizationMessagingSetting::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'email_enabled' => true,
                'whatsapp_enabled' => true,
                'sms_enabled' => true,
                'whatsapp_use_platform' => true,
            ],
        );

        if (! $settings->whatsapp_enabled) {
            throw ValidationException::withMessages([
                'whatsapp' => 'WhatsApp messaging is disabled for this organization.',
            ]);
        }

        $platform = PlatformMessagingSetting::current();
        $appId = config('services.meta.app_id') ?: $platform->meta_app_id;
        $appSecret = config('services.meta.app_secret') ?: $platform->meta_app_secret;

        if (! $settings->whatsapp_use_platform && $this->orgCredentialsComplete($settings)) {
            return new MetaWhatsAppCredentials(
                accessToken: (string) $settings->whatsapp_api_key,
                phoneNumberId: (string) $settings->whatsapp_phone_number_id,
                wabaId: (string) $settings->whatsapp_waba_id,
                apiVersion: 'v21.0',
                appId: $appId,
                appSecret: $appSecret,
                source: 'organization',
            );
        }

        if ($platform->whatsapp_enabled && $this->platformCredentialsComplete($platform)) {
            return new MetaWhatsAppCredentials(
                accessToken: (string) $platform->meta_access_token,
                phoneNumberId: (string) $platform->meta_phone_number_id,
                wabaId: (string) $platform->meta_waba_id,
                apiVersion: $platform->meta_api_version ?: 'v21.0',
                appId: $appId,
                appSecret: $appSecret,
                source: 'platform',
            );
        }

        throw ValidationException::withMessages([
            'whatsapp' => 'WhatsApp Meta credentials are not configured. Use platform WhatsApp or add organization Cloud API credentials.',
        ]);
    }

    public function tryResolve(Organization $organization, ?OrganizationMessagingSetting $settings = null): ?MetaWhatsAppCredentials
    {
        try {
            return $this->resolveForOrganization($organization, $settings);
        } catch (ValidationException) {
            return null;
        }
    }

    public function platformWebhookCredentials(): ?MetaWhatsAppCredentials
    {
        $platform = PlatformMessagingSetting::current();

        if (! $platform->whatsapp_enabled || blank($platform->meta_access_token)) {
            return null;
        }

        return new MetaWhatsAppCredentials(
            accessToken: (string) $platform->meta_access_token,
            phoneNumberId: (string) ($platform->meta_phone_number_id ?? ''),
            wabaId: (string) ($platform->meta_waba_id ?? ''),
            apiVersion: $platform->meta_api_version ?: 'v21.0',
            appId: $platform->meta_app_id,
            appSecret: $platform->meta_app_secret,
            source: 'platform',
        );
    }

    protected function orgCredentialsComplete(OrganizationMessagingSetting $settings): bool
    {
        return filled($settings->whatsapp_api_key)
            && filled($settings->whatsapp_phone_number_id)
            && filled($settings->whatsapp_waba_id);
    }

    protected function platformCredentialsComplete(PlatformMessagingSetting $settings): bool
    {
        return filled($settings->meta_access_token)
            && filled($settings->meta_phone_number_id)
            && filled($settings->meta_waba_id);
    }
}
