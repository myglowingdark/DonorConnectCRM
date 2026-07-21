<?php

namespace Tests\Feature\Imports;

use App\Models\Donor;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donors\DonorImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DummyExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dummy_xlsx_creates_nine_and_skips_one(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $path = base_path('tests/fixtures/dummy-donors-import.xlsx');
        $this->assertFileExists($path);

        $file = new UploadedFile(
            $path,
            'dummy-donors-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $batch = app(DonorImportService::class)->import($org->id, $file, $admin);

        $this->assertSame(9, $batch->rows_created);
        $this->assertSame(1, $batch->rows_skipped);
        $this->assertSame(9, Donor::query()->forOrganization($org->id)->count());
        $this->assertDatabaseHas('donors', [
            'organization_id' => $org->id,
            'full_name' => 'Anita Mehta',
            'preferred_language' => 'hi',
        ]);
    }
}
