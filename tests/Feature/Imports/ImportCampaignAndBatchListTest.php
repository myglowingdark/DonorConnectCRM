<?php

namespace Tests\Feature\Imports;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorImportBatch;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCampaignAndBatchListTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_applies_tags_creates_campaign_and_shows_batch_list(): void
    {
        Storage::fake('local');

        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $csv = "full_name,phone,email,city,state,preferred_language,tags\n"
            ."Anita Mehta,+919811111111,anita@example.com,Mumbai,Maharashtra,hi,vip\n"
            ."Ravi Kumar,+919822222222,ravi@example.com,Pune,Maharashtra,en,\n";

        $file = UploadedFile::fake()->createWithContent('donors.csv', $csv);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $response = $this->post(route('imports.store'), [
            'file' => $file,
            'assign_after_import' => false,
            'tags' => 'diwali2026, warm',
            'new_campaign_name' => 'Diwali Drive',
        ]);

        $batch = DonorImportBatch::query()->first();
        $this->assertNotNull($batch);
        $response->assertRedirect(route('imports.show', $batch));

        $campaign = Campaign::query()->where('name', 'Diwali Drive')->first();
        $this->assertNotNull($campaign);
        $this->assertSame($campaign->id, $batch->campaign_id);
        $this->assertEqualsCanonicalizing(['diwali2026', 'warm'], $batch->tags);
        $this->assertCount(2, $batch->donor_ids);

        $anita = Donor::query()->where('full_name', 'Anita Mehta')->first();
        $this->assertNotNull($anita);
        $this->assertSame($campaign->id, $anita->campaign_id);
        $this->assertSame($batch->id, $anita->import_batch_id);
        $this->assertTrue(collect($anita->tags)->contains('imported'));
        $this->assertTrue(collect($anita->tags)->contains('diwali2026'));
        $this->assertTrue(collect($anita->tags)->contains('vip'));

        $this->get(route('imports.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Imports/Show')
                ->where('batch.id', $batch->id)
                ->has('donors.data', 2)
            );
    }

    public function test_xlsx_http_import_accepts_excel_mime_as_zip(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $path = base_path('tests/fixtures/dummy-donors-import.xlsx');
        $this->assertFileExists($path);

        // Mimic browsers/OS that report xlsx as application/zip
        $file = new UploadedFile(
            $path,
            'dummy-donors-import.xlsx',
            'application/zip',
            null,
            true,
        );

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('imports.store'), [
            'file' => $file,
            'assign_after_import' => false,
            'tags' => 'fixture',
        ])->assertRedirect();

        $this->assertSame(9, Donor::query()->forOrganization($org->id)->count());
        $batch = DonorImportBatch::query()->first();
        $this->assertNotNull($batch);
        $this->assertContains('fixture', $batch->tags ?? []);
    }

    public function test_campaign_stats_show_revenue_and_conversion(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $volunteer = User::factory()->volunteer()->create();
        foreach ([$admin, $volunteer] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $campaign = Campaign::query()->create([
            'organization_id' => $org->id,
            'name' => 'Ramadan',
            'status' => 'active',
            'starts_at' => now()->subMonth()->toDateString(),
        ]);

        $donorA = Donor::factory()->create([
            'organization_id' => $org->id,
            'campaign_id' => $campaign->id,
            'total_donated' => 500,
        ]);
        $donorB = Donor::factory()->create([
            'organization_id' => $org->id,
            'campaign_id' => $campaign->id,
            'total_donated' => 0,
        ]);

        Donation::factory()->create([
            'organization_id' => $org->id,
            'donor_id' => $donorA->id,
            'campaign_id' => $campaign->id,
            'amount' => 500,
            'donated_at' => now()->subDay(),
        ]);

        DonorInteraction::create([
            'organization_id' => $org->id,
            'donor_id' => $donorA->id,
            'volunteer_id' => $volunteer->id,
            'interaction_type' => 'call',
            'outcome' => 'donated',
            'contacted_at' => now()->subDay(),
            'campaign_id' => $campaign->id,
        ]);
        DonorInteraction::create([
            'organization_id' => $org->id,
            'donor_id' => $donorB->id,
            'volunteer_id' => $volunteer->id,
            'interaction_type' => 'call',
            'outcome' => 'not_interested',
            'contacted_at' => now()->subDay(),
            'campaign_id' => $campaign->id,
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->get(route('campaigns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Campaigns/Index')
                ->has('campaigns', 1)
                ->where('campaigns.0.revenue', 500)
                ->where('campaigns.0.conversion_rate', 50)
            );

        $this->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Campaigns/Show')
                ->where('stats.revenue', 500)
                ->where('stats.donations_count', 1)
                ->where('stats.calls', 2)
                ->where('stats.call_conversion_rate', 50)
                ->where('stats.leads', 2)
                ->where('stats.donated_leads', 1)
                ->where('stats.lead_conversion_rate', 50)
            );
    }
}
