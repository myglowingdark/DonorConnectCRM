<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'donor_id' => Donor::factory(),
            'external_donation_id' => 'don-'.fake()->unique()->numerify('########'),
            'amount' => fake()->randomElement([500, 1000, 2100, 5000, 11000]),
            'currency' => 'INR',
            'donated_at' => fake()->dateTimeBetween('-12 months', 'now'),
            'payment_status' => 'completed',
            'payment_method' => fake()->randomElement(['UPI', 'Card', 'NetBanking', 'Cash']),
        ];
    }
}
