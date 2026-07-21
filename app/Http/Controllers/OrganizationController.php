<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount(['donors', 'users'])
            ->with('apiConnection')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('Organizations/Form', [
            'organization' => null,
        ]);
    }

    public function store(StoreOrganizationRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['brand_color'] = $data['brand_color'] ?? '#1e3a8a';
        $data['timezone'] = $data['timezone'] ?? 'Asia/Kolkata';
        $data['currency'] = $data['currency'] ?? 'INR';

        if (array_key_exists('donors_limit', $data) && $data['donors_limit'] === '') {
            $data['donors_limit'] = null;
        }

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo']);

        $organization = Organization::create($data);

        $auditLogger->log('organization.created', $organization, null, $organization->toArray(), $organization->id);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created.');
    }

    public function edit(Organization $organization): Response
    {
        $this->authorize('update', $organization);

        return Inertia::render('Organizations/Form', [
            'organization' => $organization,
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $old = $organization->only(['name', 'slug', 'brand_color', 'timezone', 'currency', 'is_active', 'donors_limit', 'logo_path']);
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if (array_key_exists('donors_limit', $data) && $data['donors_limit'] === '') {
            $data['donors_limit'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo']);
        $organization->update($data);

        $auditLogger->log('organization.updated', $organization, $old, $organization->fresh()->toArray(), $organization->id);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization updated.');
    }
}
