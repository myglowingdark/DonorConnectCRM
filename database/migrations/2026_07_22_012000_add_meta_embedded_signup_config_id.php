<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_messaging_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_messaging_settings', 'meta_embedded_signup_config_id')) {
                $table->string('meta_embedded_signup_config_id')->nullable()->after('meta_api_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_messaging_settings', function (Blueprint $table) {
            if (Schema::hasColumn('platform_messaging_settings', 'meta_embedded_signup_config_id')) {
                $table->dropColumn('meta_embedded_signup_config_id');
            }
        });
    }
};
