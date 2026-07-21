<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_commission_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('individual_enabled')->default(true);
            $table->decimal('individual_default_percent', 5, 2)->default(5);
            $table->boolean('shared_enabled')->default(false);
            $table->decimal('shared_percent', 5, 2)->default(0);
            $table->string('shared_eligibility')->default('active_contributors');
            $table->boolean('internal_individual_enabled')->default(true);
            $table->decimal('internal_individual_default_percent', 5, 2)->default(5);
            $table->boolean('internal_shared_enabled')->default(true);
            $table->decimal('internal_shared_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('discount_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 16); // percent | fixed
            $table->unsignedInteger('value');
            $table->json('plan_ids')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_coupon_id')->constrained('discount_coupons')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_invoice_id')->nullable()->constrained('plan_invoices')->nullOnDelete();
            $table->unsignedInteger('discount_amount')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'discount_coupon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('discount_coupons');
        Schema::dropIfExists('platform_commission_settings');
    }
};
