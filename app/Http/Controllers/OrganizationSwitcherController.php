<?php

namespace App\Http\Controllers;

use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationSwitcherController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $user = $request->user();
        $organizationId = (int) $request->integer('organization_id');

        if (! $user->belongsToOrganization($organizationId)) {
            abort(403, 'You are not assigned to this organization.');
        }

        OrganizationContext::set($organizationId);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Organization switched.');
    }
}
