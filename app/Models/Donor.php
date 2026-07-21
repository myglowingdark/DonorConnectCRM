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
        'preferred_language',
        'donor_status',
        'do_not_call',
        'was_transferred',
        'last_transferred_at',
        'last_contacted_at',
        'last_donation_at',
        'last_donation_amount',
        'total_donated',
        'next_follow_up_at',
        'metadata',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'donor_status' => DonorStatus::class,
            'do_not_call' => 'boolean',
            'was_transferred' => 'boolean',
            'last_transferred_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'last_donation_at' => 'datetime',
            'last_donation_amount' => 'decimal:2',
            'total_donated' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'metadata' => 'array',
            'tags' => 'array',
        ];
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(DonorTransferRequest::class)->latest();
    }

    public function pendingTransfer(): HasOne
    {
        return $this->hasOne(DonorTransferRequest::class)
            ->where('status', 'pending')
            ->latestOfMany();
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
     * Donors ready to work now: due/overdue follow-ups, or no follow-up set (work by last-contact age).
     */
    public function scopeNeedsCall(Builder $query): Builder
    {
        return $query->where('do_not_call', false)
            ->where(function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->whereNotNull('next_follow_up_at')
                        ->where('next_follow_up_at', '<=', now()->endOfDay());
                })->orWhereNull('next_follow_up_at');
            });
    }

    public function scopeCallable(Builder $query): Builder
    {
        return $query->where('do_not_call', false);
    }

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? [], true);
    }

    public function addTags(array $tags): void
    {
        $merged = collect($this->tags ?? [])
            ->merge($tags)
            ->map(fn ($t) => strtolower(trim((string) $t)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->tags = $merged;
    }

    /**
     * Next-donor order:
     * 1) Follow-up due/overdue (oldest due first)
     * 2) Upcoming scheduled follow-up (soonest first)
     * 3) No follow-up → never contacted, then contacted longest ago
     */
    public function scopeOrderForNextCall(Builder $query): Builder
    {
        return $query
            ->orderByRaw('
                CASE
                    WHEN do_not_call = 1 THEN 9
                    WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at <= ? THEN 0
                    WHEN next_follow_up_at IS NOT NULL THEN 1
                    ELSE 2
                END ASC
            ', [now()])
            ->orderByRaw('
                CASE
                    WHEN next_follow_up_at IS NOT NULL THEN next_follow_up_at
                END ASC
            ')
            ->orderByRaw('
                CASE
                    WHEN next_follow_up_at IS NULL AND last_contacted_at IS NULL THEN 0
                    WHEN next_follow_up_at IS NULL THEN 1
                    ELSE 2
                END ASC
            ')
            ->orderByRaw('
                CASE
                    WHEN next_follow_up_at IS NULL THEN last_contacted_at
                END ASC
            ')
            ->orderBy('full_name');
    }

    public function callPriority(): string
    {
        if ($this->do_not_call) {
            return 'do_not_call';
        }

        if ($this->next_follow_up_at && $this->next_follow_up_at->lte(now())) {
            return $this->next_follow_up_at->isToday() ? 'due_today' : 'overdue';
        }

        if ($this->next_follow_up_at && $this->next_follow_up_at->isFuture()) {
            return 'upcoming';
        }

        if (! $this->last_contacted_at) {
            return 'new';
        }

        return 'cold';
    }
}
