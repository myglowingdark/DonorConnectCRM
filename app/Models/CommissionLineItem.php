<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionLineItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'commission_cycle_id',
        'organization_id',
        'volunteer_id',
        'attributed_donation_total',
        'individual_rate',
        'individual_commission',
        'shared_allocation',
        'adjustments',
        'final_payable',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attributed_donation_total' => 'decimal:2',
            'individual_rate' => 'decimal:2',
            'individual_commission' => 'decimal:2',
            'shared_allocation' => 'decimal:2',
            'adjustments' => 'decimal:2',
            'final_payable' => 'decimal:2',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(CommissionCycle::class, 'commission_cycle_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }
}
