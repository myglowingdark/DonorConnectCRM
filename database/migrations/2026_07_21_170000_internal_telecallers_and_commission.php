<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_internal_telecaller')->default(false)->after('is_active');
        });

        Schema::table('commission_settings', function (Blueprint $table) {
            $table->boolean('internal_individual_enabled')->default(true)->after('volunteer_overrides');
            $table->decimal('internal_individual_default_percent', 5, 2)->default(5)->after('internal_individual_enabled');
            $table->boolean('internal_shared_enabled')->default(true)->after('internal_individual_default_percent');
            $table->decimal('internal_shared_percent', 5, 2)->default(0)->after('internal_shared_enabled');
            $table->json('internal_volunteer_overrides')->nullable()->after('internal_shared_percent');
        });
    }

    public function down(): void
    {
        Schema::table('commission_settings', function (Blueprint $table) {
            $table->dropColumn([
                'internal_individual_enabled',
                'internal_individual_default_percent',
                'internal_shared_enabled',
                'internal_shared_percent',
                'internal_volunteer_overrides',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_internal_telecaller');
        });
    }
};
