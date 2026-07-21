<?php

namespace App\Jobs\Sync;

use App\Models\OrganizationApiConnection;
use App\Services\WordPress\WordPressDonorSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOrganizationDonorsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $connectionId) {}

    public function handle(WordPressDonorSyncService $service): void
    {
        $connection = OrganizationApiConnection::query()->find($this->connectionId);

        if (! $connection || ! $connection->is_active) {
            return;
        }

        $service->sync($connection);
    }
}
