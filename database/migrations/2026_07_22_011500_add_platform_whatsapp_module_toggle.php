<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_messaging_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_messaging_settings', 'whatsapp_module_enabled')) {
                $table->boolean('whatsapp_module_enabled')->default(false)->after('whatsapp_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_messaging_settings', function (Blueprint $table) {
            if (Schema::hasColumn('platform_messaging_settings', 'whatsapp_module_enabled')) {
                $table->dropColumn('whatsapp_module_enabled');
            }
        });
    }
};
