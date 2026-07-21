<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Languages;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $orgId = OrganizationContext::id();
        $accessibleOrgIds = $actor->isSuperAdmin()
            ? Organization::query()->pluck('id')->all()
            : $actor->accessibleOrganizationIds();

        $query = User::query()
            ->with([
                'organizations' => function ($q) use ($actor, $accessibleOrgIds) {
                    // Org admins must not see memberships outside their organizations.
                    if (! $actor->isSuperAdmin()) {
                        $q->whereIn('organizations.id', $accessibleOrgIds);
                    }
                },
            ])
            ->orderBy('name');

        if (! $actor->isSuperAdmin()) {
            abort_unless($orgId, 403);
            $query->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId));
        } elseif ($request->filled('organization_id')) {
            $query->whereHas(
                'organizations',
                fn ($q) => $q->where('organizations.id', $request->integer('organization_id'))
            );
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20)->withQueryString();

        $users->getCollection()->transform(function (User $user) use ($orgId) {
            $user->setAttribute('donor_count', $user->assignments()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('is_active', true)
                ->count());

            return $user;
        });

        $manageableOrganizations = $actor->isSuperAdmin()
            ? Organization::query()->orderBy('name')->get(['id', 'name', 'brand_color'])
            : Organization::query()
                ->whereIn('id', $accessibleOrgIds)
                ->orderBy('name')
                ->get(['id', 'name', 'brand_color']);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'organization_id']),
            'roles' => collect(UserRole::cases())
                ->when(
                    ! $actor->isSuperAdmin(),
                    fn ($roles) => $roles->reject(fn (UserRole $role) => $role === UserRole::SuperAdmin)
                )
                ->values()
                ->map(fn (UserRole $r) => [
                    'value' => $r->value,
                    'label' => $r->label(),
                ]),
            'allOrganizations' => $manageableOrganizations,
            'canManageAllOrganizations' => $actor->isSuperAdmin(),
            'languages' => Languages::forSelect(),
        ]);
    }

    public function store(StoreUserRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $organizationIds = $this->filterOrganizationIds($request->user(), $data['organization_ids']);

        abort_if(empty($organizationIds), 422, 'Select at least one organization you can manage.');

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'languages' => $data['languages'] ?? [],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->syncOrganizations($request->user(), $user, $organizationIds);

        $auditLogger->log('user.created', $user, null, [
            'role' => $user->role->value,
            'organization_ids' => $organizationIds,
        ]);

        return back()->with('success', 'Team member added.');
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $organizationIds = $this->filterOrganizationIds($request->user(), $data['organization_ids']);
        $old = $user->only(['name', 'email', 'phone', 'role', 'is_active']);

        // Org admins cannot change someone into / out of super admin.
        if (! $request->user()->isSuperAdmin() && $user->isSuperAdmin()) {
            abort(403);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'languages' => $data['languages'] ?? [],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? $user->is_active,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $this->syncOrganizations($request->user(), $user, $organizationIds);

        $auditLogger->log('user.updated', $user, $old, [
            'role' => $user->role->value,
            'organization_ids' => $organizationIds,
        ]);

        return back()->with('success', 'Team member updated.');
    }

    /**
     * @param  array<int>  $ids
     * @return array<int>
     */
    protected function filterOrganizationIds(User $actor, array $ids): array
    {
        $ids = array_map('intval', $ids);

        if ($actor->isSuperAdmin()) {
            return array_values(array_unique($ids));
        }

        return array_values(array_intersect($ids, $actor->accessibleOrganizationIds()));
    }

    /**
     * Sync org memberships without letting org admins strip other-org assignments.
     *
     * @param  array<int>  $organizationIds
     */
    protected function syncOrganizations(User $actor, User $user, array $organizationIds): void
    {
        if ($actor->isSuperAdmin()) {
            $user->organizations()->sync(
                collect($organizationIds)->mapWithKeys(fn ($id) => [$id => ['is_active' => true]])->all()
            );

            return;
        }

        $allowed = $actor->accessibleOrganizationIds();
        $incoming = array_values(array_intersect($organizationIds, $allowed));

        // Keep memberships the org admin cannot see/manage.
        $preserved = $user->organizations()
            ->whereNotIn('organizations.id', $allowed)
            ->pluck('organizations.id')
            ->all();

        $final = array_values(array_unique(array_merge($preserved, $incoming)));

        abort_if(empty($final), 422, 'A user must remain in at least one organization.');

        $user->organizations()->sync(
            collect($final)->mapWithKeys(fn ($id) => [$id => ['is_active' => true]])->all()
        );
    }
}
