<?php

namespace Tests\Feature\Donors;

use App\Models\Donor;
use App\Models\Organization;
use App\Models\RazorpayPayment;
use App\Models\User;
use App\Services\Donors\DonorImportService;
use App\Services\Payments\RazorpayService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TagsFiltersOrgProfileRazorpayTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_adds_imported_tag_and_custom_tags(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        $csv = "full_name,phone,email,city,state,preferred_language,tags\n"
            ."Tag Me,+919900001111,tag@example.com,Pune,MH,en,vip|priority\n";

        $file = UploadedFile::fake()->createWithContent('donors.csv', $csv);
        app(DonorImportService::class)->import($org->id, $file, $admin);

        $donor = Donor::query()->forOrganization($org->id)->first();
        $this->assertNotNull($donor);
        $this->assertContains('imported', $donor->tags);
        $this->assertContains('vip', $donor->tags);
        $this->assertContains('priority', $donor->tags);
    }

    public function test_advanced_donor_filters_by_tag_and_amount(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);

        Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'High Donor',
            'total_donated' => 5000,
            'tags' => ['imported', 'vip'],
        ]);
        Donor::factory()->create([
            'organization_id' => $org->id,
            'full_name' => 'Low Donor',
            'total_donated' => 100,
            'tags' => ['imported'],
        ]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->get(route('donors.index', ['tag' => 'vip', 'min_amount' => 1000, 'needs_call' => 0]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Donors/Index')
                ->has('donors.data', 1)
                ->where('donors.data.0.full_name', 'High Donor')
            );
    }

    public function test_organization_profile_page_loads(): void
    {
        $org = Organization::factory()->create(['name' => 'Hope Profile']);
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->get(route('organizations.show', $org))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Organizations/Show')
                ->where('organization.name', 'Hope Profile')
            );
    }

    public function test_razorpay_order_creation_and_webhook_marks_paid(): void
    {
        Http::fake([
            'api.razorpay.com/*' => Http::response([
                'id' => 'order_test_123',
                'amount' => 100000,
                'currency' => 'INR',
            ], 200),
        ]);

        $org = Organization::factory()->create([
            'razorpay_enabled' => true,
            'razorpay_key_id' => 'rzp_test_key',
            'razorpay_key_secret' => 'rzp_test_secret',
            'razorpay_webhook_secret' => 'whsec_test',
            'feature_overrides' => ['razorpay' => true],
        ]);
        $admin = User::factory()->orgAdmin()->create();
        $admin->organizations()->attach($org->id, ['is_active' => true]);
        $donor = Donor::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($admin);
        OrganizationContext::set($org->id);

        $this->postJson(route('donors.razorpay.order', $donor), [
            'amount' => 1000,
        ])->assertOk()->assertJsonPath('order_id', 'order_test_123');

        $this->assertDatabaseHas('razorpay_payments', [
            'organization_id' => $org->id,
            'donor_id' => $donor->id,
            'razorpay_order_id' => 'order_test_123',
            'status' => 'created',
        ]);

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_test_9',
                        'order_id' => 'order_test_123',
                        'amount' => 100000,
                    ],
                ],
            ],
        ];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $raw, 'whsec_test');

        $this->call(
            'POST',
            route('razorpay.webhook', $org),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Razorpay-Signature' => $signature,
            ],
            $raw
        )->assertOk();

        $this->assertDatabaseHas('razorpay_payments', [
            'razorpay_order_id' => 'order_test_123',
            'status' => 'paid',
            'razorpay_payment_id' => 'pay_test_9',
        ]);
        $this->assertDatabaseHas('donations', [
            'donor_id' => $donor->id,
            'payment_method' => 'razorpay',
            'amount' => 1000,
        ]);
    }
}
