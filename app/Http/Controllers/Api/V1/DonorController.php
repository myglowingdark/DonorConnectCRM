<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $donors = Donor::query()
            ->forOrganization($orgId)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->paginate(min(100, $request->integer('per_page', 25)));

        return response()->json($donors);
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
            'tags' => ['nullable', 'array'],
        ]);

        $organization = \App\Models\Organization::query()->findOrFail($orgId);
        $organization->assertCanAcceptNewDonors();

        $donor = Donor::create([
            'organization_id' => $orgId,
            ...$validated,
        ]);

        return response()->json(['data' => $donor], 201);
    }
}
