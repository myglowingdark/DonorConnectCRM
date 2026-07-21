<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class Phase2StubController extends Controller
{
    public function commissionCycles(): Response
    {
        return Inertia::render('Phase2/ComingSoon', [
            'title' => 'Monthly Commission Cycles',
            'description' => 'Calculate, review, approve, and mark commissions as paid — coming in Phase 2.',
        ]);
    }

    public function attributions(): Response
    {
        return Inertia::render('Phase2/ComingSoon', [
            'title' => 'Donation Attribution Approval',
            'description' => 'Verify which volunteer should receive credit for telecalling-attributed donations — coming in Phase 2.',
        ]);
    }

    public function emailReports(): Response
    {
        return Inertia::render('Phase2/ComingSoon', [
            'title' => 'Email Report Recipients & Schedules',
            'description' => 'Weekly statistics and monthly commission email schedules — coming in Phase 2.',
        ]);
    }

    public function myCommission(): Response
    {
        return Inertia::render('Phase2/ComingSoon', [
            'title' => 'My Commission',
            'description' => 'Personal commission statements for verified telecalling donations — coming in Phase 2.',
        ]);
    }
}
