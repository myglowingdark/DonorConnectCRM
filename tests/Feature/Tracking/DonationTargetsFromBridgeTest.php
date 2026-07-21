<?php

namespace Tests\Feature\Tracking;

use App\Enums\ApiAuthType;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use App\Models\User;
use App\Services\WordPress\WordPressDonorSyncService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DonationTargetsFromBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_donation_targets_includes_general_and_projects(): void
    {
        Http::fake([
            '*/projects' => Http::response([
                'ok' => true,
                'site_url' => 'https://partner.test/',
                'general_donation_url' => 'https://partner.test/donate/',
                'general_donation_label' => 'General donation (NGOBuddy)',
                'projects' => [
                    [
                        'id' => 12,
                        'title' => 'Blood Donation Camp',
                        'url' => 'https://partner.test/project/blood-donation-camp/',
                        'slug' => 'blood-donation-camp',
                    ],
                ],
            ], 200),
        ]);

        $org = Organization::factory()->create();
        $connection = OrganizationApiConnection::create([
            'organization_id' => $org->id,
            'name' => 'Bridge',
            'base_url' => 'https://partner.test/wp-json/donorconnect/v1',
            'auth_type' => ApiAuthType::Hmac,
            'credentials' => [
                'api_key' => 'key',
                'hmac_secret' => 'secret',
                'site_id' => 'site-1',
            ],
            'is_active' => true,
        ]);

        $targets = app(WordPressDonorSyncService::class)->fetchDonationTargets($connection, fresh: true);

        $this->assertCount(2, $targets);
        $this->assertSame('general', $targets[0]['key']);
        $this->assertSame('https://partner.test/donate/', $targets[0]['url']);
        $this->assertSame('project-12', $targets[1]['key']);
        $this->assertSame('Blood Donation Camp', $targets[1]['label']);
    }

    public function test_donor_show_passes_donation_targets_from_bridge(): void
    {
        Http::fake([
            '*/projects' => Http::response([
                'ok' => true,
                'general_donation_url' => 'https://partner.test/donate/',
                'general_donation_label' => 'General donation (NGOBuddy)',
                'projects' => [
                    [
                        'id' => 5,
                        'title' => 'School Kits',
                        'url' => 'https://partner.test/project/school-kits/',
                        'slug' => 'school-kits',
                    ],
                ],
            ], 200),
        ]);

        Cache::flush();

        $org = Organization::factory()->create();
        OrganizationApiConnection::create([
            'organization_id' => $org->id,
            'name' => 'Bridge',
            'base_url' => 'https://partner.test/wp-json/donorconnect/v1',
            'auth_type' => ApiAuthType::Hmac,
            'credentials' => [
                'api_key' => 'key',
                'hmac_secret' => 'secret',
                'site_id' => 'site-1',
            ],
            'is_active' => true,
        ]);

        $volunteer = User::factory()->volunteer()->create();
        $volunteer->organizations()->attach($org->id, ['is_active' => true]);

        $donor = Donor::factory()->create(['organization_id' => $org->id]);
        DonorAssignment::create([
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'volunteer_id' => $volunteer->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->actingAs($volunteer);
        OrganizationContext::set($org->id);

        $this->get(route('donors.show', $donor))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Donors/Show')
                ->has('donationTargets', 2)
                ->where('donationTargets.0.key', 'general')
                ->where('donationTargets.1.label', 'School Kits')
            );
    }

    public function test_fetch_donation_targets_returns_empty_without_connection(): void
    {
        $targets = app(WordPressDonorSyncService::class)->fetchDonationTargets(null);

        $this->assertSame([], $targets);
    }
}
