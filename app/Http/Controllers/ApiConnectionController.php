<?php

namespace App\Http\Controllers;

use App\Enums\ApiAuthType;
use App\Enums\SyncStatus;
use App\Http\Requests\Sync\StoreApiConnectionRequest;
use App\Jobs\Sync\SyncOrganizationDonorsJob;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\SyncRun;
use App\Services\AuditLogger;
use App\Services\WordPress\BridgePairingService;
use App\Services\WordPress\WordPressDonorSyncService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiConnectionController extends Controller
{
    public function edit(Request $request): Response
    {
        return $this->renderEdit($this->resolveOrganization(null));
    }

    public function editForOrganization(Organization $organization): Response
    {
        return $this->renderEdit($this->resolveOrganization($organization));
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

    public function generatePairingCode(Organization $organization): RedirectResponse
    {
        $this->authorize('manageSync', $organization);

        try {
            $pairing = app(BridgePairingService::class)->generate($organization, request()->user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Could not generate pairing code. Run migrations on the server (php artisan migrate), then clear caches.',
            );
        }

        return back()->with([
            'success' => 'Pairing code generated. Paste it in WordPress Admin → DonorConnect within 15 minutes.',
            'bridge_pairing_code' => $pairing['plaintext'],
            'bridge_pairing_expires_at' => $pairing['expires_at'],
        ]);
    }

    public function test(OrganizationApiConnection $connection): RedirectResponse
    {
        return $this->runTest($connection);
    }

    public function testForOrganization(
        Organization $organization,
        OrganizationApiConnection $connection,
    ): RedirectResponse {
        abort_unless($connection->organization_id === $organization->id, 404);

        return $this->runTest($connection);
    }

    public function sync(OrganizationApiConnection $connection): RedirectResponse
    {
        return $this->runSync($connection);
    }

    public function syncForOrganization(
        Organization $organization,
        OrganizationApiConnection $connection,
    ): RedirectResponse {
        abort_unless($connection->organization_id === $organization->id, 404);

        return $this->runSync($connection);
    }

    public function syncRazorpay(OrganizationApiConnection $connection): RedirectResponse
    {
        return $this->runSyncRazorpay($connection);
    }

    public function syncRazorpayForOrganization(
        Organization $organization,
        OrganizationApiConnection $connection,
    ): RedirectResponse {
        abort_unless($connection->organization_id === $organization->id, 404);

        return $this->runSyncRazorpay($connection);
    }

    public function razorpayStatus(OrganizationApiConnection $connection): RedirectResponse
    {
        return $this->runRazorpayStatus($connection);
    }

    public function razorpayStatusForOrganization(
        Organization $organization,
        OrganizationApiConnection $connection,
    ): RedirectResponse {
        abort_unless($connection->organization_id === $organization->id, 404);

        return $this->runRazorpayStatus($connection);
    }

    protected function runTest(OrganizationApiConnection $connection): RedirectResponse
    {
        try {
            $this->authorize('sync', $connection);

            $result = app(WordPressDonorSyncService::class)->testConnection($connection);

            return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Test connection failed: '.$e->getMessage());
        }
    }

    protected function runSync(OrganizationApiConnection $connection): RedirectResponse
    {
        try {
            $this->authorize('sync', $connection);

            // Run immediately so "Sync donors now" works on shared hosting without a queue worker.
            SyncOrganizationDonorsJob::dispatchSync($connection->id);

            $connection->refresh();

            if ($connection->sync_status === SyncStatus::Failed) {
                return back()->with('error', $connection->last_error ?: 'Donor sync failed.');
            }

            return back()->with('success', 'Donor sync completed.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Donor sync failed: '.$e->getMessage());
        }
    }

    protected function runSyncRazorpay(OrganizationApiConnection $connection): RedirectResponse
    {
        try {
            $this->authorize('sync', $connection);

            $service = app(WordPressDonorSyncService::class);
            $result = $service->syncRazorpayCredentials($connection);

            if ($result['ok']) {
                app(AuditLogger::class)->log(
                    'organization.razorpay_synced_from_wordpress',
                    $connection->organization,
                    null,
                    [
                        'key_id' => $result['key_id'] ?? null,
                        'connection_id' => $connection->id,
                    ],
                    $connection->organization_id,
                );
            }

            return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Razorpay sync failed: '.$e->getMessage());
        }
    }

    protected function runRazorpayStatus(OrganizationApiConnection $connection): RedirectResponse
    {
        try {
            $this->authorize('sync', $connection);

            $result = app(WordPressDonorSyncService::class)->razorpayStatus($connection);
            $message = $result['ok']
                ? ('WordPress Razorpay '.(($result['configured'] ?? false) ? 'configured' : 'not configured')
                    .(! empty($result['key_id_masked']) ? ' ('.$result['key_id_masked'].')' : ''))
                : ('Could not read Razorpay status: '.($result['message'] ?? 'unknown'));

            return back()->with($result['ok'] ? 'success' : 'error', $message);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Razorpay status check failed: '.$e->getMessage());
        }
    }

    protected function renderEdit(Organization $organization): Response
    {
        $syncService = app(WordPressDonorSyncService::class);

        $connection = OrganizationApiConnection::query()
            ->forOrganization($organization->id)
            ->first();

        $history = SyncRun::query()
            ->forOrganization($organization->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (SyncRun $run) => [
                'id' => $run->id,
                'status' => $run->status?->value,
                'donors_imported' => $run->donors_imported,
                'donors_updated' => $run->donors_updated,
                'donations_imported' => $run->donations_imported,
                'donations_updated' => $run->donations_updated,
                'error_details' => $run->error_details,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])
            ->values();

        $orgId = $organization->id;
        $connectionId = $connection?->id;
        // Prefer relative path strings so a stale route:cache (missing new names) cannot 500 the page.
        $syncBase = '/organizations/'.$orgId.'/sync';

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
            'crmApiBaseUrl' => url('/api/v1'),
            'routes' => [
                'store' => $syncBase,
                'pairing_code' => $syncBase.'/pairing-code',
                'update' => $connectionId ? $syncBase.'/'.$connectionId : null,
                'test' => $connectionId ? $syncBase.'/'.$connectionId.'/test' : null,
                'run' => $connectionId ? $syncBase.'/'.$connectionId.'/run' : null,
                'razorpay' => $connectionId ? $syncBase.'/'.$connectionId.'/razorpay' : null,
                'razorpay_status' => $connectionId ? $syncBase.'/'.$connectionId.'/razorpay-status' : null,
                'profile' => '/organizations/'.$orgId,
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
        $filled = fn (?string $value): bool => filled($value);

        return match ($data['auth_type']) {
            'bearer' => array_filter(['token' => $data['token'] ?? null], $filled),
            'basic' => array_filter([
                'username' => $data['username'] ?? null,
                'password' => $data['password'] ?? null,
            ], $filled),
            'api_key' => array_filter([
                'key' => $data['api_key'] ?? null,
                'header' => filled($data['api_key'] ?? null)
                    ? ($data['api_key_header'] ?? 'X-API-Key')
                    : null,
            ], $filled),
            'hmac' => array_filter([
                'api_key' => $data['api_key'] ?? null,
                'key' => $data['api_key'] ?? null,
                'header' => filled($data['api_key'] ?? null) ? 'X-DC-API-Key' : null,
                'hmac_secret' => $data['hmac_secret'] ?? null,
                'site_id' => $data['site_id'] ?? null,
            ], $filled),
            default => [],
        };
    }
}
