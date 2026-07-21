<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_messaging_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_messaging_settings', 'whatsapp_enabled')) {
                $table->boolean('whatsapp_enabled')->default(false)->after('email_enabled');
            }
            if (! Schema::hasColumn('platform_messaging_settings', 'meta_access_token')) {
                $table->text('meta_access_token')->nullable()->after('from_name');
                $table->string('meta_phone_number_id')->nullable()->after('meta_access_token');
                $table->string('meta_waba_id')->nullable()->after('meta_phone_number_id');
                $table->string('meta_app_id')->nullable()->after('meta_waba_id');
                $table->text('meta_app_secret')->nullable()->after('meta_app_id');
                $table->string('meta_api_version', 20)->default('v21.0')->after('meta_app_secret');
            }
        });

        Schema::table('organization_messaging_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_messaging_settings', 'whatsapp_use_platform')) {
                $table->boolean('whatsapp_use_platform')->default(true)->after('whatsapp_from_number');
                $table->string('whatsapp_phone_number_id')->nullable()->after('whatsapp_use_platform');
                $table->string('whatsapp_waba_id')->nullable()->after('whatsapp_phone_number_id');
            }
            if (! Schema::hasColumn('organization_messaging_settings', 'bulk_whatsapp_enabled')) {
                $table->boolean('bulk_whatsapp_enabled')->default(false)->after('sms_from_number');
                $table->boolean('auto_donation_thankyou_enabled')->default(false)->after('bulk_whatsapp_enabled');
                $table->unsignedBigInteger('auto_donation_thankyou_template_id')->nullable()->after('auto_donation_thankyou_enabled');
                $table->foreign('auto_donation_thankyou_template_id', 'org_msg_thankyou_template_fk')
                    ->references('id')
                    ->on('message_templates')
                    ->nullOnDelete();
            }
        });

        Schema::table('message_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('message_templates', 'meta_name')) {
                $table->string('meta_name')->nullable()->after('is_active');
                $table->string('meta_language', 20)->nullable()->after('meta_name');
                $table->string('meta_category', 40)->nullable()->after('meta_language');
                $table->string('meta_status', 20)->nullable()->after('meta_category');
                $table->string('meta_template_id')->nullable()->after('meta_status');
                $table->text('meta_rejection_reason')->nullable()->after('meta_template_id');
                $table->json('variable_schema')->nullable()->after('meta_rejection_reason');
            }
        });

        Schema::table('outbound_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('outbound_messages', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable()->after('error_message');
                $table->json('provider_payload')->nullable()->after('provider_message_id');
            }
        });

        // Ensure plan feature catalogs include whatsapp for growth/enterprise.
        if (Schema::hasTable('plans')) {
            app(\App\Services\SaaS\PlanCatalog::class)->seed();
        }
    }

    public function down(): void
    {
        Schema::table('outbound_messages', function (Blueprint $table) {
            if (Schema::hasColumn('outbound_messages', 'provider_message_id')) {
                $table->dropColumn(['provider_message_id', 'provider_payload']);
            }
        });

        Schema::table('message_templates', function (Blueprint $table) {
            if (Schema::hasColumn('message_templates', 'meta_name')) {
                $table->dropColumn([
                    'meta_name',
                    'meta_language',
                    'meta_category',
                    'meta_status',
                    'meta_template_id',
                    'meta_rejection_reason',
                    'variable_schema',
                ]);
            }
        });

        Schema::table('organization_messaging_settings', function (Blueprint $table) {
            if (Schema::hasColumn('organization_messaging_settings', 'auto_donation_thankyou_template_id')) {
                $table->dropForeign('org_msg_thankyou_template_fk');
                $table->dropColumn([
                    'whatsapp_use_platform',
                    'whatsapp_phone_number_id',
                    'whatsapp_waba_id',
                    'bulk_whatsapp_enabled',
                    'auto_donation_thankyou_enabled',
                    'auto_donation_thankyou_template_id',
                ]);
            }
        });

        Schema::table('platform_messaging_settings', function (Blueprint $table) {
            if (Schema::hasColumn('platform_messaging_settings', 'whatsapp_enabled')) {
                $table->dropColumn([
                    'whatsapp_enabled',
                    'meta_access_token',
                    'meta_phone_number_id',
                    'meta_waba_id',
                    'meta_app_id',
                    'meta_app_secret',
                    'meta_api_version',
                ]);
            }
        });
    }
};
