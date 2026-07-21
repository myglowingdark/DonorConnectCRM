<?php

namespace App\Services\WordPress;

use App\Enums\ApiAuthType;
use App\Models\BridgePairingCode;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\OrganizationApiToken;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BridgePairingService
{
    public const TTL_MINUTES = 15;

    /**
     * @return array{plaintext:string,expires_at:string,prefix:string}
     */
    public function generate(Organization $organization, ?User $creator = null): array
    {
        BridgePairingCode::query()
            ->forOrganization($organization->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $plaintext = 'dc_pair_'.Str::lower(Str::random(32));
        $prefix = substr($plaintext, 0, 12);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        BridgePairingCode::create([
            'organization_id' => $organization->id,
            'created_by' => $creator?->id,
            'code_hash' => hash('sha256', $plaintext),
            'code_prefix' => $prefix,
            'expires_at' => $expiresAt,
        ]);

        return [
            'plaintext' => $plaintext,
            'expires_at' => $expiresAt->toIso8601String(),
            'prefix' => $prefix,
        ];
    }

    public function resolveFromBearer(?string $bearer): ?BridgePairingCode
    {
        if (! $bearer || ! str_starts_with($bearer, 'dc_pair_')) {
            return null;
        }

        return BridgePairingCode::query()
            ->where('code_hash', hash('sha256', $bearer))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param  array{site_id:string,api_key:string,hmac_secret:string,rest_base_url:string}  $payload
     * @return array{ok:bool,organization_id:int,connection_id:int,push_token?:string,push_token_prefix?:string}
     */
    public function claim(BridgePairingCode $code, array $payload): array
    {
        if (! $code->isValid()) {
            throw ValidationException::withMessages([
                'pairing_code' => 'Pairing code is invalid, expired, or already used.',
            ]);
        }

        $organization = Organization::query()->findOrFail($code->organization_id);

        $connection = OrganizationApiConnection::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'name' => 'DonorConnect Bridge',
                'base_url' => rtrim($payload['rest_base_url'], '/'),
                'auth_type' => ApiAuthType::Hmac,
                'credentials' => [
                    'api_key' => $payload['api_key'],
                    'key' => $payload['api_key'],
                    'header' => 'X-DC-API-Key',
                    'hmac_secret' => $payload['hmac_secret'],
                    'site_id' => $payload['site_id'],
                ],
                'sync_settings' => [
                    'per_page' => 100,
                    'schedule' => 'hourly',
                    'endpoints' => ['donors' => '/donors'],
                ],
                'is_active' => true,
            ],
        );

        $code->forceFill(['used_at' => now()])->saveQuietly();

        $pushToken = 'dc_'.Str::random(40);
        $pushPrefix = substr($pushToken, 0, 12);

        OrganizationApiToken::create([
            'organization_id' => $organization->id,
            'created_by' => $code->created_by,
            'name' => 'WordPress Bridge (paired)',
            'token_hash' => hash('sha256', $pushToken),
            'token_prefix' => $pushPrefix,
            'abilities' => ['donors:read', 'donors:write', 'donations:read'],
        ]);

        return [
            'ok' => true,
            'organization_id' => $organization->id,
            'connection_id' => $connection->id,
            'push_token' => $pushToken,
            'push_token_prefix' => $pushPrefix,
        ];
    }
}
