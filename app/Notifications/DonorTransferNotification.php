<?php

namespace App\Notifications;

use App\Models\DonorTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DonorTransferNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DonorTransferRequest $transfer,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $donorName = $this->transfer->donor?->full_name ?? 'Donor';
        $from = $this->transfer->fromVolunteer?->name ?? 'Volunteer';
        $to = $this->transfer->toVolunteer?->name ?? 'Volunteer';

        [$title, $body] = match ($this->event) {
            'requested' => [
                'Donor transfer requested',
                "{$from} asked to transfer {$donorName} to {$to}.",
            ],
            'accepted' => [
                'Donor transfer accepted',
                "{$to} accepted the transfer of {$donorName}.",
            ],
            'rejected' => [
                'Donor transfer rejected',
                "{$to} declined the transfer of {$donorName}.",
            ],
            'cancelled' => [
                'Donor transfer cancelled',
                "{$from} cancelled the transfer of {$donorName}.",
            ],
            default => [
                'Donor transfer update',
                "Transfer for {$donorName} was updated.",
            ],
        };

        return [
            'title' => $title,
            'body' => $body,
            'event' => $this->event,
            'transfer_id' => $this->transfer->id,
            'donor_id' => $this->transfer->donor_id,
            'organization_id' => $this->transfer->organization_id,
            'action' => 'transfer',
            'url' => $this->event === 'accepted'
                ? route('donors.show', $this->transfer->donor_id)
                : route('transfers.index', ['status' => $this->event === 'requested' ? 'pending' : null]),
        ];
    }
}
