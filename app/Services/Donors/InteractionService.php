<?php

namespace App\Services\Donors;

use App\Enums\CallOutcome;
use App\Enums\DonorStatus;
use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Commissions\AttributionService;
use App\Services\SaaS\WebhookDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InteractionService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private AttributionService $attributionService,
        private WebhookDispatcher $webhookDispatcher,
    ) {}

    /**
     * @param  array{
     *     outcome: string,
     *     notes?: string|null,
     *     follow_up_at?: string|null,
     *     pledged_amount?: float|null,
     *     campaign_id?: int|null,
     *     attribute_donation?: bool,
     *     preferred_language?: string|null,
     *     contacted_at?: string|null
     * }  $data
     */
    public function logCall(Donor $donor, User $volunteer, array $data): DonorInteraction
    {
        if ($donor->do_not_call) {
            throw ValidationException::withMessages([
                'outcome' => 'This donor is marked Do Not Call. An admin must remove the restriction before logging a new call.',
            ]);
        }

        $outcome = CallOutcome::from($data['outcome']);

        return DB::transaction(function () use ($donor, $volunteer, $data, $outcome) {
            $attribute = (bool) ($data['attribute_donation'] ?? false);

            $interaction = DonorInteraction::create([
                'organization_id' => $donor->organization_id,
                'donor_id' => $donor->id,
                'volunteer_id' => $volunteer->id,
                'interaction_type' => 'call',
                'outcome' => $outcome,
                'notes' => $data['notes'] ?? null,
                'contacted_at' => $data['contacted_at'] ?? now(),
                'follow_up_at' => $data['follow_up_at'] ?? null,
                'pledged_amount' => $data['pledged_amount'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'attribute_donation' => $attribute,
            ]);

            $updates = [
                'last_contacted_at' => $interaction->contacted_at,
                'donor_status' => DonorStatus::fromOutcome($outcome),
                'next_follow_up_at' => $interaction->follow_up_at,
            ];

            if (array_key_exists('preferred_language', $data) && filled($data['preferred_language'])) {
                $updates['preferred_language'] = $data['preferred_language'];
            }

            if ($outcome === CallOutcome::DoNotCall) {
                $updates['do_not_call'] = true;
                $updates['donor_status'] = DonorStatus::DoNotCall;
                $updates['next_follow_up_at'] = null;
            }

            $donor->update($updates);

            if ($attribute) {
                $this->attributionService->queueFromCall($donor->fresh(), $volunteer);
            }

            $this->auditLogger->log(
                'donor.call_logged',
                $donor,
                null,
                [
                    'outcome' => $outcome->value,
                    'interaction_id' => $interaction->id,
                    'volunteer_id' => $volunteer->id,
                    'attribute_donation' => $attribute,
                ],
                $donor->organization_id,
                $volunteer,
            );

            if ($outcome === CallOutcome::Pledged) {
                $organization = \App\Models\Organization::query()->find($donor->organization_id);
                if ($organization) {
                    $this->webhookDispatcher->dispatch($organization, 'pledge.made', [
                        'donor_id' => $donor->id,
                        'interaction_id' => $interaction->id,
                        'pledged_amount' => $interaction->pledged_amount,
                        'volunteer_id' => $volunteer->id,
                    ]);
                }
            }

            return $interaction->fresh(['volunteer', 'donor']);
        });
    }
}
