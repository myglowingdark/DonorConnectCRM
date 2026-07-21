<?php

use App\Http\Controllers\ApiConnectionController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttributionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CallQualityController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CapacityBookingController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CommissionCycleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\DonorImportController;
use App\Http\Controllers\EmailReportController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\IdlePoolController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\MarginDashboardController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationExportController;
use App\Http\Controllers\OrganizationSwitcherController;
use App\Http\Controllers\PlatformMessagingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QueueDialerController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::post('/razorpay/webhook/{organization}', [RazorpayController::class, 'webhook'])
    ->name('razorpay.webhook');

Route::get('/invites/{token}', [OnboardingController::class, 'showAccept'])->name('invites.show');
Route::post('/invites/{token}/accept', [OnboardingController::class, 'acceptInvite'])->name('invites.accept');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/organization/switch', [OrganizationSwitcherController::class, 'switch'])
        ->name('organization.switch');

    Route::post('/impersonation/leave', [ImpersonationController::class, 'leave'])
        ->name('impersonation.leave');
    Route::post('/impersonation/{user}', [ImpersonationController::class, 'start'])
        ->middleware('role:super_admin')
        ->name('impersonation.start');

    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::post('/two-factor/skip', [TwoFactorController::class, 'skip'])->name('two-factor.skip');

    Route::middleware('org.selected')->group(function () {
        Route::get('/donors', [DonorController::class, 'index'])->middleware('role:staff')->name('donors.index');
        Route::get('/donors/{donor}', [DonorController::class, 'show'])->name('donors.show');
        Route::post('/donors/{donor}/calls', [DonorController::class, 'logCall'])->name('donors.log-call');
        Route::post('/donors/{donor}/clear-dnc', [DonorController::class, 'clearDoNotCall'])
            ->middleware('role:admin')
            ->name('donors.clear-dnc');
        Route::post('/donors/{donor}/payment-link', [QueueDialerController::class, 'paymentLink'])
            ->middleware('feature:razorpay')
            ->name('donors.payment-link');

        Route::get('/dialer/queue', [QueueDialerController::class, 'queue'])->name('dialer.queue');
        Route::post('/dialer/skip', [QueueDialerController::class, 'skip'])->name('dialer.skip');

        Route::get('/assignments', [AssignmentController::class, 'index'])
            ->middleware('role:admin')
            ->name('assignments.index');
        Route::post('/assignments', [AssignmentController::class, 'store'])
            ->middleware('role:admin')
            ->name('assignments.store');
        Route::post('/assignments/unassign', [AssignmentController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('assignments.destroy');
        Route::post('/assignments/distribute', [AssignmentController::class, 'distribute'])
            ->middleware('role:admin')
            ->name('assignments.distribute');

        Route::get('/imports', [DonorImportController::class, 'index'])
            ->middleware('role:admin')
            ->name('imports.index');
        Route::post('/imports', [DonorImportController::class, 'store'])
            ->middleware('role:admin')
            ->name('imports.store');
        Route::get('/imports/template', [DonorImportController::class, 'template'])
            ->middleware('role:admin')
            ->name('imports.template');
        Route::get('/imports/{import}', [DonorImportController::class, 'show'])
            ->middleware('role:admin')
            ->name('imports.show');

        Route::get('/campaigns', [CampaignController::class, 'index'])
            ->middleware('role:admin')
            ->name('campaigns.index');
        Route::post('/campaigns', [CampaignController::class, 'store'])
            ->middleware('role:admin')
            ->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])
            ->middleware('role:admin')
            ->name('campaigns.show');

        Route::get('/handovers', [HandoverController::class, 'index'])
            ->middleware('role:admin')
            ->name('handovers.index');
        Route::post('/handovers', [HandoverController::class, 'store'])
            ->middleware('role:admin')
            ->name('handovers.store');

        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::post('/donors/{donor}/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('/transfers/{transfer}/accept', [TransferController::class, 'accept'])->name('transfers.accept');
        Route::post('/transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');
        Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

        // Current-workspace aliases (org already selected). Prefer organizations.sync.* for explicit org targeting.
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
        Route::post('/sync/{connection}/razorpay', [ApiConnectionController::class, 'syncRazorpay'])
            ->middleware(['role:super_admin,organization_admin', 'throttle:sync'])
            ->name('sync.razorpay');
        Route::post('/sync/{connection}/razorpay-status', [ApiConnectionController::class, 'razorpayStatus'])
            ->middleware(['role:super_admin,organization_admin', 'throttle:sync'])
            ->name('sync.razorpay-status');

        Route::get('/reports', [ReportController::class, 'index'])
            ->middleware('role:admin')
            ->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])
            ->middleware('role:admin')
            ->name('reports.export');

        Route::get('/insights', [InsightsController::class, 'index'])
            ->middleware('role:staff')
            ->name('insights.index');

        Route::get('/audit', [AuditLogController::class, 'index'])
            ->middleware('role:admin')
            ->name('audit.index');

        Route::get('/onboarding', [OnboardingController::class, 'show'])
            ->middleware('role:admin')
            ->name('onboarding.show');
        Route::post('/onboarding/invites', [OnboardingController::class, 'createInvite'])
            ->middleware('role:admin')
            ->name('onboarding.invites.store');

        Route::get('/billing', [BillingController::class, 'index'])
            ->middleware('role:admin')
            ->name('billing.index');
        Route::post('/billing/invoices', [BillingController::class, 'createInvoice'])
            ->middleware('role:super_admin')
            ->name('billing.invoices.store');
        Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'payInvoice'])
            ->middleware('role:admin')
            ->name('billing.invoices.pay');
        Route::put('/billing/white-label', [BillingController::class, 'updateWhiteLabel'])
            ->middleware('role:admin')
            ->name('billing.white-label.update');

        Route::get('/exports/donors', [OrganizationExportController::class, 'exportDonors'])
            ->middleware('role:admin')
            ->name('exports.donors');
        Route::get('/exports/interactions', [OrganizationExportController::class, 'exportInteractions'])
            ->middleware('role:admin')
            ->name('exports.interactions');

        Route::get('/capacity-bookings', [CapacityBookingController::class, 'index'])
            ->middleware(['role:admin', 'feature:capacity_booking'])
            ->name('capacity.index');
        Route::post('/capacity-bookings', [CapacityBookingController::class, 'store'])
            ->middleware(['role:admin', 'feature:capacity_booking'])
            ->name('capacity.store');
        Route::post('/capacity-bookings/{booking}/approve', [CapacityBookingController::class, 'approve'])
            ->middleware('role:super_admin')
            ->name('capacity.approve');
        Route::post('/capacity-bookings/{booking}/reject', [CapacityBookingController::class, 'reject'])
            ->middleware('role:super_admin')
            ->name('capacity.reject');

        Route::get('/margin', [MarginDashboardController::class, 'index'])
            ->middleware('role:super_admin')
            ->name('margin.index');

        Route::post('/interactions/{interaction}/quality', [CallQualityController::class, 'store'])
            ->middleware('role:admin')
            ->name('call-quality.store');
        Route::post('/commission-holds', [CallQualityController::class, 'createHold'])
            ->middleware('role:super_admin')
            ->name('commission-holds.store');
        Route::post('/commission-holds/{hold}/release', [CallQualityController::class, 'releaseHold'])
            ->middleware('role:super_admin')
            ->name('commission-holds.release');

        Route::get('/api-tokens', [ApiTokenController::class, 'index'])
            ->middleware(['role:admin', 'feature:api'])
            ->name('api-tokens.index');
        Route::post('/api-tokens', [ApiTokenController::class, 'store'])
            ->middleware(['role:admin', 'feature:api'])
            ->name('api-tokens.store');
        Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])
            ->middleware(['role:admin', 'feature:api'])
            ->name('api-tokens.destroy');

        Route::get('/webhooks', [WebhookController::class, 'index'])
            ->middleware(['role:admin', 'feature:webhooks'])
            ->name('webhooks.index');
        Route::post('/webhooks', [WebhookController::class, 'store'])
            ->middleware(['role:admin', 'feature:webhooks'])
            ->name('webhooks.store');
        Route::put('/webhooks/{webhook}', [WebhookController::class, 'update'])
            ->middleware(['role:admin', 'feature:webhooks'])
            ->name('webhooks.update');
        Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])
            ->middleware(['role:admin', 'feature:webhooks'])
            ->name('webhooks.destroy');

        Route::get('/messaging', [MessagingController::class, 'settings'])
            ->middleware('role:admin')
            ->name('messaging.settings');
        Route::put('/messaging', [MessagingController::class, 'updateSettings'])
            ->middleware('role:admin')
            ->name('messaging.settings.update');
        Route::get('/messaging/templates', [MessagingController::class, 'templates'])
            ->middleware('role:admin')
            ->name('messaging.templates');
        Route::post('/messaging/templates', [MessagingController::class, 'storeTemplate'])
            ->middleware('role:admin')
            ->name('messaging.templates.store');
        Route::put('/messaging/templates/{template}', [MessagingController::class, 'updateTemplate'])
            ->middleware('role:admin')
            ->name('messaging.templates.update');
        Route::delete('/messaging/templates/{template}', [MessagingController::class, 'destroyTemplate'])
            ->middleware('role:admin')
            ->name('messaging.templates.destroy');

        Route::post('/donors/{donor}/messages', [MessagingController::class, 'send'])
            ->name('donors.messages.send');
        Route::post('/donors/{donor}/razorpay-order', [RazorpayController::class, 'createOrder'])
            ->middleware(['role:admin', 'feature:razorpay'])
            ->name('donors.razorpay.order');

        Route::get('/commissions', [CommissionController::class, 'settings'])
            ->middleware('role:admin')
            ->name('commissions.settings');
        Route::put('/commissions', [CommissionController::class, 'updateSettings'])
            ->middleware('role:admin')
            ->name('commissions.settings.update');

        Route::get('/commission-cycles', [CommissionCycleController::class, 'index'])
            ->middleware('role:admin')
            ->name('commissions.cycles');
        Route::post('/commission-cycles/calculate', [CommissionCycleController::class, 'calculate'])
            ->middleware('role:admin')
            ->name('commissions.cycles.calculate');
        Route::get('/commission-cycles/{cycle}', [CommissionCycleController::class, 'show'])
            ->middleware('role:admin')
            ->name('commissions.cycles.show');
        Route::post('/commission-cycles/{cycle}/approve', [CommissionCycleController::class, 'approve'])
            ->middleware('role:admin')
            ->name('commissions.cycles.approve');
        Route::post('/commission-cycles/{cycle}/pay', [CommissionCycleController::class, 'markPaid'])
            ->middleware('role:admin')
            ->name('commissions.cycles.pay');

        Route::get('/attributions', [AttributionController::class, 'index'])
            ->middleware('role:admin')
            ->name('attributions.index');
        Route::post('/attributions/{attribution}/approve', [AttributionController::class, 'approve'])
            ->middleware('role:admin')
            ->name('attributions.approve');
        Route::post('/attributions/{attribution}/reject', [AttributionController::class, 'reject'])
            ->middleware('role:admin')
            ->name('attributions.reject');

        Route::get('/email-reports', [EmailReportController::class, 'index'])
            ->middleware('role:admin')
            ->name('email-reports.index');
        Route::post('/email-reports/recipients', [EmailReportController::class, 'storeRecipient'])
            ->middleware('role:admin')
            ->name('email-reports.recipients.store');
        Route::put('/email-reports/recipients/{recipient}', [EmailReportController::class, 'updateRecipient'])
            ->middleware('role:admin')
            ->name('email-reports.recipients.update');
        Route::delete('/email-reports/recipients/{recipient}', [EmailReportController::class, 'destroyRecipient'])
            ->middleware('role:admin')
            ->name('email-reports.recipients.destroy');
        Route::post('/email-reports/schedules', [EmailReportController::class, 'storeSchedule'])
            ->middleware('role:admin')
            ->name('email-reports.schedules.store');
        Route::put('/email-reports/schedules/{schedule}', [EmailReportController::class, 'updateSchedule'])
            ->middleware('role:admin')
            ->name('email-reports.schedules.update');
        Route::delete('/email-reports/schedules/{schedule}', [EmailReportController::class, 'destroySchedule'])
            ->middleware('role:admin')
            ->name('email-reports.schedules.destroy');

        Route::get('/my-commission', [CommissionCycleController::class, 'mine'])
            ->name('commissions.mine');
    });

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role:admin')
        ->name('users.index');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('role:admin')
        ->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('role:admin')
        ->name('users.update');

    Route::get('/organization/profile', [OrganizationController::class, 'current'])
        ->middleware('role:admin')
        ->name('organization.profile');
    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('organizations.index');
    Route::get('/organizations/create', [OrganizationController::class, 'create'])
        ->middleware('role:super_admin')
        ->name('organizations.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->middleware('role:super_admin')
        ->name('organizations.store');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])
        ->middleware('role:admin')
        ->name('organizations.show');
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])
        ->middleware('role:super_admin')
        ->name('organizations.edit');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])
        ->middleware('role:super_admin')
        ->name('organizations.update');
    Route::put('/organizations/{organization}/razorpay', [OrganizationController::class, 'updateRazorpay'])
        ->middleware('role:admin')
        ->name('organizations.razorpay.update');

    Route::middleware('role:super_admin,organization_admin')->group(function () {
        Route::get('/organizations/{organization}/sync', [ApiConnectionController::class, 'editForOrganization'])
            ->name('organizations.sync.edit');
        Route::post('/organizations/{organization}/sync', [ApiConnectionController::class, 'storeForOrganization'])
            ->middleware('throttle:sync')
            ->name('organizations.sync.store');
        Route::put('/organizations/{organization}/sync/{connection}', [ApiConnectionController::class, 'updateForOrganization'])
            ->middleware('throttle:sync')
            ->name('organizations.sync.update');
        Route::post('/organizations/{organization}/sync/{connection}/test', [ApiConnectionController::class, 'testForOrganization'])
            ->middleware('throttle:sync')
            ->name('organizations.sync.test');
        Route::post('/organizations/{organization}/sync/{connection}/run', [ApiConnectionController::class, 'syncForOrganization'])
            ->middleware('throttle:sync')
            ->name('organizations.sync.run');
        Route::post('/organizations/{organization}/sync/{connection}/razorpay', [ApiConnectionController::class, 'syncRazorpayForOrganization'])
            ->middleware('throttle:sync')
            ->name('organizations.sync.razorpay');
        Route::post('/organizations/{organization}/sync/{connection}/razorpay-status', [ApiConnectionController::class, 'razorpayStatusForOrganization'])
            ->middleware('throttle:sync')
            ->name('organizations.sync.razorpay-status');
    });

    Route::post('/organizations/{organization}/offboard', [OrganizationExportController::class, 'offboard'])
        ->middleware('role:super_admin')
        ->name('organizations.offboard');
    Route::put('/organizations/{organization}/plan', [BillingController::class, 'assignPlan'])
        ->middleware('role:super_admin')
        ->name('organizations.plan.update');

    Route::get('/idle-pool', [IdlePoolController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('idle-pool.index');
    Route::post('/idle-pool/{user}/reassign', [IdlePoolController::class, 'reassign'])
        ->middleware('role:super_admin')
        ->name('idle-pool.reassign');

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
