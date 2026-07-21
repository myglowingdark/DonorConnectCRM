<?php

namespace App\Http\Controllers;

use App\Models\CommissionCycle;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\TelecallerCapacityBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarginDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $orgs = Organization::query()
            ->withCount('donors')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $org) {
                $revenue = (float) Donation::query()
                    ->forOrganization($org->id)
                    ->where('donated_at', '>=', now()->startOfMonth())
                    ->sum('amount');

                $commissionPayable = (float) CommissionCycle::query()
                    ->forOrganization($org->id)
                    ->where('period', now()->format('Y-m'))
                    ->value('payable_total') ?? 0;

                $feePercent = (float) ($org->platform_service_fee_percent ?? 0);
                $serviceFee = $revenue * ($feePercent / 100);
                $estimatedMargin = $serviceFee - $commissionPayable;

                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'revenue' => $revenue,
                    'commission_payable' => $commissionPayable,
                    'service_fee_percent' => $feePercent,
                    'estimated_service_fee' => round($serviceFee, 2),
                    'estimated_margin' => round($estimatedMargin, 2),
                    'donors_count' => $org->donors_count,
                ];
            });

        $bookings = TelecallerCapacityBooking::query()
            ->with(['organization:id,name', 'campaign:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        $idleTelecallers = User::query()
            ->where('role', 'volunteer')
            ->where('is_internal_telecaller', true)
            ->where('is_active', true)
            ->whereDoesntHave('assignments', function ($q) {
                $q->where('is_active', true)
                    ->where('assigned_at', '>=', now()->subDays(7));
            })
            ->with(['organizations:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Margin/Index', [
            'organizations' => $orgs,
            'capacityBookings' => $bookings,
            'idleTelecallers' => $idleTelecallers,
        ]);
    }
}
