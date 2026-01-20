<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'phone_number' => fake()->unique()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'country_id' => null,
            'is_super_admin' => false,
            'is_support' => false,
            'is_player' => true,
            'is_organizer' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
            'is_player' => false,
        ]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_support' => true,
        ]);
    }

    public function player(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_player' => true,
        ]);
    }

    public function organizer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_organizer' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
