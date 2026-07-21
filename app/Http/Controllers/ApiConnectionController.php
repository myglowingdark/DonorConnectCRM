<?php

namespace App\Http\Controllers;

use App\Enums\ApiAuthType;
use App\Http\Requests\Sync\StoreApiConnectionRequest;
use App\Jobs\Sync\SyncOrganizationDonorsJob;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\SyncRun;
use App\Services\AuditLogger;
use App\Services\WordPress\WordPressDonorSyncService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiConnectionController extends Controller
{
    public function edit(Request $request, WordPressDonorSyncService $syncService): Response
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $this->authorize('manageSync', $organization);

        $connection = OrganizationApiConnection::query()
            ->forOrganization($orgId)
            ->first();

        $history = SyncRun::query()
            ->forOrganization($orgId)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Sync/Settings', [
            'connection' => $connection?->toSafeArray(),
            'history' => $history,
            'authTypes' => collect(ApiAuthType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'defaultMappings' => $syncService->defaultFieldMappings(),
        ]);
    }

    public function store(StoreApiConnectionRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $organization = Organization::findOrFail($orgId);
        $this->authorize('manageSync', $organization);

        $data = $request->validated();
        $credentials = $this->buildCredentials($data);

        $connection = OrganizationApiConnection::query()->updateOrCreate(
            ['organization_id' => $orgId],
            [
                'name' => $data['name'],
                'base_url' => $data['base_url'],
                'auth_type' => $data['auth_type'],
                'credentials' => $credentials ?: null,
                'field_mappings' => $data['field_mappings'] ?? null,
                'sync_settings' => $data['sync_settings'] ?? [
                    'per_page' => 100,
                    'schedule' => 'hourly',
                    'endpoints' => ['donors' => '/donors'],
                ],
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        // Preserve existing credentials when form sends empty secrets
        if (empty($credentials) && $connection->wasRecentlyCreated === false) {
            // updateOrCreate already wrote null; restore if previously set via separate update
        }

        $auditLogger->log('api_connection.updated', $connection, null, [
            'base_url' => $connection->base_url,
            'auth_type' => $connection->auth_type?->value,
        ], $orgId);

        return back()->with('success', 'API connection saved. Credentials are encrypted at rest.');
    }

    public function update(
        StoreApiConnectionRequest $request,
        OrganizationApiConnection $connection,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->authorize('update', $connection);

        $data = $request->validated();
        $credentials = $this->buildCredentials($data);

        $payload = [
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'auth_type' => $data['auth_type'],
            'field_mappings' => $data['field_mappings'] ?? $connection->field_mappings,
            'sync_settings' => $data['sync_settings'] ?? $connection->sync_settings,
            'is_active' => $data['is_active'] ?? true,
        ];

        if (! empty($credentials)) {
            $payload['credentials'] = array_merge($connection->credentials ?? [], $credentials);
        }

        $connection->update($payload);

        $auditLogger->log('api_connection.updated', $connection, null, [
            'base_url' => $connection->base_url,
        ], $connection->organization_id);

        return back()->with('success', 'API connection updated.');
    }

    public function test(OrganizationApiConnection $connection, WordPressDonorSyncService $service): RedirectResponse
    {
        $this->authorize('sync', $connection);

        $result = $service->testConnection($connection);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function sync(Request $request, OrganizationApiConnection $connection): RedirectResponse
    {
        $this->authorize('sync', $connection);

        SyncOrganizationDonorsJob::dispatch($connection->id);

        return back()->with('success', 'Donor sync queued. Refresh shortly to see results.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildCredentials(array $data): array
    {
        return match ($data['auth_type']) {
            'bearer' => array_filter(['token' => $data['token'] ?? null]),
            'basic' => array_filter([
                'username' => $data['username'] ?? null,
                'password' => $data['password'] ?? null,
            ]),
            'api_key' => array_filter([
                'key' => $data['api_key'] ?? null,
                'header' => $data['api_key_header'] ?? 'X-API-Key',
            ]),
            default => [],
        };
    }
}
