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
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Perfil completo y visible en Descubrir.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $catalog = config('datecito.catalog');

        return [
            'name' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password1'),
            'birthdate' => fake()->dateTimeBetween('-40 years', '-21 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Mujer', 'Hombre']),
            'interested_in' => ['Todas las personas'],
            'job' => fake()->jobTitle(),
            'bio' => fake()->sentence(12),
            'intent' => fake()->randomElement($catalog['intents']),
            'relationship_type' => fake()->randomElement($catalog['relationship_types']),
            'zone' => 'Roma Norte',
            'interests' => fake()->randomElements($catalog['interests']['Cultura'], 3),
            'music_genres' => fake()->randomElements($catalog['genres'], 2),
            'music_artists' => [],
            'basics' => ['drink' => 'Socialmente'],
            'languages' => ['Español'],
            'prompts' => [['q' => 'Mi datecito ideal', 'a' => 'Café y caminar.']],
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function verifiedProfile(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['verified_at' => now(), 'verification_status' => 'approved'])->save());
    }

    /**
     * Ubicación aproximada (CDMX por defecto).
     */
    public function locatedAt(float $lat = 19.42, float $lng = -99.16): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['approx_lat' => $lat, 'approx_lng' => $lng])->save());
    }

    public function withPhoto(): static
    {
        return $this->afterCreating(fn (User $user) => $user->photos()->create(['position' => 0, 'storage_key' => 'photos/'.$user->id.'/test']));
    }
}
