<?php

use App\Http\Controllers\ApiConnectionController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\DonorImportController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationSwitcherController;
use App\Http\Controllers\Phase2StubController;
use App\Http\Controllers\PlatformMessagingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/organization/switch', [OrganizationSwitcherController::class, 'switch'])
        ->name('organization.switch');

    Route::middleware('org.selected')->group(function () {
        Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
        Route::get('/donors/{donor}', [DonorController::class, 'show'])->name('donors.show');
        Route::post('/donors/{donor}/calls', [DonorController::class, 'logCall'])->name('donors.log-call');
        Route::post('/donors/{donor}/clear-dnc', [DonorController::class, 'clearDoNotCall'])
            ->middleware('role:super_admin,organization_admin')
            ->name('donors.clear-dnc');

        Route::get('/assignments', [AssignmentController::class, 'index'])
            ->middleware('role:super_admin,organization_admin')
            ->name('assignments.index');
        Route::post('/assignments', [AssignmentController::class, 'store'])
            ->middleware('role:super_admin,organization_admin')
            ->name('assignments.store');
        Route::post('/assignments/unassign', [AssignmentController::class, 'destroy'])
            ->middleware('role:super_admin,organization_admin')
            ->name('assignments.destroy');
        Route::post('/assignments/distribute', [AssignmentController::class, 'distribute'])
            ->middleware('role:super_admin,organization_admin')
            ->name('assignments.distribute');

        Route::get('/imports', [DonorImportController::class, 'index'])
            ->middleware('role:super_admin,organization_admin')
            ->name('imports.index');
        Route::post('/imports', [DonorImportController::class, 'store'])
            ->middleware('role:super_admin,organization_admin')
            ->name('imports.store');
        Route::get('/imports/template', [DonorImportController::class, 'template'])
            ->middleware('role:super_admin,organization_admin')
            ->name('imports.template');

        Route::get('/handovers', [HandoverController::class, 'index'])
            ->middleware('role:super_admin,organization_admin')
            ->name('handovers.index');
        Route::post('/handovers', [HandoverController::class, 'store'])
            ->middleware('role:super_admin,organization_admin')
            ->name('handovers.store');

        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::post('/donors/{donor}/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('/transfers/{transfer}/accept', [TransferController::class, 'accept'])->name('transfers.accept');
        Route::post('/transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');
        Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

        Route::get('/sync', [ApiConnectionController::class, 'edit'])
            ->middleware('role:super_admin,organization_admin')
            ->name('sync.edit');
        Route::post('/sync', [ApiConnectionController::class, 'store'])
            ->middleware(['role:super_admin,organization_admin', 'throttle:sync'])
            ->name('sync.store');
        Route::put('/sync/{connection}', [ApiConnectionController::class, 'update'])
            ->middleware(['role:super_admin,organization_admin', 'throttle:sync'])
            ->name('sync.update');
        Route::post('/sync/{connection}/test', [ApiConnectionController::class, 'test'])
            ->middleware(['role:super_admin,organization_admin', 'throttle:sync'])
            ->name('sync.test');
        Route::post('/sync/{connection}/run', [ApiConnectionController::class, 'sync'])
            ->middleware(['role:super_admin,organization_admin', 'throttle:sync'])
            ->name('sync.run');

        Route::get('/reports', [ReportController::class, 'index'])
            ->middleware('role:super_admin,organization_admin')
            ->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])
            ->middleware('role:super_admin,organization_admin')
            ->name('reports.export');

        Route::get('/messaging', [MessagingController::class, 'settings'])
            ->middleware('role:super_admin,organization_admin')
            ->name('messaging.settings');
        Route::put('/messaging', [MessagingController::class, 'updateSettings'])
            ->middleware('role:super_admin,organization_admin')
            ->name('messaging.settings.update');
        Route::get('/messaging/templates', [MessagingController::class, 'templates'])
            ->middleware('role:super_admin,organization_admin')
            ->name('messaging.templates');
        Route::post('/messaging/templates', [MessagingController::class, 'storeTemplate'])
            ->middleware('role:super_admin,organization_admin')
            ->name('messaging.templates.store');
        Route::put('/messaging/templates/{template}', [MessagingController::class, 'updateTemplate'])
            ->middleware('role:super_admin,organization_admin')
            ->name('messaging.templates.update');
        Route::delete('/messaging/templates/{template}', [MessagingController::class, 'destroyTemplate'])
            ->middleware('role:super_admin,organization_admin')
            ->name('messaging.templates.destroy');

        Route::post('/donors/{donor}/messages', [MessagingController::class, 'send'])
            ->name('donors.messages.send');

        Route::get('/commissions', [CommissionController::class, 'settings'])
            ->middleware('role:super_admin,organization_admin')
            ->name('commissions.settings');
        Route::put('/commissions', [CommissionController::class, 'updateSettings'])
            ->middleware('role:super_admin,organization_admin')
            ->name('commissions.settings.update');
        Route::get('/commission-cycles', [Phase2StubController::class, 'commissionCycles'])
            ->middleware('role:super_admin,organization_admin')
            ->name('commissions.cycles');
        Route::get('/attributions', [Phase2StubController::class, 'attributions'])
            ->middleware('role:super_admin,organization_admin')
            ->name('attributions.index');
        Route::get('/email-reports', [Phase2StubController::class, 'emailReports'])
            ->middleware('role:super_admin,organization_admin')
            ->name('email-reports.index');
        Route::get('/my-commission', [Phase2StubController::class, 'myCommission'])
            ->name('commissions.mine');
    });

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role:super_admin,organization_admin')
        ->name('users.index');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('role:super_admin,organization_admin')
        ->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('role:super_admin,organization_admin')
        ->name('users.update');

    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('organizations.index');
    Route::get('/organizations/create', [OrganizationController::class, 'create'])
        ->middleware('role:super_admin')
        ->name('organizations.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->middleware('role:super_admin')
        ->name('organizations.store');
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])
        ->middleware('role:super_admin')
        ->name('organizations.edit');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])
        ->middleware('role:super_admin')
        ->name('organizations.update');

    Route::get('/platform/messaging', [PlatformMessagingController::class, 'edit'])
        ->middleware('role:super_admin')
        ->name('platform.messaging.edit');
    Route::put('/platform/messaging', [PlatformMessagingController::class, 'update'])
        ->middleware('role:super_admin')
        ->name('platform.messaging.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
