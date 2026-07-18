<?php

namespace Tests\Feature\Finance;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Person;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_a_due_transaction_creates_it_and_advances_next_run_date(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $account = Account::factory()->for($user)->for($person)->create();

        $recurring = RecurringTransaction::factory()->for($user)->for($account)->for($person)->create([
            'type' => TransactionType::Expense,
            'description' => 'Aluguel',
            'amount' => 1200,
            'frequency' => RecurringFrequency::Monthly,
            'interval' => 1,
            'start_date' => Carbon::parse('2026-07-05'),
            'next_run_date' => Carbon::parse('2026-07-05'),
            'end_date' => null,
        ]);

        $transaction = $recurring->generateDueTransaction();

        $this->assertSame('1200.00', $transaction->amount);
        $this->assertSame($recurring->id, $transaction->recurring_transaction_id);
        $this->assertSame('2026-07-05', $transaction->date->format('Y-m-d'));

        $recurring->refresh();
        $this->assertSame('2026-08-05', $recurring->next_run_date->format('Y-m-d'));
        $this->assertTrue($recurring->is_active);
    }

    public function test_recurring_transaction_deactivates_once_it_passes_its_end_date(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $account = Account::factory()->for($user)->for($person)->create();

        $recurring = RecurringTransaction::factory()->for($user)->for($account)->for($person)->create([
            'frequency' => RecurringFrequency::Monthly,
            'interval' => 1,
            'next_run_date' => Carbon::parse('2026-07-05'),
            'end_date' => Carbon::parse('2026-07-20'),
        ]);

        $recurring->generateDueTransaction();

        $recurring->refresh();
        $this->assertFalse($recurring->is_active);
    }
}
