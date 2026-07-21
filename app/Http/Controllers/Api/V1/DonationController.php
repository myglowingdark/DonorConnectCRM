<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $donations = Donation::query()
            ->forOrganization($orgId)
            ->with('donor:id,full_name,email,phone')
            ->when($request->filled('from'), fn ($q) => $q->where('donated_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('donated_at', '<=', $request->date('to')->endOfDay()))
            ->latest('donated_at')
            ->paginate(min(100, $request->integer('per_page', 25)));

        return response()->json($donations);
    }
}
