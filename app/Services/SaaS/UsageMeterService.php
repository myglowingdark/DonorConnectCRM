<?php

namespace App\Services\SaaS;

use App\Enums\MessageChannel;
use App\Models\DonorImportBatch;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\OutboundMessage;
use Illuminate\Support\Carbon;

class UsageMeterService
{
    /** @return array<string, int> */
    public function metersFor(Organization $organization): array
    {
        return [
            'donors' => $this->donorsCount($organization),
            'calls_this_month' => $this->callsThisMonth($organization),
            'messages_this_month' => $this->messagesThisMonth($organization),
            'imports_this_month' => $this->importsThisMonth($organization),
            'seats_used' => $this->seatsUsed($organization),
        ];
    }

    public function donorsCount(Organization $organization): int
    {
        return $organization->donors()->count();
    }

    public function callsThisMonth(Organization $organization): int
    {
        return DonorInteraction::query()
            ->forOrganization($organization->id)
            ->where('interaction_type', 'call')
            ->where('contacted_at', '>=', $this->monthStart())
            ->count();
    }

    public function messagesThisMonth(Organization $organization): int
    {
        return OutboundMessage::query()
            ->forOrganization($organization->id)
            ->where('created_at', '>=', $this->monthStart())
            ->count();
    }

    public function whatsappThisMonth(Organization $organization): int
    {
        return OutboundMessage::query()
            ->forOrganization($organization->id)
            ->where('channel', MessageChannel::WhatsApp)
            ->where('created_at', '>=', $this->monthStart())
            ->count();
    }

    public function importsThisMonth(Organization $organization): int
    {
        return DonorImportBatch::query()
            ->forOrganization($organization->id)
            ->where('created_at', '>=', $this->monthStart())
            ->count();
    }

    public function seatsUsed(Organization $organization): int
    {
        return $organization->users()
            ->wherePivot('is_active', true)
            ->where('users.is_active', true)
            ->count();
    }

    protected function monthStart(): Carbon
    {
        return now()->startOfMonth();
    }
}
