<?php

namespace Database\Factories;

use App\Models\BalanceSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalanceSnapshot>
 */
class BalanceSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => fake()->randomFloat(2, 500, 25000),
            'captured_at' => now(),
            'trading_channel' => 'binance',
        ];
    }
}
