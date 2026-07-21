<?php

namespace App\Services\WordPress;

use App\Enums\ApiAuthType;
use App\Enums\SyncStatus;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\SyncRun;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WordPressDonorSyncService
{
    public function defaultFieldMappings(): array
    {
        return [
            'donor_id' => 'id',
            'full_name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'alternate_phone' => 'alternate_phone',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'donation_id' => 'donation_id',
            'amount' => 'amount',
            'currency' => 'currency',
            'donated_at' => 'donated_at',
            'payment_status' => 'payment_status',
            'payment_method' => 'payment_method',
        ];
    }

    public function testConnection(OrganizationApiConnection $connection): array
    {
        try {
            $credentials = $connection->safeCredentials();
            if ($credentials === null) {
                return [
                    'ok' => false,
                    'message' => 'Saved credentials cannot be decrypted. Re-enter Site ID, API key, and HMAC secret, then Save.',
                ];
            }
            if ($connection->auth_type === ApiAuthType::Hmac && blank($credentials['api_key'] ?? $credentials['key'] ?? null)) {
                return [
                    'ok' => false,
                    'message' => 'API key is missing. Paste the key from WP Admin → DonorConnect → Reveal secrets, then Save.',
                ];
            }

            $healthUrl = rtrim($connection->base_url, '/').'/health';
            $response = $this->signedRequest($connection, 'GET', $healthUrl);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => 'Bridge connection successful.',
                    'status' => $response->status(),
                    'body' => $response->json(),
                ];
            }

            // Fallback to donors endpoint for legacy connectors.
            $response = $this->signedRequest(
                $connection,
                'GET',
                $this->endpoint($connection, 'donors'),
                ['per_page' => 1, 'page' => 1],
            );

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Connection successful.', 'status' => $response->status()];
            }

            $body = $response->json() ?? $response->body();
            $detail = is_array($body)
                ? (string) ($body['message'] ?? json_encode($body))
                : (string) $body;

            $message = 'Connection failed: HTTP '.$response->status();
            if ($response->status() === 401) {
                $message = 'WordPress rejected the API key/HMAC (HTTP 401). Re-copy secrets from WP Admin → DonorConnect and Save again.';
                if ($detail !== '') {
                    $message .= ' '.$detail;
                }
            } elseif ($detail !== '') {
                $message .= ' '.$detail;
            }

            return [
                'ok' => false,
                'message' => $message,
                'status' => $response->status(),
                'body' => $body,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function sync(OrganizationApiConnection $connection): SyncRun
    {
        $run = SyncRun::create([
            'organization_id' => $connection->organization_id,
            'organization_api_connection_id' => $connection->id,
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ]);

        $connection->update([
            'sync_status' => SyncStatus::Running,
            'last_error' => null,
        ]);

        try {
            $mappings = array_merge($this->defaultFieldMappings(), $connection->field_mappings ?? []);
            $page = 1;
            $perPage = (int) data_get($connection->sync_settings, 'per_page', 100);

            do {
                $response = $this->signedRequest(
                    $connection,
                    'GET',
                    $this->endpoint($connection, 'donors'),
                    [
                        'page' => $page,
                        'per_page' => $perPage,
                    ],
                );

                if (! $response->successful()) {
                    throw new \RuntimeException('Sync failed with HTTP '.$response->status().': '.$response->body());
                }

                $payload = $response->json();
                $records = $this->extractRecords($payload);

                foreach ($records as $record) {
                    $this->upsertDonorAndDonations($connection, $record, $mappings, $run);
                }

                $hasMore = count($records) >= $perPage;
                $page++;
            } while ($hasMore && $page <= 100);

            $run->update([
                'status' => SyncStatus::Success,
                'finished_at' => now(),
            ]);

            $connection->update([
                'sync_status' => SyncStatus::Success,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('WordPress sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => SyncStatus::Failed,
                'error_details' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $connection->update([
                'sync_status' => SyncStatus::Failed,
                'last_error' => $e->getMessage(),
            ]);
        }

        return $run->fresh();
    }

    protected function upsertDonorAndDonations(
        OrganizationApiConnection $connection,
        array $record,
        array $mappings,
        SyncRun $run,
    ): void {
        $externalId = (string) data_get($record, $mappings['donor_id']);

        if ($externalId === '') {
            return;
        }

        $donorData = [
            'organization_id' => $connection->organization_id,
            'external_donor_id' => $externalId,
            'full_name' => (string) (data_get($record, $mappings['full_name']) ?: 'Unknown Donor'),
            'email' => data_get($record, $mappings['email']),
            'phone' => data_get($record, $mappings['phone']),
            'alternate_phone' => data_get($record, $mappings['alternate_phone']),
            'address' => data_get($record, $mappings['address']),
            'city' => data_get($record, $mappings['city']),
            'state' => data_get($record, $mappings['state']),
            'country' => data_get($record, $mappings['country']) ?: 'India',
            'metadata' => $record,
        ];

        $existing = Donor::query()
            ->forOrganization($connection->organization_id)
            ->where('external_donor_id', $externalId)
            ->first();

        if ($existing) {
            $existing->update($donorData);
            $run->increment('donors_updated');
            $donor = $existing;
        } else {
            $organization = $connection->organization ?? Organization::query()->find($connection->organization_id);
            if ($organization && ! $organization->canAcceptNewDonors(1)) {
                Log::warning('WordPress sync skipped new donor — org donor limit reached', [
                    'organization_id' => $connection->organization_id,
                    'external_donor_id' => $externalId,
                    'donors_limit' => $organization->donors_limit,
                ]);

                return;
            }

            $donor = Donor::create($donorData);
            $run->increment('donors_imported');
        }

        $donations = data_get($record, 'donations', []);
        if (! is_array($donations)) {
            $donations = [];
        }

        // Support flat single-donation payloads
        if (empty($donations) && data_get($record, $mappings['amount']) !== null) {
            $donations = [$record];
        }

        foreach ($donations as $donationRecord) {
            $this->upsertDonation($connection, $donor, $donationRecord, $mappings, $run);
        }

        $this->refreshDonorTotals($donor);
    }

    protected function upsertDonation(
        OrganizationApiConnection $connection,
        Donor $donor,
        array $record,
        array $mappings,
        SyncRun $run,
    ): void {
        $externalDonationId = (string) (data_get($record, $mappings['donation_id'])
            ?: data_get($record, 'id')
            ?: '');

        if ($externalDonationId === '') {
            $externalDonationId = 'gen-'.$donor->external_donor_id.'-'.md5(json_encode($record));
        }

        $amount = (float) data_get($record, $mappings['amount'], 0);
        if ($amount <= 0) {
            return;
        }

        $donatedAt = data_get($record, $mappings['donated_at']) ?: now()->toDateTimeString();

        $payload = [
            'organization_id' => $connection->organization_id,
            'donor_id' => $donor->id,
            'external_donation_id' => $externalDonationId,
            'amount' => $amount,
            'currency' => data_get($record, $mappings['currency']) ?: 'INR',
            'donated_at' => $donatedAt,
            'payment_status' => data_get($record, $mappings['payment_status']) ?: 'completed',
            'payment_method' => data_get($record, $mappings['payment_method']),
            'source_data' => $record,
        ];

        $existing = Donation::query()
            ->forOrganization($connection->organization_id)
            ->where('external_donation_id', $externalDonationId)
            ->first();

        if ($existing) {
            $existing->update($payload);
            $run->increment('donations_updated');
        } else {
            Donation::create($payload);
            $run->increment('donations_imported');
        }
    }

    protected function refreshDonorTotals(Donor $donor): void
    {
        $stats = Donation::query()
            ->where('donor_id', $donor->id)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount),0) as total, MAX(donated_at) as last_at')
            ->first();

        $last = Donation::query()
            ->where('donor_id', $donor->id)
            ->orderByDesc('donated_at')
            ->first();

        $donor->update([
            'total_donated' => $stats->total ?? 0,
            'last_donation_at' => $stats->last_at,
            'last_donation_amount' => $last?->amount,
        ]);
    }

    protected function extractRecords(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'donors', 'results', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : [$payload[$key]];
            }
        }

        return [$payload];
    }

    protected function endpoint(OrganizationApiConnection $connection, string $resource): string
    {
        $base = rtrim($connection->base_url, '/');
        $path = data_get($connection->sync_settings, "endpoints.{$resource}", '/'.$resource);

        return $base.'/'.ltrim((string) $path, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function signedRequest(
        OrganizationApiConnection $connection,
        string $method,
        string $url,
        array $query = [],
        ?string $body = null,
    ): \Illuminate\Http\Client\Response {
        $request = $this->client($connection);
        $method = strtoupper($method);

        if ($connection->auth_type === ApiAuthType::Hmac) {
            $credentials = $connection->credentials ?? [];
            ksort($query);
            $queryString = http_build_query($query);
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
            // WP REST get_route() omits /wp-json prefix.
            $path = (string) preg_replace('#^/wp-json#', '', $path);
            $pathWithQuery = $path.($queryString !== '' ? '?'.$queryString : '');
            $bodyString = $body ?? '';
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $payload = $timestamp.'.'.$nonce.'.'.$method.'.'.$pathWithQuery.'.'.hash('sha256', $bodyString);
            $signature = hash_hmac('sha256', $payload, (string) ($credentials['hmac_secret'] ?? ''));

            $request = $request->withHeaders([
                'X-DC-API-Key' => (string) ($credentials['api_key'] ?? $credentials['key'] ?? ''),
                'X-DC-Timestamp' => $timestamp,
                'X-DC-Nonce' => $nonce,
                'X-DC-Signature' => $signature,
                'X-DC-Site-Id' => (string) ($credentials['site_id'] ?? ''),
            ]);
        }

        return match ($method) {
            'POST' => $request->withBody($body ?? '', 'application/json')->post($url),
            'PUT' => $request->withBody($body ?? '', 'application/json')->put($url),
            default => $request->get($url, $query),
        };
    }

    protected function client(OrganizationApiConnection $connection): PendingRequest
    {
        $request = Http::timeout(60)->acceptJson();
        $credentials = $connection->credentials ?? [];

        return match ($connection->auth_type) {
            ApiAuthType::Bearer => $request->withToken((string) ($credentials['token'] ?? '')),
            ApiAuthType::Basic => $request->withBasicAuth(
                (string) ($credentials['username'] ?? ''),
                (string) ($credentials['password'] ?? ''),
            ),
            ApiAuthType::ApiKey, ApiAuthType::Hmac => $request->withHeaders([
                (string) ($credentials['header'] ?? 'X-DC-API-Key') => (string) ($credentials['key'] ?? $credentials['api_key'] ?? ''),
            ]),
            default => $request,
        };
    }

    /**
     * Ingest donor payloads pushed from the WordPress bridge (same upsert rules as pull sync).
     *
     * @param  list<array<string, mixed>>  $records
     * @return array{donors_imported:int,donors_updated:int,donations_imported:int,donations_updated:int}
     */
    public function ingestRecords(int $organizationId, array $records): array
    {
        $connection = OrganizationApiConnection::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'name' => 'WordPress Bridge (push)',
                'base_url' => 'push://local',
                'auth_type' => ApiAuthType::None,
                'is_active' => true,
            ]
        );

        $run = SyncRun::create([
            'organization_id' => $organizationId,
            'organization_api_connection_id' => $connection->id,
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ]);

        $mappings = array_merge($this->defaultFieldMappings(), $connection->field_mappings ?? []);

        try {
            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }
                $this->upsertDonorAndDonations($connection, $record, $mappings, $run);
            }

            $run->update([
                'status' => SyncStatus::Success,
                'finished_at' => now(),
            ]);

            $connection->update([
                'sync_status' => SyncStatus::Success,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => SyncStatus::Failed,
                'error_details' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }

        $run->refresh();

        return [
            'donors_imported' => (int) $run->donors_imported,
            'donors_updated' => (int) $run->donors_updated,
            'donations_imported' => (int) $run->donations_imported,
            'donations_updated' => (int) $run->donations_updated,
        ];
    }

    /**
     * Pull NGOBuddy Razorpay keys from the Bridge and store them on the organization.
     *
     * @return array{ok:bool,message:string,key_id?:string}
     */
    public function syncRazorpayCredentials(OrganizationApiConnection $connection): array
    {
        $url = rtrim($connection->base_url, '/').'/razorpay/config';
        $response = $this->signedRequest($connection, 'GET', $url);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Failed to read Razorpay config: HTTP '.$response->status().' '.$response->body(),
            ];
        }

        $data = $response->json() ?? [];
        $keyId = (string) ($data['razorpay_key_id'] ?? '');
        $keySecret = (string) ($data['razorpay_key_secret'] ?? '');

        if ($keyId === '' || $keySecret === '') {
            return ['ok' => false, 'message' => 'Bridge did not return Razorpay keys. Configure them in NGOBuddy on the WordPress site.'];
        }

        $organization = Organization::query()->findOrFail($connection->organization_id);
        $organization->fill([
            'razorpay_enabled' => true,
            'razorpay_key_id' => $keyId,
            'razorpay_key_secret' => $keySecret,
        ]);

        if (filled($data['razorpay_webhook_secret'] ?? null)) {
            $organization->razorpay_webhook_secret = (string) $data['razorpay_webhook_secret'];
        }

        $organization->save();

        return [
            'ok' => true,
            'message' => 'Razorpay credentials synced from WordPress (NGOBuddy). Payment requests can now be triggered from CRM.',
            'key_id' => $keyId,
            'mode' => $data['razorpay_mode'] ?? null,
        ];
    }

    /**
     * Ask the WordPress Bridge to create a Razorpay payment link using the site's NGOBuddy keys.
     *
     * @return array{ok:bool,short_url?:string,payment_link_id?:string,message?:string,raw?:mixed}
     */
    public function createPaymentLinkViaBridge(
        OrganizationApiConnection $connection,
        Donor $donor,
        float $amount,
        ?string $purpose = null,
    ): array {
        $url = rtrim($connection->base_url, '/').'/razorpay/payment-links';
        $body = json_encode([
            'amount' => $amount,
            'currency' => $connection->organization?->currency ?: 'INR',
            'purpose' => $purpose ?: 'Donation request',
            'donor_name' => $donor->full_name,
            'donor_email' => $donor->email,
            'donor_phone' => $donor->phone,
            'external_donor_id' => $donor->external_donor_id,
            'crm_donor_id' => $donor->id,
            'callback_url' => url('/donors/'.$donor->id),
        ], JSON_THROW_ON_ERROR);

        $response = $this->signedRequest($connection, 'POST', $url, [], $body);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Bridge payment link failed: HTTP '.$response->status().' '.$response->body(),
            ];
        }

        $data = $response->json() ?? [];

        return [
            'ok' => true,
            'short_url' => $data['short_url'] ?? null,
            'payment_link_id' => $data['payment_link_id'] ?? null,
            'raw' => $data,
        ];
    }

    public function razorpayStatus(OrganizationApiConnection $connection): array
    {
        $url = rtrim($connection->base_url, '/').'/razorpay/status';
        $response = $this->signedRequest($connection, 'GET', $url);

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'HTTP '.$response->status(), 'body' => $response->json()];
        }

        return ['ok' => true, ...(array) $response->json()];
    }
}
