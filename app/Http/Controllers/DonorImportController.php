<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Donor;
use App\Models\DonorImportBatch;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donors\DonorImportService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonorImportController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $volunteers = User::query()
            ->where('role', 'volunteer')
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn ($q) => $q->where('organizations.id', $orgId)->where('organization_user.is_active', true)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'languages']);

        $batches = DonorImportBatch::query()
            ->forOrganization($orgId)
            ->with(['uploader', 'campaign:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        $campaigns = Campaign::query()
            ->forOrganization($orgId)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return Inertia::render('Imports/Index', [
            'volunteers' => $volunteers,
            'batches' => $batches,
            'campaigns' => $campaigns,
        ]);
    }

    public function show(Request $request, DonorImportBatch $import): Response
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId && $import->organization_id === $orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $import->load(['uploader:id,name', 'campaign:id,name']);

        $donors = Donor::query()
            ->forOrganization($orgId)
            ->where(function ($q) use ($import) {
                $q->where('import_batch_id', $import->id);
                if (! empty($import->donor_ids)) {
                    $q->orWhereIn('id', $import->donor_ids);
                }
            })
            ->with(['activeAssignment.volunteer:id,name', 'campaign:id,name'])
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Imports/Show', [
            'batch' => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'rows_total' => $import->rows_total,
                'rows_created' => $import->rows_created,
                'rows_updated' => $import->rows_updated,
                'rows_skipped' => $import->rows_skipped,
                'rows_assigned' => $import->rows_assigned,
                'tags' => $import->tags ?? [],
                'errors' => $import->errors ?? [],
                'created_at' => $import->created_at,
                'uploader' => $import->uploader,
                'campaign' => $import->campaign,
            ],
            'donors' => $donors,
        ]);
    }

    public function store(Request $request, DonorImportService $service): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $validated = $request->validate([
            'file' => [
                'required',
                File::types(['csv', 'txt', 'xlsx', 'xlsm'])
                    ->max(10 * 1024),
            ],
            'assign_after_import' => ['sometimes', 'boolean'],
            'volunteer_ids' => ['nullable', 'array'],
            'volunteer_ids.*' => ['integer', 'exists:users,id'],
            'cap_per_volunteer' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'new_campaign_name' => ['nullable', 'string', 'max:120'],
        ]);

        $batch = $service->import(
            $orgId,
            $request->file('file'),
            $request->user(),
            [
                'assign_after_import' => $request->boolean('assign_after_import'),
                'volunteer_ids' => $validated['volunteer_ids'] ?? [],
                'cap_per_volunteer' => $validated['cap_per_volunteer'] ?? null,
                'tags' => $validated['tags'] ?? null,
                'campaign_id' => $validated['campaign_id'] ?? null,
                'new_campaign_name' => $validated['new_campaign_name'] ?? null,
            ],
        );

        $message = "Import done: {$batch->rows_created} created, {$batch->rows_updated} updated";
        if ($batch->rows_assigned) {
            $message .= ", {$batch->rows_assigned} assigned";
        }
        if ($batch->rows_skipped) {
            $message .= ", {$batch->rows_skipped} skipped";
        }
        $message .= '.';

        return redirect()
            ->route('imports.show', $batch)
            ->with('success', $message)
            ->with('warning', $batch->errors ? implode(' ', array_slice($batch->errors, 0, 3)) : null);
    }

    public function template(): StreamedResponse
    {
        $headers = ['full_name', 'phone', 'email', 'city', 'state', 'preferred_language', 'tags'];
        $sample = ['Anita Mehta', '+919811111111', 'anita@example.com', 'Mumbai', 'Maharashtra', 'hi', 'vip|warm'];

        return response()->streamDownload(function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        }, 'donor-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
