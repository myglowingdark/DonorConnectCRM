<?php

namespace App\Models;

use App\Enums\DonorStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Donor extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'external_donor_id',
        'full_name',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'city',
        'state',
        'country',
        'donor_status',
        'do_not_call',
        'last_contacted_at',
        'last_donation_at',
        'last_donation_amount',
        'total_donated',
        'next_follow_up_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'donor_status' => DonorStatus::class,
            'do_not_call' => 'boolean',
            'last_contacted_at' => 'datetime',
            'last_donation_at' => 'datetime',
            'last_donation_amount' => 'decimal:2',
            'total_donated' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(DonorInteraction::class)->latest('contacted_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DonorAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(DonorAssignment::class)->where('is_active', true)->latestOfMany();
    }

    public function scopeAssignedTo(Builder $query, int $volunteerId): Builder
    {
        return $query->whereHas('assignments', function (Builder $q) use ($volunteerId) {
            $q->where('volunteer_id', $volunteerId)->where('is_active', true);
        });
    }

    public function scopeUncontacted(Builder $query): Builder
    {
        return $query->whereNull('last_contacted_at');
    }

    public function scopeFollowUpDue(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->where('do_not_call', false);
    }

    public function scopeFollowUpToday(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', today())
            ->where('do_not_call', false);
    }

    /**
     * Donors a volunteer should call next: overdue/due follow-ups, then never contacted.
     */
    public function scopeNeedsCall(Builder $query): Builder
    {
        return $query->where('do_not_call', false)
            ->where(function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->whereNotNull('next_follow_up_at')
                        ->where('next_follow_up_at', '<=', now()->endOfDay());
                })->orWhereNull('last_contacted_at');
            });
    }

    public function scopeCallable(Builder $query): Builder
    {
        return $query->where('do_not_call', false);
    }

    public function callPriority(): string
    {
        if ($this->do_not_call) {
            return 'do_not_call';
        }

        if ($this->next_follow_up_at && $this->next_follow_up_at->isPast()) {
            return 'overdue';
        }

        if ($this->next_follow_up_at && $this->next_follow_up_at->isToday()) {
            return 'due_today';
        }

        if ($this->next_follow_up_at && $this->next_follow_up_at->isFuture()) {
            return 'upcoming';
        }

        if (! $this->last_contacted_at) {
            return 'new';
        }

        return 'later';
    }
}
