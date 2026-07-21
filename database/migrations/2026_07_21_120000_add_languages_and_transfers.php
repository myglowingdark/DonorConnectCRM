<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('languages')->nullable()->after('phone');
        });

        Schema::table('donors', function (Blueprint $table) {
            $table->string('preferred_language', 32)->nullable()->after('country');
            $table->boolean('was_transferred')->default(false)->after('do_not_call');
            $table->timestamp('last_transferred_at')->nullable()->after('was_transferred');
        });

        Schema::create('donor_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('response_note')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('donor_id');
            $table->index('to_volunteer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donor_transfer_requests');

        Schema::table('donors', function (Blueprint $table) {
            $table->dropColumn(['preferred_language', 'was_transferred', 'last_transferred_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('languages');
        });
    }
};
