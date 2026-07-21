<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('metadata');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('razorpay_key_id')->nullable()->after('donors_limit');
            $table->text('razorpay_key_secret')->nullable()->after('razorpay_key_id');
            $table->string('razorpay_webhook_secret')->nullable()->after('razorpay_key_secret');
            $table->boolean('razorpay_enabled')->default(false)->after('razorpay_webhook_secret');
        });

        Schema::create('razorpay_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('razorpay_order_id')->nullable()->index();
            $table->string('razorpay_payment_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('created');
            $table->string('purpose')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_payments');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'razorpay_key_id',
                'razorpay_key_secret',
                'razorpay_webhook_secret',
                'razorpay_enabled',
            ]);
        });

        Schema::table('donors', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
