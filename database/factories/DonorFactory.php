<?php

namespace Database\Factories;

use App\Enums\DonorStatus;
use App\Models\Donor;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donor>
 */
class DonorFactory extends Factory
{
    protected $model = Donor::class;

    public function definition(): array
    {
        $amount = fake()->randomElement([500, 1000, 2100, 5000, 11000, 25000]);

        return [
            'organization_id' => Organization::factory(),
            'external_donor_id' => 'ext-'.fake()->unique()->numerify('######'),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+91 9#########'),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'India',
            'donor_status' => DonorStatus::New,
            'do_not_call' => false,
            'last_donation_at' => fake()->dateTimeBetween('-18 months', '-1 week'),
            'last_donation_amount' => $amount,
            'total_donated' => $amount * fake()->numberBetween(1, 5),
        ];
    }
}
