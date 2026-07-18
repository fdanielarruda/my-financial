<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'account_id' => Account::factory(),
            'person_id' => Person::factory(),
            'category_id' => null,
            'type' => fake()->randomElement(TransactionType::cases()),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 10, 500),
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
