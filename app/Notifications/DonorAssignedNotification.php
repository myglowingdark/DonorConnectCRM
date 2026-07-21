<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DonorAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $organizationId,
        public int $donorCount,
        public ?User $assignedBy = null,
        public string $event = 'assigned',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $by = $this->assignedBy?->name ?? 'An admin';
        $title = $this->event === 'imported'
            ? 'New leads assigned'
            : 'Leads assigned to you';
        $body = $this->event === 'imported'
            ? "{$by} imported and assigned {$this->donorCount} lead(s) to you."
            : "{$by} assigned {$this->donorCount} lead(s) to you.";

        return [
            'title' => $title,
            'body' => $body,
            'event' => $this->event,
            'organization_id' => $this->organizationId,
            'donor_count' => $this->donorCount,
            'action' => 'assignment',
            'url' => route('donors.index'),
        ];
    }
}
