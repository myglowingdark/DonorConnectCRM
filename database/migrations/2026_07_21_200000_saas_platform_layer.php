<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('price_monthly')->default(0); // INR
            $table->unsignedInteger('seats_limit')->nullable();
            $table->unsignedInteger('donors_limit')->nullable();
            $table->unsignedInteger('campaigns_limit')->nullable();
            $table->unsignedInteger('whatsapp_monthly_limit')->nullable();
            $table->unsignedInteger('telecaller_hours_monthly')->nullable();
            $table->unsignedInteger('imports_monthly_limit')->nullable();
            $table->json('features')->nullable(); // feature flags
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained('plans')->nullOnDelete();
            $table->string('subscription_status')->default('trial')->after('plan_id'); // trial|active|past_due|suspended
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->unsignedInteger('seats_limit')->nullable()->after('donors_limit');
            $table->unsignedInteger('campaigns_limit')->nullable()->after('seats_limit');
            $table->unsignedInteger('whatsapp_monthly_limit')->nullable()->after('campaigns_limit');
            $table->unsignedInteger('telecaller_hours_monthly')->nullable()->after('whatsapp_monthly_limit');
            $table->unsignedInteger('imports_monthly_limit')->nullable()->after('telecaller_hours_monthly');
            $table->string('custom_domain')->nullable()->after('slug');
            $table->string('email_from_name')->nullable()->after('custom_domain');
            $table->json('feature_overrides')->nullable()->after('email_from_name');
            $table->timestamp('onboarded_at')->nullable()->after('feature_overrides');
            $table->decimal('platform_service_fee_percent', 5, 2)->nullable()->after('onboarded_at');
            $table->softDeletes();
        });

        Schema::create('plan_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->unsignedInteger('amount'); // INR
            $table->string('currency')->default('INR');
            $table->string('status')->default('draft'); // draft|open|paid|void
            $table->string('razorpay_order_id')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('razorpay_key_id')->nullable();
            $table->text('razorpay_key_secret')->nullable();
            $table->string('razorpay_webhook_secret')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::create('organization_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 12);
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('events'); // donation.created, lead.assigned, pledge.made
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->json('payload')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_capacity_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('seats');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('pending'); // pending|approved|rejected|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('call_quality_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interaction_id')->constrained('donor_interactions')->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('commission_line_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->string('status')->default('held'); // held|released|forfeited
            $table->timestamps();
        });

        Schema::create('organization_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role')->default('volunteer');
            $table->string('token', 64)->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invites');
        Schema::dropIfExists('commission_holds');
        Schema::dropIfExists('call_quality_ratings');
        Schema::dropIfExists('telecaller_capacity_bookings');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('organization_webhooks');
        Schema::dropIfExists('organization_api_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
        Schema::dropIfExists('platform_billing_settings');
        Schema::dropIfExists('plan_invoices');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn([
                'subscription_status',
                'trial_ends_at',
                'subscription_ends_at',
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
                'deleted_at',
            ]);
        });
        Schema::dropIfExists('plans');
    }
};
