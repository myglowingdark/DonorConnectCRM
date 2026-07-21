<?php

namespace App\Services\Payments;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\RazorpayPayment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RazorpayService
{
    public function createOrder(Organization $organization, Donor $donor, User $actor, float $amount, ?string $purpose = null): array
    {
        if (! $organization->razorpay_enabled || blank($organization->razorpay_key_id) || blank($organization->razorpay_key_secret)) {
            throw ValidationException::withMessages([
                'razorpay' => 'Razorpay is not configured for this organization.',
            ]);
        }

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be at least ₹1.',
            ]);
        }

        $amountPaise = (int) round($amount * 100);
        $receipt = 'dc_'.Str::lower(Str::ulid());

        $response = Http::withBasicAuth($organization->razorpay_key_id, $organization->razorpay_key_secret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => $organization->currency ?: 'INR',
                'receipt' => $receipt,
                'notes' => [
                    'organization_id' => $organization->id,
                    'donor_id' => $donor->id,
                    'purpose' => $purpose ?: 'donation',
                ],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'razorpay' => 'Razorpay order failed: '.($response->json('error.description') ?? $response->body()),
            ]);
        }

        $order = $response->json();

        $payment = RazorpayPayment::create([
            'organization_id' => $organization->id,
            'donor_id' => $donor->id,
            'created_by' => $actor->id,
            'razorpay_order_id' => $order['id'] ?? null,
            'amount' => $amount,
            'currency' => $organization->currency ?: 'INR',
            'status' => 'created',
            'purpose' => $purpose ?: 'donation',
            'payload' => $order,
        ]);

        return [
            'payment' => $payment,
            'order_id' => $order['id'],
            'amount' => $amountPaise,
            'currency' => $order['currency'] ?? 'INR',
            'key_id' => $organization->razorpay_key_id,
            'donor_name' => $donor->full_name,
            'donor_email' => $donor->email,
            'donor_phone' => $donor->phone,
        ];
    }

    public function markPaidFromWebhook(Organization $organization, array $payload): ?RazorpayPayment
    {
        $paymentEntity = data_get($payload, 'payload.payment.entity', []);
        $orderId = $paymentEntity['order_id'] ?? null;
        $paymentId = $paymentEntity['id'] ?? null;

        if (! $orderId) {
            return null;
        }

        $payment = RazorpayPayment::query()
            ->forOrganization($organization->id)
            ->where('razorpay_order_id', $orderId)
            ->first();

        if (! $payment) {
            return null;
        }

        if ($payment->status === 'paid') {
            return $payment;
        }

        $donation = null;
        if ($payment->donor_id) {
            $donation = Donation::create([
                'organization_id' => $organization->id,
                'donor_id' => $payment->donor_id,
                'external_donation_id' => $paymentId ?: ('rzp-'.$payment->id),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'donated_at' => now(),
                'payment_status' => 'completed',
                'payment_method' => 'razorpay',
                'source_data' => $paymentEntity,
            ]);

            $donor = Donor::query()->find($payment->donor_id);
            if ($donor) {
                $donor->update([
                    'last_donation_at' => now(),
                    'last_donation_amount' => $payment->amount,
                    'total_donated' => (float) $donor->total_donated + (float) $payment->amount,
                ]);
            }
        }

        $payment->update([
            'status' => 'paid',
            'razorpay_payment_id' => $paymentId,
            'donation_id' => $donation?->id,
            'payload' => $payload,
        ]);

        return $payment->fresh();
    }

    public function verifyWebhookSignature(Organization $organization, string $payload, ?string $signature): bool
    {
        if (blank($organization->razorpay_webhook_secret) || blank($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $organization->razorpay_webhook_secret);

        return hash_equals($expected, $signature);
    }
}
