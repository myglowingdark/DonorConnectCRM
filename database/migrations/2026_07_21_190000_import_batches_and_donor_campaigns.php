<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->after('campaign_id')->constrained('donor_import_batches')->nullOnDelete();
            $table->index(['organization_id', 'campaign_id']);
            $table->index(['organization_id', 'import_batch_id']);
        });

        Schema::table('donor_import_batches', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('uploaded_by')->constrained()->nullOnDelete();
            $table->json('donor_ids')->nullable()->after('errors');
            $table->json('tags')->nullable()->after('donor_ids');
        });
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropConstrainedForeignId('import_batch_id');
        });

        Schema::table('donor_import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn(['donor_ids', 'tags']);
        });
    }
};
