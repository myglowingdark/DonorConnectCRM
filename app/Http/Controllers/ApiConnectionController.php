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
        return $this->renderEdit(
            $this->resolveOrganization(null),
            $syncService,
        );
    }

    public function editForOrganization(
        Organization $organization,
        WordPressDonorSyncService $syncService,
    ): Response {
        return $this->renderEdit(
            $this->resolveOrganization($organization),
            $syncService,
        );
    }

    public function store(
        StoreApiConnectionRequest $request,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        return $this->persistNew(
            $request,
            $this->resolveOrganization(null),
            $auditLogger,
        );
    }

    public function storeForOrganization(
        StoreApiConnectionRequest $request,
        Organization $organization,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        return $this->persistNew(
            $request,
            $this->resolveOrganization($organization),
            $auditLogger,
        );
    }

    public function update(
        StoreApiConnectionRequest $request,
        OrganizationApiConnection $connection,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->authorize('update', $connection);

        return $this->persistUpdate($request, $connection, $auditLogger);
    }

    public function updateForOrganization(
        StoreApiConnectionRequest $request,
        Organization $organization,
        OrganizationApiConnection $connection,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($connection->organization_id === $organization->id, 404);
        $this->authorize('update', $connection);

        return $this->persistUpdate($request, $connection, $auditLogger);
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

    public function syncRazorpay(
        OrganizationApiConnection $connection,
        WordPressDonorSyncService $service,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->authorize('sync', $connection);

        $result = $service->syncRazorpayCredentials($connection);

        if ($result['ok']) {
            $auditLogger->log('organization.razorpay_synced_from_wordpress', $connection->organization, null, [
                'key_id' => $result['key_id'] ?? null,
                'connection_id' => $connection->id,
            ], $connection->organization_id);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function razorpayStatus(
        OrganizationApiConnection $connection,
        WordPressDonorSyncService $service,
    ): RedirectResponse {
        $this->authorize('sync', $connection);

        $result = $service->razorpayStatus($connection);
        $message = $result['ok']
            ? ('WordPress Razorpay '.(($result['configured'] ?? false) ? 'configured' : 'not configured')
                .(! empty($result['key_id_masked']) ? ' ('.$result['key_id_masked'].')' : ''))
            : ('Could not read Razorpay status: '.($result['message'] ?? 'unknown'));

        return back()->with($result['ok'] ? 'success' : 'error', $message);
    }

    protected function renderEdit(Organization $organization, WordPressDonorSyncService $syncService): Response
    {
        $connection = OrganizationApiConnection::query()
            ->forOrganization($organization->id)
            ->first();

        $history = SyncRun::query()
            ->forOrganization($organization->id)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Sync/Settings', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'connection' => $connection?->toSafeArray(),
            'history' => $history,
            'authTypes' => collect(ApiAuthType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'defaultMappings' => $syncService->defaultFieldMappings(),
            'routes' => [
                'store' => route('organizations.sync.store', $organization),
                'update' => $connection
                    ? route('organizations.sync.update', [$organization, $connection])
                    : null,
                'test' => $connection
                    ? route('organizations.sync.test', [$organization, $connection])
                    : null,
                'run' => $connection
                    ? route('organizations.sync.run', [$organization, $connection])
                    : null,
                'razorpay' => $connection
                    ? route('organizations.sync.razorpay', [$organization, $connection])
                    : null,
                'razorpay_status' => $connection
                    ? route('organizations.sync.razorpay-status', [$organization, $connection])
                    : null,
                'profile' => route('organizations.show', $organization),
            ],
        ]);
    }

    protected function persistNew(
        StoreApiConnectionRequest $request,
        Organization $organization,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $data = $request->validated();
        $credentials = $this->buildCredentials($data);

        $connection = OrganizationApiConnection::query()->updateOrCreate(
            ['organization_id' => $organization->id],
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

        $auditLogger->log('api_connection.updated', $connection, null, [
            'base_url' => $connection->base_url,
            'auth_type' => $connection->auth_type?->value,
        ], $organization->id);

        return redirect()
            ->route('organizations.sync.edit', $organization)
            ->with('success', 'WordPress site connected. Credentials are encrypted at rest.');
    }

    protected function persistUpdate(
        StoreApiConnectionRequest $request,
        OrganizationApiConnection $connection,
        AuditLogger $auditLogger,
    ): RedirectResponse {
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

        return redirect()
            ->route('organizations.sync.edit', $connection->organization_id)
            ->with('success', 'WordPress site connection updated.');
    }

    protected function resolveOrganization(?Organization $organization): Organization
    {
        if ($organization) {
            $this->authorize('manageSync', $organization);

            return $organization;
        }

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403, 'Select an organization first.');

        $organization = Organization::query()->findOrFail($orgId);
        $this->authorize('manageSync', $organization);

        return $organization;
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
            'hmac' => array_filter([
                'api_key' => $data['api_key'] ?? null,
                'key' => $data['api_key'] ?? null,
                'header' => 'X-DC-API-Key',
                'hmac_secret' => $data['hmac_secret'] ?? null,
                'site_id' => $data['site_id'] ?? null,
            ]),
            default => [],
        };
    }
}
