<?php

namespace App\Services\SaaS;

use App\Models\DiscountCoupon;
use App\Models\Organization;
use App\Models\PlanInvoice;
use App\Models\CouponRedemption;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function findActiveByCode(string $code): ?DiscountCoupon
    {
        return DiscountCoupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();
    }

    /**
     * @return array{coupon: DiscountCoupon, discount: int, amount: int}
     */
    public function quote(string $code, Organization $organization, int $originalAmount): array
    {
        $coupon = $this->findActiveByCode($code);

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Coupon code is invalid.',
            ]);
        }

        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon is inactive.',
            ]);
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon is not active yet.',
            ]);
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon has expired.',
            ]);
        }

        if ($coupon->max_redemptions !== null && $coupon->redeemed_count >= $coupon->max_redemptions) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon has reached its redemption limit.',
            ]);
        }

        if (! $coupon->appliesToPlan($organization->plan_id)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon does not apply to the organization plan.',
            ]);
        }

        $discount = $this->computeDiscount($coupon, $originalAmount);

        if ($discount < 1) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Coupon does not reduce this invoice amount.',
            ]);
        }

        $amount = max(0, $originalAmount - $discount);

        return [
            'coupon' => $coupon,
            'discount' => $discount,
            'amount' => $amount,
        ];
    }

    public function redeem(DiscountCoupon $coupon, Organization $organization, PlanInvoice $invoice, int $discountAmount): CouponRedemption
    {
        $redemption = CouponRedemption::query()->create([
            'discount_coupon_id' => $coupon->id,
            'organization_id' => $organization->id,
            'plan_invoice_id' => $invoice->id,
            'discount_amount' => $discountAmount,
        ]);

        $coupon->increment('redeemed_count');

        return $redemption;
    }

    public function computeDiscount(DiscountCoupon $coupon, int $originalAmount): int
    {
        if ($originalAmount < 1) {
            return 0;
        }

        if ($coupon->type === 'percent') {
            $pct = min(100, max(0, (int) $coupon->value));

            return (int) floor($originalAmount * $pct / 100);
        }

        return min($originalAmount, max(0, (int) $coupon->value));
    }
}
