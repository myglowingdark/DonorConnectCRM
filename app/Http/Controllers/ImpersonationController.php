<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /** @var list<UserRole> */
    protected array $allowedRoles = [
        UserRole::OrganizationAdmin,
        UserRole::TeamLead,
        UserRole::Finance,
        UserRole::Viewer,
        UserRole::Volunteer,
    ];

    public function start(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 403, 'Cannot impersonate another super admin.');
        abort_unless(in_array($user->role, $this->allowedRoles, true), 403, 'Target role cannot be impersonated.');

        if ($request->session()->has('impersonator_id')) {
            abort(403, 'Already impersonating a user. Leave impersonation first.');
        }

        $impersonator = $request->user();
        $request->session()->put('impersonator_id', $impersonator->id);

        $orgId = $user->activeOrganizations()->orderBy('name')->value('organizations.id');
        if ($orgId) {
            OrganizationContext::set((int) $orgId);
        }

        Auth::login($user);

        $auditLogger->log(
            'impersonation.started',
            $user,
            null,
            ['impersonator_id' => $impersonator->id, 'target_id' => $user->id],
            $orgId ? (int) $orgId : null,
            $impersonator,
        );

        return redirect()->route('dashboard')->with('success', "Now viewing as {$user->name}.");
    }

    public function leave(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        abort_unless($impersonatorId, 403, 'Not impersonating anyone.');

        $impersonator = User::query()->findOrFail($impersonatorId);
        $target = $request->user();

        $auditLogger->log(
            'impersonation.ended',
            $target,
            null,
            ['impersonator_id' => $impersonator->id, 'target_id' => $target?->id],
            OrganizationContext::id(),
            $impersonator,
        );

        Auth::login($impersonator);
        OrganizationContext::ensureFor($impersonator);

        return redirect()->route('dashboard')->with('success', 'Impersonation ended.');
    }
}
