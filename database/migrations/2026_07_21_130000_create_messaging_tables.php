<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('whatsapp_provider')->nullable();
            $table->text('whatsapp_api_key')->nullable();
            $table->string('whatsapp_from_number')->nullable();
            $table->string('sms_provider')->nullable();
            $table->text('sms_api_key')->nullable();
            $table->string('sms_from_number')->nullable();
            $table->timestamps();
            $table->unique('organization_id');
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel'); // email | whatsapp | sms
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'channel']);
        });

        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'donor_id']);
            $table->index('channel');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('organization_messaging_settings');
    }
};
