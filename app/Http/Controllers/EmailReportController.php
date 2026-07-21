<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ReportRecipient;
use App\Models\ReportSchedule;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        return Inertia::render('EmailReports/Index', [
            'recipients' => ReportRecipient::query()->forOrganization($orgId)->orderBy('name')->get(),
            'schedules' => ReportSchedule::query()->forOrganization($orgId)->orderBy('type')->get(),
        ]);
    }

    public function storeRecipient(Request $request): RedirectResponse
    {
        $orgId = $this->orgId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        ReportRecipient::create([
            ...$data,
            'organization_id' => $orgId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Recipient added.');
    }

    public function updateRecipient(Request $request, ReportRecipient $recipient): RedirectResponse
    {
        $this->guardRecipient($request, $recipient);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $recipient->update([
            ...$data,
            'is_active' => $request->boolean('is_active', $recipient->is_active),
        ]);

        return back()->with('success', 'Recipient updated.');
    }

    public function destroyRecipient(Request $request, ReportRecipient $recipient): RedirectResponse
    {
        $this->guardRecipient($request, $recipient);
        $recipient->delete();

        return back()->with('success', 'Recipient removed.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $orgId = $this->orgId($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(['weekly_stats', 'monthly_commission_summary'])],
            'frequency' => ['required', Rule::in(['weekly', 'monthly'])],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'send_at' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        ReportSchedule::create([
            ...$data,
            'organization_id' => $orgId,
            'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
            'send_at' => ($data['send_at'] ?? '09:00').':00',
            'is_active' => $request->boolean('is_active', true),
            'requires_approval' => $request->boolean('requires_approval', false),
        ]);

        return back()->with('success', 'Schedule saved.');
    }

    public function updateSchedule(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->guardSchedule($request, $schedule);

        $data = $request->validate([
            'type' => ['required', Rule::in(['weekly_stats', 'monthly_commission_summary'])],
            'frequency' => ['required', Rule::in(['weekly', 'monthly'])],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'send_at' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['send_at'])) {
            $data['send_at'] = $data['send_at'].':00';
        }

        $schedule->update([
            ...$data,
            'is_active' => $request->boolean('is_active', $schedule->is_active),
            'requires_approval' => $request->boolean('requires_approval', $schedule->requires_approval),
        ]);

        return back()->with('success', 'Schedule updated.');
    }

    public function destroySchedule(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->guardSchedule($request, $schedule);
        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }

    protected function orgId(Request $request): int
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        return (int) $orgId;
    }

    protected function guardRecipient(Request $request, ReportRecipient $recipient): void
    {
        $orgId = $this->orgId($request);
        abort_unless((int) $recipient->organization_id === $orgId, 403);
    }

    protected function guardSchedule(Request $request, ReportSchedule $schedule): void
    {
        $orgId = $this->orgId($request);
        abort_unless((int) $schedule->organization_id === $orgId, 403);
    }
}
