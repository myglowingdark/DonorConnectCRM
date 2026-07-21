<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCoupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'plan_ids',
        'max_redemptions',
        'redeemed_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'plan_ids' => 'array',
            'value' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function appliesToPlan(?int $planId): bool
    {
        $ids = $this->plan_ids;

        if ($ids === null || $ids === []) {
            return true;
        }

        return $planId !== null && in_array($planId, array_map('intval', $ids), true);
    }
}
