<?php

namespace App\Http\Controllers;

use App\Enums\TransferStatus;
use App\Http\Requests\Transfers\RespondTransferRequest;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Models\Donor;
use App\Models\DonorTransferRequest;
use App\Models\User;
use App\Services\Donors\TransferService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $user = $request->user();

        $query = DonorTransferRequest::query()
            ->forOrganization($orgId)
            ->with(['donor', 'fromVolunteer', 'toVolunteer', 'requester', 'responder'])
            ->latest();

        if ($user->isVolunteer()) {
            $query->where(function ($q) use ($user) {
                $q->where('from_volunteer_id', $user->id)
                    ->orWhere('to_volunteer_id', $user->id)
                    ->orWhere('requested_by', $user->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return Inertia::render('Transfers/Index', [
            'transfers' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['status']),
            'statuses' => collect(TransferStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function store(
        StoreTransferRequest $request,
        Donor $donor,
        TransferService $service,
    ): RedirectResponse {
        $this->authorize('view', $donor);

        $donor->loadMissing('activeAssignment.volunteer');

        $user = $request->user();
        $fromVolunteer = $user->isVolunteer()
            ? $user
            : ($donor->activeAssignment?->volunteer ?? $user);

        if ($user->isVolunteer()) {
            abort_unless(
                $donor->activeAssignment?->volunteer_id === $user->id,
                403,
                'You can only transfer donors assigned to you.',
            );
        }

        $toVolunteer = User::query()->findOrFail($request->integer('to_volunteer_id'));

        $service->request(
            $donor,
            $fromVolunteer,
            $toVolunteer,
            $user,
            $request->string('reason')->toString() ?: null,
        );

        return back()->with('success', 'Transfer request sent. Waiting for acceptance.');
    }

    public function accept(
        RespondTransferRequest $request,
        DonorTransferRequest $transfer,
        TransferService $service,
    ): RedirectResponse {
        $this->authorizeOrg($request, $transfer);
        $service->accept($transfer, $request->user(), $request->string('response_note')->toString() ?: null);

        return back()->with('success', 'Transfer accepted. Donor is now in your queue.');
    }

    public function reject(
        RespondTransferRequest $request,
        DonorTransferRequest $transfer,
        TransferService $service,
    ): RedirectResponse {
        $this->authorizeOrg($request, $transfer);
        $service->reject($transfer, $request->user(), $request->string('response_note')->toString() ?: null);

        return back()->with('success', 'Transfer declined.');
    }

    public function cancel(
        Request $request,
        DonorTransferRequest $transfer,
        TransferService $service,
    ): RedirectResponse {
        $this->authorizeOrg($request, $transfer);
        $service->cancel($transfer, $request->user());

        return back()->with('success', 'Transfer cancelled.');
    }

    protected function authorizeOrg(Request $request, DonorTransferRequest $transfer): void
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId === $transfer->organization_id, 403);
        abort_unless($request->user()->belongsToOrganization($transfer->organization_id), 403);
    }
}
