<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('volunteer')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 7)->default('#1e3a8a');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->index('organization_id');
        });

        Schema::create('organization_api_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('WordPress Donors');
            $table->string('base_url');
            $table->string('auth_type')->default('bearer');
            $table->text('credentials')->nullable();
            $table->json('field_mappings')->nullable();
            $table->json('sync_settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('idle');
            $table->text('last_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('organization_id');
        });

        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_donor_id')->nullable();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable()->default('India');
            $table->string('donor_status')->default('new');
            $table->boolean('do_not_call')->default(false);
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('last_donation_at')->nullable();
            $table->decimal('last_donation_amount', 12, 2)->nullable();
            $table->decimal('total_donated', 14, 2)->default(0);
            $table->timestamp('next_follow_up_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('donor_status');
            $table->index('do_not_call');
            $table->index('next_follow_up_at');
            $table->index('last_donation_at');
            $table->unique(['organization_id', 'external_donor_id']);
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();
            $table->string('external_donation_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->timestamp('donated_at');
            $table->string('payment_status')->default('completed');
            $table->string('payment_method')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->json('source_data')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('donor_id');
            $table->index('donated_at');
            $table->unique(['organization_id', 'external_donation_id']);
        });

        Schema::create('donor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('donor_id');
            $table->index('volunteer_id');
            $table->unique(['donor_id', 'volunteer_id', 'organization_id']);
        });

        Schema::create('donor_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->string('interaction_type')->default('call');
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->timestamp('contacted_at');
            $table->timestamp('follow_up_at')->nullable();
            $table->decimal('pledged_amount', 12, 2)->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('attribute_donation')->default(false);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('donor_id');
            $table->index('volunteer_id');
            $table->index('follow_up_at');
            $table->index('outcome');
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_api_connection_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('donors_imported')->default(0);
            $table->unsignedInteger('donors_updated')->default(0);
            $table->unsignedInteger('donations_imported')->default(0);
            $table->unsignedInteger('donations_updated')->default(0);
            $table->text('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['subject_type', 'subject_id']);
        });

        // Phase 2 stub tables
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->boolean('individual_enabled')->default(false);
            $table->decimal('individual_default_percent', 5, 2)->default(0);
            $table->boolean('shared_enabled')->default(false);
            $table->decimal('shared_percent', 5, 2)->default(0);
            $table->string('shared_eligibility')->default('active_contributors');
            $table->json('volunteer_overrides')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->unique('organization_id');
        });

        Schema::create('commission_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->string('status')->default('draft');
            $table->decimal('verified_donation_total', 14, 2)->default(0);
            $table->decimal('individual_total', 14, 2)->default(0);
            $table->decimal('shared_pool', 14, 2)->default(0);
            $table->decimal('payable_total', 14, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'period']);
        });

        Schema::create('commission_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('attributed_donation_total', 14, 2)->default(0);
            $table->decimal('individual_rate', 5, 2)->default(0);
            $table->decimal('individual_commission', 14, 2)->default(0);
            $table->decimal('shared_allocation', 14, 2)->default(0);
            $table->decimal('adjustments', 14, 2)->default(0);
            $table->decimal('final_payable', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('donation_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('organization_id');
        });

        Schema::create('report_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('role_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('organization_id');
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('frequency')->default('weekly');
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('send_at')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('report_recipients');
        Schema::dropIfExists('donation_attributions');
        Schema::dropIfExists('commission_line_items');
        Schema::dropIfExists('commission_cycles');
        Schema::dropIfExists('commission_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('donor_interactions');
        Schema::dropIfExists('donor_assignments');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('donors');
        Schema::dropIfExists('organization_api_connections');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};
