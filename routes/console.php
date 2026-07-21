<?php

use App\Jobs\Sync\SyncOrganizationDonorsJob;
use App\Models\OrganizationApiConnection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    OrganizationApiConnection::query()
        ->where('is_active', true)
        ->where(function ($q) {
            $q->whereNull('sync_settings->schedule')
                ->orWhere('sync_settings->schedule', 'hourly');
        })
        ->pluck('id')
        ->each(fn ($id) => SyncOrganizationDonorsJob::dispatch($id));
})->hourly()->name('wordpress-donor-sync-hourly');

Schedule::command('reports:send-due')->hourly()->name('org-report-emails-hourly');
