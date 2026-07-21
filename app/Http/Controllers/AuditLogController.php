<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $query = AuditLog::query()->with('actor:id,name,email,role');

        if ($request->user()->isSuperAdmin() && $request->filled('organization_id')) {
            $query->forOrganization($request->integer('organization_id'));
        } else {
            $query->forOrganization($orgId);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action').'%');
        }

        $logs = $query->latest('id')->paginate(30)->withQueryString();

        $organizations = $request->user()->isSuperAdmin()
            ? Organization::query()->orderBy('name')->get(['id', 'name'])
            : [];

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['organization_id', 'action']),
            'organizations' => $organizations,
        ]);
    }
}
