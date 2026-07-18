<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Person;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'person_id' => Person::factory(),
            'category_id' => null,
            'type' => fake()->randomElement(TransactionType::cases()),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 10, 500),
            'frequency' => RecurringFrequency::Monthly,
            'interval' => 1,
            'start_date' => $startDate,
            'end_date' => null,
            'next_run_date' => $startDate,
            'is_active' => true,
        ];
    }
}
