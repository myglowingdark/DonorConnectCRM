<?php

namespace App\Models;

use App\Services\SaaS\EntitlementService;
use App\Services\SaaS\UsageMeterService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'brand_color',
        'timezone',
        'currency',
        'is_active',
        'plan_id',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'donors_limit',
        'seats_limit',
        'campaigns_limit',
        'whatsapp_monthly_limit',
        'telecaller_hours_monthly',
        'imports_monthly_limit',
        'custom_domain',
        'email_from_name',
        'feature_overrides',
        'onboarded_at',
        'platform_service_fee_percent',
        'razorpay_key_id',
        'razorpay_key_secret',
        'razorpay_webhook_secret',
        'razorpay_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'donors_limit' => 'integer',
            'seats_limit' => 'integer',
            'campaigns_limit' => 'integer',
            'whatsapp_monthly_limit' => 'integer',
            'telecaller_hours_monthly' => 'integer',
            'imports_monthly_limit' => 'integer',
            'feature_overrides' => 'array',
            'onboarded_at' => 'datetime',
            'platform_service_fee_percent' => 'decimal:2',
            'razorpay_enabled' => 'boolean',
            'razorpay_key_secret' => 'encrypted',
            'razorpay_webhook_secret' => 'encrypted',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PlanInvoice::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(OrganizationApiToken::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(OrganizationWebhook::class);
    }

    public function capacityBookings(): HasMany
    {
        return $this->hasMany(TelecallerCapacityBooking::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_active'])
            ->withTimestamps();
    }

    public function donors(): HasMany
    {
        return $this->hasMany(Donor::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function apiConnection(): HasOne
    {
        return $this->hasOne(OrganizationApiConnection::class);
    }

    public function apiConnections(): HasMany
    {
        return $this->hasMany(OrganizationApiConnection::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DonorAssignment::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(DonorInteraction::class);
    }

    public function commissionSetting(): HasOne
    {
        return $this->hasOne(CommissionSetting::class);
    }

    public function messagingSetting(): HasOne
    {
        return $this->hasOne(OrganizationMessagingSetting::class);
    }

    public function razorpayPayments(): HasMany
    {
        return $this->hasMany(RazorpayPayment::class);
    }

    /** @return array<string, int|null> */
    public function effectiveLimits(): array
    {
        return app(EntitlementService::class)->limitsFor($this);
    }

    public function hasFeature(string $feature): bool
    {
        return app(EntitlementService::class)->hasFeature($this, $feature);
    }

    public function isSubscriptionLocked(): bool
    {
        if ($this->subscription_status === 'past_due') {
            return true;
        }

        if ($this->subscription_status === 'trial' && $this->trial_ends_at !== null) {
            return $this->trial_ends_at->isPast();
        }

        return false;
    }

    public function isHardLocked(): bool
    {
        return $this->subscription_status === 'suspended';
    }

    /** @return array<string, int> */
    public function usageMeters(): array
    {
        return app(UsageMeterService::class)->metersFor($this);
    }

    public function remainingDonorSlots(): ?int
    {
        $limit = $this->effectiveLimits()['donors'] ?? null;

        if ($limit === null) {
            return null;
        }

        return max(0, (int) $limit - $this->donors()->count());
    }

    public function canAcceptNewDonors(int $count = 1): bool
    {
        $limit = $this->effectiveLimits()['donors'] ?? null;

        if ($limit === null) {
            return true;
        }

        return ($this->donors()->count() + $count) <= (int) $limit;
    }

    public function assertCanAcceptNewDonors(int $count = 1): void
    {
        if ($this->canAcceptNewDonors($count)) {
            return;
        }

        $limit = (int) ($this->effectiveLimits()['donors'] ?? 0);
        $current = $this->donors()->count();
        $remaining = max(0, $limit - $current);

        throw \Illuminate\Validation\ValidationException::withMessages([
            'donors_limit' => "Donor list limit reached for {$this->name}. Limit {$limit}, current {$current}, remaining {$remaining}.",
        ]);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = collect($parts)->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)));

        return $letters->implode('') ?: 'ORG';
    }
}
