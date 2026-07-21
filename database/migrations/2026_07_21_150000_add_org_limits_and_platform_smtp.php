<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('donors_limit')->nullable()->after('is_active');
        });

        Schema::create('platform_messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('email_enabled')->default(true);
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_messaging_settings');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('donors_limit');
        });
    }
};
