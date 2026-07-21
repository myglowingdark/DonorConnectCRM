<?php

namespace App\Models;

use App\Enums\CommissionCycleStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionCycle extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'period',
        'status',
        'verified_donation_total',
        'individual_total',
        'shared_pool',
        'payable_total',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommissionCycleStatus::class,
            'verified_donation_total' => 'decimal:2',
            'individual_total' => 'decimal:2',
            'shared_pool' => 'decimal:2',
            'payable_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(CommissionLineItem::class);
    }
}
