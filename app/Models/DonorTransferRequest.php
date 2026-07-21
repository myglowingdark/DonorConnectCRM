<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorTransferRequest extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'donor_id',
        'from_volunteer_id',
        'to_volunteer_id',
        'requested_by',
        'status',
        'reason',
        'response_note',
        'responded_by',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function fromVolunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_volunteer_id');
    }

    public function toVolunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_volunteer_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isPending(): bool
    {
        return $this->status === TransferStatus::Pending;
    }
}
