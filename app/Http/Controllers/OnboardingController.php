<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\DonorImportBatch;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\OrganizationMessagingSetting;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);

        $hasVolunteers = User::query()
            ->where('role', UserRole::Volunteer->value)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
            ->exists();

        $hasImport = DonorImportBatch::query()->forOrganization($orgId)->exists();

        $hasCallLogged = DonorInteraction::query()
            ->forOrganization($orgId)
            ->where('interaction_type', 'call')
            ->exists();

        $messaging = OrganizationMessagingSetting::query()
            ->where('organization_id', $orgId)
            ->first();

        $messagingConfigured = $messaging && (
            $messaging->usesCustomSmtp()
            || filled($messaging->whatsapp_api_key)
            || filled($messaging->sms_api_key)
        );

        $invites = OrganizationInvite::query()
            ->forOrganization($orgId)
            ->whereNull('accepted_at')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Onboarding/Index', [
            'checklist' => [
                'has_volunteers' => $hasVolunteers,
                'has_import' => $hasImport,
                'has_call_logged' => $hasCallLogged,
                'messaging_configured' => $messagingConfigured,
            ],
            'invites' => $invites,
            'organization' => $organization->only(['id', 'name', 'onboarded_at']),
        ]);
    }

    public function createInvite(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:volunteer,organization_admin,team_lead,finance,viewer'],
        ]);

        OrganizationInvite::create([
            'organization_id' => $orgId,
            'invited_by' => $request->user()->id,
            'email' => strtolower($validated['email']),
            'role' => $validated['role'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('success', 'Invite created. Share the accept link with your teammate.');
    }

    public function showAccept(string $token): Response
    {
        $invite = OrganizationInvite::query()->where('token', $token)->firstOrFail();

        abort_if($invite->accepted_at !== null, 410, 'This invite has already been accepted.');
        abort_if($invite->isExpired(), 410, 'This invite has expired.');

        return Inertia::render('Onboarding/AcceptInvite', [
            'invite' => [
                'email' => $invite->email,
                'role' => $invite->role,
                'organization' => $invite->organization()->first(['id', 'name']),
                'token' => $token,
            ],
        ]);
    }

    public function acceptInvite(Request $request, string $token): RedirectResponse
    {
        $invite = OrganizationInvite::query()->where('token', $token)->firstOrFail();

        abort_if($invite->accepted_at !== null, 410);
        abort_if($invite->isExpired(), 410);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => $invite->email],
            [
                'name' => $validated['name'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::from($invite->role),
                'is_active' => true,
            ],
        );

        $user->organizations()->syncWithoutDetaching([
            $invite->organization_id => ['is_active' => true],
        ]);

        $invite->update(['accepted_at' => now()]);

        auth()->login($user);
        OrganizationContext::set($invite->organization_id);

        return redirect()->route('onboarding.show')->with('success', 'Welcome! Your account is ready.');
    }
}
