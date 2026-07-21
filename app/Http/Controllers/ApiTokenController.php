<?php

namespace App\Http\Controllers;

use App\Models\OrganizationApiToken;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $tokens = OrganizationApiToken::query()
            ->forOrganization($orgId)
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (OrganizationApiToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'token_prefix' => $token->token_prefix,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Api/Tokens', [
            'tokens' => $tokens,
            'plaintextToken' => session('api_token_plaintext'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $plaintext = 'dc_'.Str::random(40);
        $prefix = substr($plaintext, 0, 12);

        OrganizationApiToken::create([
            'organization_id' => $orgId,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'token_hash' => hash('sha256', $plaintext),
            'token_prefix' => $prefix,
            'abilities' => $validated['abilities'] ?? ['donors:read', 'donors:write', 'donations:read'],
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return back()->with([
            'success' => 'API token created. Copy it now — it will not be shown again.',
            'api_token_plaintext' => $plaintext,
        ]);
    }

    public function destroy(Request $request, OrganizationApiToken $token): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($token->organization_id === OrganizationContext::id(), 403);

        $token->delete();

        return back()->with('success', 'API token revoked.');
    }
}
