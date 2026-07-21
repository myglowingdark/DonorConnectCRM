<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donor_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_assigned')->default(0);
            $table->unsignedInteger('cap_per_volunteer')->nullable();
            $table->json('volunteer_ids')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('donor_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->string('mode'); // full | partial
            $table->unsignedInteger('donors_moved')->default(0);
            $table->boolean('reassign_interactions')->default(false);
            $table->unsignedInteger('interactions_moved')->default(0);
            $table->text('notes')->nullable();
            $table->json('to_volunteer_ids')->nullable();
            $table->json('donor_ids')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('from_volunteer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donor_handovers');
        Schema::dropIfExists('donor_import_batches');
    }
};
