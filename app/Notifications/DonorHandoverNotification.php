<?php

namespace App\Notifications;

use App\Models\DonorHandover;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DonorHandoverNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DonorHandover $handover,
        public string $event = 'completed',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $from = $this->handover->fromVolunteer?->name ?? 'Volunteer';
        $count = (int) $this->handover->donors_moved;

        return [
            'title' => 'Donor handover completed',
            'body' => "{$count} donor(s) were handed over from {$from}.",
            'event' => $this->event,
            'handover_id' => $this->handover->id,
            'organization_id' => $this->handover->organization_id,
            'action' => 'handover',
            'url' => route('donors.index'),
        ];
    }
}
