<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Mail\DonorOutreachMail;
use App\Models\CommissionSetting;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;
use App\Models\PlatformMessagingSetting;
use App\Models\User;
use App\Services\Donors\DonorImportService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrgLimitsAndPlatformSmtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_set_organization_donors_limit(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $org = Organization::factory()->create();

        $this->actingAs($admin)
            ->put(route('organizations.update', $org), [
                'name' => $org->name,
                'slug' => $org->slug,
                'brand_color' => $org->brand_color,
                'timezone' => $org->timezone,
                'currency' => $org->currency,
                'is_active' => true,
                'donors_limit' => 2,
            ])
            ->assertRedirect();

        $this->assertSame(2, $org->fresh()->donors_limit);
    }

    public function test_import_respects_donors_limit(): void
    {
        Storage::fake('local');

        $org = Organization::factory()->create(['donors_limit' => 1]);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        Donor::factory()->create(['organization_id' => $org->id]);

        $csv = "full_name,phone,email\nNew One,+919900000001,one@example.com\nNew Two,+919900000002,two@example.com\n";
        $file = UploadedFile::fake()->createWithContent('donors.csv', $csv);

        $batch = app(DonorImportService::class)->import($org->id, $file, $admin);

        $this->assertSame(0, $batch->rows_created);
        $this->assertSame(2, $batch->rows_skipped);
        $this->assertSame(1, Donor::query()->forOrganization($org->id)->count());
    }

    public function test_admin_can_save_commission_settings_with_volunteer_override(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->put(route('commissions.settings.update'), [
            'individual_enabled' => true,
            'individual_default_percent' => 5,
            'shared_enabled' => false,
            'shared_percent' => 0,
            'shared_eligibility' => 'active_contributors',
            'volunteer_overrides' => [
                ['volunteer_id' => $volunteer->id, 'percent' => 8.5],
            ],
        ])->assertRedirect();

        $settings = CommissionSetting::query()->where('organization_id', $org->id)->first();
        $this->assertNotNull($settings);
        $this->assertTrue($settings->individual_enabled);
        $this->assertEquals(5, (float) $settings->individual_default_percent);
        $this->assertEquals(8.5, $settings->rateForVolunteer($volunteer->id));
    }

    public function test_super_admin_can_save_platform_smtp_and_email_uses_it(): void
    {
        Mail::fake();

        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super)
            ->put(route('platform.messaging.update'), [
                'email_enabled' => true,
                'smtp_host' => 'smtp.platform.test',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'platform',
                'smtp_password' => 'secret',
                'from_email' => 'platform@donorconnect.test',
                'from_name' => 'DonorConnect',
            ])
            ->assertRedirect();

        $platform = PlatformMessagingSetting::current();
        $this->assertSame('smtp.platform.test', $platform->smtp_host);
        $this->assertTrue($platform->usesCustomSmtp());

        $org = Organization::factory()->create();
        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);
        $donor = Donor::factory()->create([
            'organization_id' => $org->id,
            'email' => 'donor@example.com',
        ]);
        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'assigned_by' => $volunteer->id,
            'is_active' => true,
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        // Mail::fake() intercepts before custom mailer transport; assert message is recorded as sent.
        Config::set('mail.default', 'array');

        $this->post(route('donors.messages.send', $donor), [
            'channel' => MessageChannel::Email->value,
            'subject' => 'Hello',
            'body' => 'Platform SMTP path',
        ])->assertRedirect();

        $this->assertDatabaseHas('outbound_messages', [
            'donor_id' => $donor->id,
            'channel' => 'email',
            'status' => MessageStatus::Sent->value,
        ]);

        Mail::assertSent(DonorOutreachMail::class);
    }
}
