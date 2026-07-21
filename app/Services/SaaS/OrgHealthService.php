<?php

namespace App\Services\SaaS;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;

class OrgHealthService
{
    /** @return array{score: int, issues: list<array{code: string, severity: string, message: string, count?: int}>} */
    public function assess(Organization $organization): array
    {
        $issues = [];

        $unassigned = $this->unassignedLeadCount($organization);
        if ($unassigned > 0) {
            $issues[] = [
                'code' => 'unassigned_leads',
                'severity' => $unassigned > 20 ? 'high' : 'medium',
                'message' => "{$unassigned} donor(s) have no active volunteer assignment.",
                'count' => $unassigned,
            ];
        }

        $staleFollowups = $this->staleFollowupCount($organization);
        if ($staleFollowups > 0) {
            $issues[] = [
                'code' => 'stale_followups',
                'severity' => $staleFollowups > 10 ? 'high' : 'medium',
                'message' => "{$staleFollowups} donor(s) have overdue follow-ups.",
                'count' => $staleFollowups,
            ];
        }

        $messaging = $organization->messagingSetting;
        if (! $messaging || ! $messaging->usesCustomSmtp()) {
            $issues[] = [
                'code' => 'smtp_missing',
                'severity' => 'medium',
                'message' => 'Custom SMTP is not configured for outbound email.',
            ];
        }

        $entitlements = app(EntitlementService::class);
        if ($entitlements->hasFeature($organization, 'razorpay') && ! $organization->razorpay_enabled) {
            $issues[] = [
                'code' => 'razorpay_disabled',
                'severity' => 'low',
                'message' => 'Razorpay payments are included in your plan but not enabled.',
            ];
        }

        if ($organization->subscription_status === 'trial' && $organization->trial_ends_at !== null) {
            $daysLeft = now()->diffInDays($organization->trial_ends_at, false);
            if ($daysLeft >= 0 && $daysLeft <= 7) {
                $issues[] = [
                    'code' => 'trial_ending',
                    'severity' => $daysLeft <= 3 ? 'high' : 'medium',
                    'message' => $daysLeft === 0
                        ? 'Your trial ends today.'
                        : "Your trial ends in {$daysLeft} day(s).",
                ];
            }
        }

        $score = max(0, 100 - collect($issues)->sum(fn (array $issue) => match ($issue['severity']) {
            'high' => 25,
            'medium' => 15,
            'low' => 5,
            default => 10,
        }));

        return [
            'score' => $score,
            'issues' => $issues,
        ];
    }

    protected function unassignedLeadCount(Organization $organization): int
    {
        $assignedDonorIds = DonorAssignment::query()
            ->forOrganization($organization->id)
            ->where('is_active', true)
            ->pluck('donor_id');

        return Donor::query()
            ->forOrganization($organization->id)
            ->whereNotIn('id', $assignedDonorIds)
            ->count();
    }

    protected function staleFollowupCount(Organization $organization): int
    {
        return Donor::query()
            ->forOrganization($organization->id)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now())
            ->count();
    }
}
