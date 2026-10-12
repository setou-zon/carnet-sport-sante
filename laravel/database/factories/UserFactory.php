<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('secret123'),
            'role' => 'evaluateur',
            'actif' => true,
            'niveau' => 'evaluateur',
        ];
    }

    public function admin(): static { return $this->state(['role' => 'admin']); }
    public function inactif(): static { return $this->state(['actif' => false]); }
    public function niveau(string $n, ?int $province = null, ?int $region = null): static
    {
        return $this->state(['niveau' => $n, 'province_id' => $province, 'region_id' => $region]);
    }
}
