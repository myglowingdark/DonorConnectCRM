<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IdlePoolController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $telecallers = User::query()
            ->where('role', 'volunteer')
            ->where('is_internal_telecaller', true)
            ->where('is_active', true)
            ->with(['organizations:id,name'])
            ->orderBy('name')
            ->get();

        $organizations = Organization::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('IdlePool/Index', [
            'telecallers' => $telecallers,
            'organizations' => $organizations,
        ]);
    }

    public function reassign(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless($user->is_internal_telecaller && $user->isVolunteer(), 422);

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'detach_organization_ids' => ['nullable', 'array'],
            'detach_organization_ids.*' => ['integer', 'exists:organizations,id'],
        ]);

        foreach ($validated['detach_organization_ids'] ?? [] as $orgId) {
            $user->organizations()->updateExistingPivot($orgId, ['is_active' => false]);
        }

        $user->organizations()->syncWithoutDetaching([
            $validated['organization_id'] => ['is_active' => true],
        ]);

        $auditLogger->log(
            'telecaller.reassigned',
            $user,
            null,
            [
                'organization_id' => $validated['organization_id'],
                'detached' => $validated['detach_organization_ids'] ?? [],
            ],
            (int) $validated['organization_id'],
            $request->user(),
        );

        return back()->with('success', "{$user->name} reassigned.");
    }
}
