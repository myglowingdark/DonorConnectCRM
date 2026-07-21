<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Foundation';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'brand_color' => fake()->randomElement(['#1e3a8a', '#0f766e', '#166534', '#7c2d12']),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
        ];
    }
}
