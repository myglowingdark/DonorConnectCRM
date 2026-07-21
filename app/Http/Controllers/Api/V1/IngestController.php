<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressDonorSyncService;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestController extends Controller
{
    public function donors(Request $request, WordPressDonorSyncService $sync): JsonResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'site_id' => ['nullable', 'string', 'max:64'],
            'donors' => ['required', 'array', 'min:1'],
            'donors.*.id' => ['required'],
            'donors.*.name' => ['nullable', 'string', 'max:255'],
            'donors.*.email' => ['nullable', 'email', 'max:255'],
            'donors.*.phone' => ['nullable', 'string', 'max:40'],
            'donors.*.donations' => ['nullable', 'array'],
        ]);

        $stats = $sync->ingestRecords($orgId, $validated['donors']);

        return response()->json([
            'ok' => true,
            'organization_id' => $orgId,
            'site_id' => $validated['site_id'] ?? null,
            'stats' => $stats,
        ]);
    }
}
