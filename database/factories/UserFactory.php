<?php

namespace Database\Factories;

use App\Enums\UserRole;
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
            'username'   => fake()->unique()->userName(),
            'email'      => fake()->unique()->safeEmail(),
            'password'   => static::$password ??= Hash::make('123456'),
            'full_name'  => fake()->name(),
            'phone'      => fake()->numerify('09########'),
            'street'     => fake()->streetAddress(),
            'ward'       => 'Phường ' . fake()->numberBetween(1, 15),
            'district'   => 'Quận ' . fake()->numberBetween(1, 12),
            'city'       => fake()->randomElement(['TP. Hồ Chí Minh', 'Hà Nội', 'Đà Nẵng']),
            'role'       => UserRole::User,
            'is_active'  => true,
            'api_token'  => Str::random(60),
        ];
    }

    /**
     * State: Tạo user với role admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * State: Tạo user bị vô hiệu hoá.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
