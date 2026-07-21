<?php

namespace App\Services\Commissions;

use App\Enums\AttributionStatus;
use App\Enums\CommissionCycleStatus;
use App\Models\CommissionCycle;
use App\Models\CommissionLineItem;
use App\Models\CommissionSetting;
use App\Models\DonationAttribution;
use App\Models\PlatformCommissionSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function settingsFor(int $organizationId): CommissionSetting
    {
        return CommissionSetting::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            PlatformCommissionSetting::current()->defaultsForOrganization()
        );
    }

    public function calculate(int $organizationId, string $period, User $actor): CommissionCycle
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw ValidationException::withMessages([
                'period' => 'Period must be YYYY-MM.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $settings = $this->settingsFor($organizationId);

        $attributions = DonationAttribution::query()
            ->forOrganization($organizationId)
            ->where('status', AttributionStatus::Approved)
            ->whereHas('donation', fn ($q) => $q->whereBetween('donated_at', [$start, $end]))
            ->with(['donation', 'volunteer'])
            ->get();

        $orgByVolunteer = [];
        $internalByVolunteer = [];
        $verifiedTotal = 0.0;
        $orgVerified = 0.0;
        $internalVerified = 0.0;

        foreach ($attributions as $attr) {
            $vid = (int) $attr->volunteer_id;
            $amount = (float) ($attr->donation?->amount ?? 0);
            $verifiedTotal += $amount;
            $isInternal = (bool) ($attr->volunteer?->is_internal_telecaller);

            if ($isInternal) {
                $internalVerified += $amount;
                $internalByVolunteer[$vid] = ($internalByVolunteer[$vid] ?? 0) + $amount;
            } else {
                $orgVerified += $amount;
                $orgByVolunteer[$vid] = ($orgByVolunteer[$vid] ?? 0) + $amount;
            }
        }

        $orgSharedPool = 0.0;
        if ($settings->shared_enabled && $settings->shared_percent > 0) {
            $orgSharedPool = round($orgVerified * ((float) $settings->shared_percent) / 100, 2);
        }
        $orgContributors = count(array_filter($orgByVolunteer, fn ($t) => $t > 0));
        $orgSharedEach = ($orgSharedPool > 0 && $orgContributors > 0)
            ? round($orgSharedPool / $orgContributors, 2)
            : 0.0;

        $internalSharedPool = 0.0;
        if ($settings->internal_shared_enabled && $settings->internal_shared_percent > 0) {
            $internalSharedPool = round($internalVerified * ((float) $settings->internal_shared_percent) / 100, 2);
        }
        $internalContributors = count(array_filter($internalByVolunteer, fn ($t) => $t > 0));
        $internalSharedEach = ($internalSharedPool > 0 && $internalContributors > 0)
            ? round($internalSharedPool / $internalContributors, 2)
            : 0.0;

        $sharedPool = round($orgSharedPool + $internalSharedPool, 2);

        return DB::transaction(function () use (
            $organizationId,
            $period,
            $settings,
            $orgByVolunteer,
            $internalByVolunteer,
            $verifiedTotal,
            $sharedPool,
            $orgSharedEach,
            $internalSharedEach,
            $actor,
        ) {
            $cycle = CommissionCycle::query()->firstOrNew([
                'organization_id' => $organizationId,
                'period' => $period,
            ]);

            if ($cycle->exists && $cycle->status === CommissionCycleStatus::Paid) {
                throw ValidationException::withMessages([
                    'period' => 'Cannot recalculate a paid cycle.',
                ]);
            }

            $cycle->fill([
                'status' => CommissionCycleStatus::Draft,
                'verified_donation_total' => $verifiedTotal,
                'shared_pool' => $sharedPool,
                'approved_at' => null,
                'paid_at' => null,
            ]);
            $cycle->save();
            $cycle->lineItems()->delete();

            $individualTotal = 0.0;
            $payableTotal = 0.0;

            $addLine = function (
                int $volunteerId,
                float $attributed,
                bool $internal,
                float $sharedEach,
            ) use ($cycle, $organizationId, $settings, &$individualTotal, &$payableTotal) {
                $enabled = $internal ? $settings->internal_individual_enabled : $settings->individual_enabled;
                $rate = $enabled ? $settings->rateForVolunteer($volunteerId, $internal) : 0.0;
                $individual = $enabled ? round($attributed * $rate / 100, 2) : 0.0;
                $shared = $attributed > 0 ? $sharedEach : 0.0;
                $final = round($individual + $shared, 2);

                CommissionLineItem::create([
                    'commission_cycle_id' => $cycle->id,
                    'organization_id' => $organizationId,
                    'volunteer_id' => $volunteerId,
                    'attributed_donation_total' => $attributed,
                    'individual_rate' => $rate,
                    'individual_commission' => $individual,
                    'shared_allocation' => $shared,
                    'adjustments' => 0,
                    'final_payable' => $final,
                    'status' => CommissionCycleStatus::Draft->value,
                ]);

                $individualTotal += $individual;
                $payableTotal += $final;
            };

            foreach ($orgByVolunteer as $volunteerId => $attributed) {
                $addLine((int) $volunteerId, (float) $attributed, false, $orgSharedEach);
            }
            foreach ($internalByVolunteer as $volunteerId => $attributed) {
                $addLine((int) $volunteerId, (float) $attributed, true, $internalSharedEach);
            }

            $cycle->update([
                'individual_total' => $individualTotal,
                'payable_total' => $payableTotal,
            ]);

            $this->auditLogger->log(
                'commission.cycle_calculated',
                $cycle,
                null,
                ['period' => $period, 'payable_total' => $payableTotal],
                $organizationId,
                $actor,
            );

            return $cycle->fresh(['lineItems.volunteer']);
        });
    }

    public function approve(CommissionCycle $cycle, User $actor): CommissionCycle
    {
        if ($cycle->status !== CommissionCycleStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft cycles can be approved.',
            ]);
        }

        $cycle->update([
            'status' => CommissionCycleStatus::Approved,
            'approved_at' => now(),
        ]);
        $cycle->lineItems()->update(['status' => CommissionCycleStatus::Approved->value]);

        $this->auditLogger->log(
            'commission.cycle_approved',
            $cycle,
            null,
            ['period' => $cycle->period],
            $cycle->organization_id,
            $actor,
        );

        return $cycle->fresh(['lineItems.volunteer']);
    }

    public function markPaid(CommissionCycle $cycle, User $actor): CommissionCycle
    {
        if ($cycle->status !== CommissionCycleStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Only approved cycles can be marked paid.',
            ]);
        }

        $cycle->update([
            'status' => CommissionCycleStatus::Paid,
            'paid_at' => now(),
        ]);
        $cycle->lineItems()->update(['status' => CommissionCycleStatus::Paid->value]);

        $this->auditLogger->log(
            'commission.cycle_paid',
            $cycle,
            null,
            ['period' => $cycle->period],
            $cycle->organization_id,
            $actor,
        );

        return $cycle->fresh(['lineItems.volunteer']);
    }
}
