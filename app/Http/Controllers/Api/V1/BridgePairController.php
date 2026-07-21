<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BridgePairingCode;
use App\Services\WordPress\BridgePairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BridgePairController extends Controller
{
    public function pair(Request $request, BridgePairingService $pairingService): JsonResponse
    {
        $bearer = $request->bearerToken();
        $code = $pairingService->resolveFromBearer($bearer);

        if (! $code instanceof BridgePairingCode) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid or expired pairing code.',
            ], 401);
        }

        $validated = $request->validate([
            'site_id' => ['required', 'string', 'max:64'],
            'api_key' => ['required', 'string', 'max:2000'],
            'hmac_secret' => ['required', 'string', 'max:2000'],
            'rest_base_url' => ['required', 'url', 'max:500'],
        ]);

        $result = $pairingService->claim($code, $validated);

        return response()->json([
            'ok' => true,
            'message' => 'WordPress site paired with DonorConnect.',
            'organization_id' => $result['organization_id'],
            'connection_id' => $result['connection_id'],
            'push_token' => $result['push_token'],
            'push_token_prefix' => $result['push_token_prefix'],
        ]);
    }
}
