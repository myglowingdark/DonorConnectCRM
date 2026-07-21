<?php

namespace App\Http\Controllers;

use App\Models\DonorImportBatch;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donors\DonorImportService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with('uploader')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Imports/Index', [
            'volunteers' => $volunteers,
            'batches' => $batches,
        ]);
    }

    public function store(Request $request, DonorImportService $service): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('assignDonors', Organization::findOrFail($orgId));

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xlsm', 'max:10240'],
            'assign_after_import' => ['sometimes', 'boolean'],
            'volunteer_ids' => ['nullable', 'array'],
            'volunteer_ids.*' => ['integer', 'exists:users,id'],
            'cap_per_volunteer' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $batch = $service->import(
            $orgId,
            $request->file('file'),
            $request->user(),
            [
                'assign_after_import' => $request->boolean('assign_after_import'),
                'volunteer_ids' => $validated['volunteer_ids'] ?? [],
                'cap_per_volunteer' => $validated['cap_per_volunteer'] ?? null,
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
            ->route('imports.index')
            ->with('success', $message)
            ->with('warning', $batch->errors ? implode(' ', array_slice($batch->errors, 0, 3)) : null);
    }

    public function template(): StreamedResponse
    {
        $headers = ['full_name', 'phone', 'email', 'city', 'state', 'preferred_language'];
        $sample = ['Anita Mehta', '+919811111111', 'anita@example.com', 'Mumbai', 'Maharashtra', 'hi'];

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
