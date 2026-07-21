<?php

namespace App\Services\Messaging;

use App\Models\Organization;
use App\Models\OrganizationMessagingSetting;
use App\Models\PlatformMessagingSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MetaEmbeddedSignupService
{
    public function __construct(private MetaWhatsAppClient $client) {}

    /**
     * @return array{app_id: string|null, config_id: string|null, api_version: string, ready: bool}
     */
    public function publicConfig(?PlatformMessagingSetting $platform = null): array
    {
        $platform ??= PlatformMessagingSetting::current();

        $appId = config('services.meta.app_id') ?: $platform->meta_app_id;
        $configId = config('services.meta.embedded_signup_config_id')
            ?: $platform->meta_embedded_signup_config_id;
        $apiVersion = config('services.meta.api_version')
            ?: ($platform->meta_api_version ?: 'v21.0');

        return [
            'app_id' => $appId ?: null,
            'config_id' => $configId ?: null,
            'api_version' => $apiVersion,
            'ready' => filled($appId) && filled($configId) && filled(config('services.meta.app_secret') ?: $platform->meta_app_secret),
        ];
    }

    /**
     * @param  array{code: string, phone_number_id: string, waba_id: string}  $payload
     * @return array{access_token: string, phone_number_id: string, waba_id: string, display_phone_number: string|null, raw: array<string, mixed>}
     */
    public function completeSignup(array $payload): array
    {
        $code = trim((string) ($payload['code'] ?? ''));
        $phoneNumberId = trim((string) ($payload['phone_number_id'] ?? ''));
        $wabaId = trim((string) ($payload['waba_id'] ?? ''));

        if ($code === '' || $phoneNumberId === '' || $wabaId === '') {
            throw ValidationException::withMessages([
                'whatsapp' => 'Meta signup did not return a complete session (code, phone number ID, and WABA ID are required).',
            ]);
        }

        $platform = PlatformMessagingSetting::current();
        $appId = config('services.meta.app_id') ?: $platform->meta_app_id;
        $appSecret = config('services.meta.app_secret') ?: $platform->meta_app_secret;
        $apiVersion = ltrim(
            (string) (config('services.meta.api_version') ?: ($platform->meta_api_version ?: 'v21.0')),
            '/',
        );

        if (blank($appId) || blank($appSecret)) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Meta App ID and App Secret must be configured before Connect with Meta can finish.',
            ]);
        }

        $tokenResponse = Http::asForm()->get("https://graph.facebook.com/{$apiVersion}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
        ]);

        if (! $tokenResponse->successful()) {
            $message = data_get($tokenResponse->json(), 'error.message') ?: 'Could not exchange Meta signup code for an access token.';

            throw ValidationException::withMessages([
                'whatsapp' => $message,
            ]);
        }

        $accessToken = (string) ($tokenResponse->json('access_token') ?? '');
        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'whatsapp' => 'Meta did not return an access token for this signup.',
            ]);
        }

        // Subscribe the app to WABA webhooks (best-effort).
        Http::withToken($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$wabaId}/subscribed_apps");

        $display = null;
        try {
            $phone = $this->client->getPhoneNumber(new MetaWhatsAppCredentials(
                accessToken: $accessToken,
                phoneNumberId: $phoneNumberId,
                wabaId: $wabaId,
                apiVersion: $apiVersion,
                appId: $appId,
                appSecret: $appSecret,
            ));
            $display = $phone['display_phone_number'] ?? null;
        } catch (\Throwable) {
            // Non-fatal — credentials may still be valid.
        }

        return [
            'access_token' => $accessToken,
            'phone_number_id' => $phoneNumberId,
            'waba_id' => $wabaId,
            'display_phone_number' => $display,
            'raw' => $tokenResponse->json() ?? [],
        ];
    }

    /**
     * @param  array{code: string, phone_number_id: string, waba_id: string}  $payload
     */
    public function connectPlatform(array $payload): PlatformMessagingSetting
    {
        $result = $this->completeSignup($payload);
        $settings = PlatformMessagingSetting::current();

        $settings->fill([
            'whatsapp_enabled' => true,
            'whatsapp_module_enabled' => $settings->whatsapp_module_enabled ?: true,
            'meta_access_token' => $result['access_token'],
            'meta_phone_number_id' => $result['phone_number_id'],
            'meta_waba_id' => $result['waba_id'],
            'meta_app_id' => config('services.meta.app_id') ?: $settings->meta_app_id,
            'meta_api_version' => config('services.meta.api_version') ?: ($settings->meta_api_version ?: 'v21.0'),
        ]);
        $settings->save();

        return $settings->fresh();
    }

    /**
     * @param  array{code: string, phone_number_id: string, waba_id: string}  $payload
     */
    public function connectOrganization(Organization $organization, array $payload): OrganizationMessagingSetting
    {
        $result = $this->completeSignup($payload);

        $settings = OrganizationMessagingSetting::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'email_enabled' => true,
                'whatsapp_enabled' => true,
                'sms_enabled' => true,
                'whatsapp_use_platform' => false,
            ],
        );

        $settings->fill([
            'whatsapp_enabled' => true,
            'whatsapp_use_platform' => false,
            'whatsapp_provider' => 'meta',
            'whatsapp_api_key' => $result['access_token'],
            'whatsapp_phone_number_id' => $result['phone_number_id'],
            'whatsapp_waba_id' => $result['waba_id'],
            'whatsapp_from_number' => $result['display_phone_number'],
        ]);
        $settings->save();

        return $settings->fresh();
    }
}
