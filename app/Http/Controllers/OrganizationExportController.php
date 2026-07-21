<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationExportController extends Controller
{
    public function exportDonors(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $filename = 'donors-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($orgId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'full_name', 'email', 'phone', 'city', 'state', 'donor_status', 'total_donated', 'last_contacted_at', 'tags']);

            Donor::query()
                ->forOrganization($orgId)
                ->orderBy('id')
                ->chunk(200, function ($donors) use ($handle) {
                    foreach ($donors as $donor) {
                        fputcsv($handle, [
                            $donor->id,
                            $donor->full_name,
                            $donor->email,
                            $donor->phone,
                            $donor->city,
                            $donor->state,
                            $donor->donor_status?->value ?? $donor->donor_status,
                            $donor->total_donated,
                            $donor->last_contacted_at?->toDateTimeString(),
                            implode('|', $donor->tags ?? []),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportInteractions(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $filename = 'interactions-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($orgId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'donor_id', 'volunteer_id', 'outcome', 'notes', 'contacted_at', 'pledged_amount', 'campaign_id']);

            DonorInteraction::query()
                ->forOrganization($orgId)
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->donor_id,
                            $row->volunteer_id,
                            $row->outcome instanceof \BackedEnum ? $row->outcome->value : $row->outcome,
                            $row->notes,
                            $row->contacted_at?->toDateTimeString(),
                            $row->pledged_amount,
                            $row->campaign_id,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function offboard(Request $request, Organization $organization, AuditLogger $auditLogger): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $organization->update([
            'is_active' => false,
            'subscription_status' => 'suspended',
        ]);

        $organization->delete();

        $auditLogger->log(
            'organization.offboarded',
            $organization,
            null,
            ['organization_id' => $organization->id],
            $organization->id,
            $request->user(),
        );

        return redirect()
            ->route('organizations.index')
            ->with('success', "Organization {$organization->name} has been offboarded.");
    }
}
