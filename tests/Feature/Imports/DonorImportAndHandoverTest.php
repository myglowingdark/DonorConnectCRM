<?php

namespace Tests\Feature\Imports;

use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DonorImportAndHandoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_csv_and_distribute_with_cap(): void
    {
        Storage::fake('local');

        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $v1 = User::factory()->volunteer()->create(['name' => 'Vol One']);
        $v2 = User::factory()->volunteer()->create(['name' => 'Vol Two']);

        foreach ([$admin, $v1, $v2] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $csv = "full_name,phone,email,city,state,preferred_language\n"
            ."Anita Mehta,+919811111111,anita@example.com,Mumbai,Maharashtra,hi\n"
            ."Ravi Kumar,+919822222222,ravi@example.com,Pune,Maharashtra,en\n"
            ."Neha Shah,+919833333333,neha@example.com,Surat,Gujarat,gu\n"
            ."Amit Patel,+919844444444,amit@example.com,Ahmedabad,Gujarat,hi\n";

        $file = UploadedFile::fake()->createWithContent('donors.csv', $csv);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('imports.store'), [
            'file' => $file,
            'assign_after_import' => true,
            'volunteer_ids' => [$v1->id, $v2->id],
            'cap_per_volunteer' => 2,
        ])->assertRedirect();

        $this->assertSame(4, Donor::query()->forOrganization($org->id)->count());
        $this->assertSame(2, DonorAssignment::query()->where('volunteer_id', $v1->id)->where('is_active', true)->count());
        $this->assertSame(2, DonorAssignment::query()->where('volunteer_id', $v2->id)->where('is_active', true)->count());
    }

    public function test_admin_can_handover_partial_donors_to_another_volunteer(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $from = User::factory()->volunteer()->create();
        $to = User::factory()->volunteer()->create();

        foreach ([$admin, $from, $to] as $user) {
            $user->organizations()->attach($org->id, ['is_active' => true]);
        }

        $donors = Donor::factory()->count(3)->create(['organization_id' => $org->id]);
        foreach ($donors as $donor) {
            DonorAssignment::create([
                'organization_id' => $org->id,
                'donor_id' => $donor->id,
                'volunteer_id' => $from->id,
                'assigned_by' => $admin->id,
                'is_active' => true,
            ]);
        }

        $moveIds = $donors->take(2)->pluck('id')->all();

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->post(route('handovers.store'), [
            'from_volunteer_id' => $from->id,
            'to_volunteer_ids' => [$to->id],
            'mode' => 'partial',
            'donor_ids' => $moveIds,
            'reassign_interactions' => false,
        ])->assertRedirect();

        $this->assertSame(1, DonorAssignment::query()->where('volunteer_id', $from->id)->where('is_active', true)->count());
        $this->assertSame(2, DonorAssignment::query()->where('volunteer_id', $to->id)->where('is_active', true)->count());
    }
}
