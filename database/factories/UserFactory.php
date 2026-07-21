<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Volunteer,
            'phone' => fake()->numerify('+91 98########'),
            'is_active' => true,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::SuperAdmin,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function orgAdmin(): static
    {
        return $this->state(fn () => ['role' => UserRole::OrganizationAdmin]);
    }

    public function volunteer(): static
    {
        return $this->state(fn () => ['role' => UserRole::Volunteer]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
