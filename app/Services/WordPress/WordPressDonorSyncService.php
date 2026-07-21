<?php

namespace App\Services\WordPress;

use App\Enums\ApiAuthType;
use App\Enums\SyncStatus;
use App\Models\Donation;
use App\Models\Donor;
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
            $response = $this->client($connection)->get($this->endpoint($connection, 'donors'), [
                'per_page' => 1,
            ]);

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Connection successful.', 'status' => $response->status()];
            }

            return [
                'ok' => false,
                'message' => 'Connection failed: HTTP '.$response->status(),
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
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
                $response = $this->client($connection)->get($this->endpoint($connection, 'donors'), [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

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
            ApiAuthType::ApiKey => $request->withHeaders([
                (string) ($credentials['header'] ?? 'X-API-Key') => (string) ($credentials['key'] ?? ''),
            ]),
            default => $request,
        };
    }
}
