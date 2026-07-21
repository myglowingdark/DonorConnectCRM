<?php

namespace App\Notifications;

use App\Models\DonationAttribution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DonationAttributionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DonationAttribution $attribution,
        public string $event,
        public int $count = 1,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $donor = $this->attribution->donor?->full_name ?? 'Donor';
        $volunteer = $this->attribution->volunteer?->name ?? 'Volunteer';
        $amount = number_format((float) ($this->attribution->donation?->amount ?? 0), 2);

        [$title, $body, $url] = match ($this->event) {
            'queued' => [
                'Donation attribution pending',
                $this->count > 1
                    ? "{$volunteer} requested credit for {$this->count} recent donations for {$donor}."
                    : "{$volunteer} requested credit for a ₹{$amount} donation from {$donor}.",
                route('attributions.index', ['status' => 'pending']),
            ],
            'approved' => [
                'Attribution approved',
                "Your attribution for {$donor} (₹{$amount}) was approved.",
                route('commissions.mine'),
            ],
            'rejected' => [
                'Attribution rejected',
                "Your attribution for {$donor} (₹{$amount}) was rejected.",
                route('attributions.index'),
            ],
            'link_paid' => [
                'Successful tracked donation',
                "{$donor} donated ₹{$amount} via your tracking link.",
                route('donors.show', $this->attribution->donor_id),
            ],
            default => [
                'Attribution update',
                "Attribution for {$donor} was updated.",
                route('attributions.index'),
            ],
        };

        return [
            'title' => $title,
            'body' => $body,
            'event' => $this->event,
            'attribution_id' => $this->attribution->id,
            'organization_id' => $this->attribution->organization_id,
            'action' => 'attribution',
            'url' => $url,
        ];
    }
}
