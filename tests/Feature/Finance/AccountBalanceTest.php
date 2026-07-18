<?php

namespace Tests\Feature\Finance;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Person;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_reflects_initial_balance_income_and_expense(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $account = Account::factory()->for($user)->for($person)->create(['initial_balance' => 100]);

        $account->transactions()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'type' => TransactionType::Income,
            'description' => 'Salário',
            'amount' => 500,
            'date' => now(),
        ]);

        $account->transactions()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Mercado',
            'amount' => 80,
            'date' => now(),
        ]);

        $this->assertSame('520.00', $account->balance());
    }

    public function test_balance_reflects_transfers_in_and_out(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $from = Account::factory()->for($user)->for($person)->create(['initial_balance' => 300]);
        $to = Account::factory()->for($user)->for($person)->create(['initial_balance' => 0]);

        Transfer::create([
            'user_id' => $user->id,
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 120,
            'date' => now(),
        ]);

        $this->assertSame('180.00', $from->balance());
        $this->assertSame('120.00', $to->balance());
    }
}
