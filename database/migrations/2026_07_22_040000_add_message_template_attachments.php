<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('header_format', 20)->default('none')->after('variable_schema');
            $table->string('attachment_path')->nullable()->after('header_format');
            $table->string('attachment_filename')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_filename');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn([
                'header_format',
                'attachment_path',
                'attachment_filename',
                'attachment_mime',
            ]);
        });
    }
};
