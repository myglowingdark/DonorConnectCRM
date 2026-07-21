<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedSmallInteger('attribution_window_days')->default(3)->after('donors_limit');
        });

        Schema::create('tracking_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->text('target_url');
            $table->string('channel', 32)->nullable();
            $table->foreignId('outbound_message_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'donor_id', 'volunteer_id'], 'tracking_links_donor_volunteer_unique');
            $table->index(['organization_id', 'donor_id']);
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_link_id')->constrained('tracking_links')->cascadeOnDelete();
            $table->foreignId('volunteer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->text('page_url')->nullable();
            $table->string('project_id')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tracking_link_id', 'event_type']);
            $table->index(['organization_id', 'occurred_at']);
        });

        Schema::table('donation_attributions', function (Blueprint $table) {
            $table->string('source', 32)->default('call')->after('volunteer_id');
            $table->foreignId('tracking_link_id')->nullable()->after('source')->constrained('tracking_links')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('donation_attributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tracking_link_id');
            $table->dropColumn('source');
        });

        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('tracking_links');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('attribution_window_days');
        });
    }
};
