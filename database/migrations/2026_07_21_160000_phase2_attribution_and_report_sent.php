<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_schedules', function (Blueprint $table) {
            $table->timestamp('last_sent_at')->nullable()->after('is_active');
        });

        Schema::table('donation_attributions', function (Blueprint $table) {
            $table->unique('donation_id');
        });
    }

    public function down(): void
    {
        Schema::table('donation_attributions', function (Blueprint $table) {
            $table->dropUnique(['donation_id']);
        });

        Schema::table('report_schedules', function (Blueprint $table) {
            $table->dropColumn('last_sent_at');
        });
    }
};
