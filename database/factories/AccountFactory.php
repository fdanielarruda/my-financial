<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
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
            'person_id' => Person::factory(),
            'institution_id' => null,
            'name' => fake()->words(2, true),
            'type' => AccountType::Checking,
            'initial_balance' => 0,
        ];
    }

    public function creditCard(): static
    {
        return $this->state(fn () => ['type' => AccountType::CreditCard]);
    }
}
